<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\FinalLabResult;
use App\Models\Learner;
use App\Support\LearnerPreviewContext;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;

class CertificateController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring
    ) {}

    public function __invoke(): View|RedirectResponse
    {
        $learner = Learner::findOrFail(LearnerPreviewContext::learnerId());
        $courseId = LearnerPreviewContext::courseId();
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        if ($course && ! $course->certificate_enabled) {
            return redirect()->route('dashboard')->with('err', 'Сертификат для этого курса отключён.');
        }
        $finalLabEnabled = $course ? (bool) ($course->final_lab_enabled ?? false) : true;
        $final = $this->resolveCertificateFinalRow($learner, $courseId, $finalLabEnabled);
        if ($finalLabEnabled && ! $final->passed) {
            return redirect()->route('final-lab')->with('err', 'Сначала сдайте финальную лабораторную работу.');
        }

        $certCoursePercent = $this->scoring->certificateCoursePercent($learner, $courseId > 0 ? $courseId : null, $final);
        $certTier = $this->scoring->certificateTier($certCoursePercent, $courseId > 0 ? $courseId : null);
        if ($certTier === null) {
            return redirect()->route('dashboard')->with('err', 'Сертификат не выдаётся: недостаточно результата по курсу.');
        }

        $finalLabMax = $this->scoring->maxFinalLabPoints($courseId > 0 ? $courseId : null);

        return view('certificate', [
            'course' => $course,
            'learner' => $learner,
            'grand' => $this->scoring->grandTotal($learner, $courseId > 0 ? $courseId : null, $final),
            'certCoursePercent' => $certCoursePercent,
            'certTier' => $certTier,
            'modulePoints' => $this->scoring->totalModulePoints($learner, $courseId > 0 ? $courseId : null),
            'modulePointsMax' => $this->scoring->maxTotalModulePoints($courseId > 0 ? $courseId : null),
            'finalPoints' => $this->scoring->finalLabPoints($final),
            'moduleReport' => $this->scoring->moduleReport($learner, $courseId > 0 ? $courseId : null),
            'final' => $final,
            'finalLabMax' => $finalLabMax,
        ]);
    }

    public function saveRecipient(Request $request): RedirectResponse
    {
        $learner = Learner::findOrFail(LearnerPreviewContext::learnerId());
        $courseId = LearnerPreviewContext::courseId();
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        if ($course && ! $course->certificate_enabled) {
            return redirect()->route('dashboard')->with('err', 'Сертификат для этого курса отключён.');
        }
        $finalLabEnabled = $course ? (bool) ($course->final_lab_enabled ?? false) : true;
        $final = $this->resolveCertificateFinalRow($learner, $courseId, $finalLabEnabled);
        if ($finalLabEnabled && ! $final->passed) {
            return redirect()->route('final-lab')->with('err', 'Сначала сдайте финальную лабораторную работу.');
        }
        if (! empty($final->certificate_full_name)) {
            return redirect()->route('certificate')->with('err', 'ФИО для сертификата уже зафиксировано и не может быть изменено.');
        }

        $data = $request->validate([
            'certificate_full_name' => ['required', 'string', 'min:5', 'max:120'],
        ], [
            'certificate_full_name.required' => 'Укажите ФИО для сертификата.',
            'certificate_full_name.min' => 'ФИО должно содержать не менее 5 символов.',
            'certificate_full_name.max' => 'ФИО не должно превышать 120 символов.',
        ]);

        $final->certificate_full_name = trim((string) $data['certificate_full_name']);
        if (! $final->certificate_serial) {
            $final->certificate_serial = $this->makeCertificateSerial($learner->id, $final->id ?? 0);
        }
        if (! $final->certificate_issued_at) {
            $final->certificate_issued_at = now();
        }
        $final->save();

        return redirect()->route('certificate')->with('ok', 'Данные сертификата сохранены. Можно скачать сертификат в формате PNG.');
    }

    private function makeCertificateSerial(int $learnerId, int $resultId): string
    {
        $date = now()->format('Ymd');
        $suffix = str_pad((string) max(1, $resultId), 5, '0', STR_PAD_LEFT);

        return sprintf('CROC-ALT-%s-%s-L%04d', $date, $suffix, $learnerId);
    }

    private function resolveCertificateFinalRow(Learner $learner, int $courseId, bool $finalLabEnabled): FinalLabResult
    {
        // Если финальная лабораторная выключена, сертификат живёт "сам по себе" и хранит поля в final_lab_results
        // (ФИО/номер/дата) без обязательного прохождения финальной лабы.
        if (! $finalLabEnabled && $courseId > 0 && Schema::hasTable('final_lab_results')) {
            /** @var FinalLabResult $row */
            $row = FinalLabResult::query()->firstOrCreate(
                ['learner_id' => $learner->id, 'course_id' => $courseId],
                ['attempts' => 0, 'passed' => true, 'best_score' => 0, 'completed_at' => now()]
            );
            if (! $row->passed) {
                $row->passed = true;
                $row->save();
            }

            return $row;
        }

        $row = $courseId > 0
            ? $learner->finalLabResults()->where('course_id', $courseId)->first()
            : $learner->finalLabResult;

        return $row instanceof FinalLabResult
            ? $row
            : new FinalLabResult(['learner_id' => $learner->id, 'course_id' => $courseId, 'attempts' => 0, 'passed' => false, 'best_score' => 0]);
    }
}
