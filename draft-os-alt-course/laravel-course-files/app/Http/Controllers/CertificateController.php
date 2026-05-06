<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Services\CourseScoringService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring
    ) {}

    public function __invoke(): View|RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        $final = $learner->finalLabResult;
        if (! $final || ! $final->passed) {
            return redirect()->route('final-lab')->with('err', 'Сначала сдайте финальную лабораторную работу.');
        }

        $courseId = (int) session('course_id', 0);
        $certCoursePercent = $this->scoring->certificateCoursePercent($learner, $courseId > 0 ? $courseId : null);

        return view('certificate', [
            'learner' => $learner,
            'grand' => $this->scoring->grandTotal($learner, $courseId > 0 ? $courseId : null),
            'certCoursePercent' => $certCoursePercent,
            'certTier' => $this->scoring->certificateTier($certCoursePercent),
            'modulePoints' => $this->scoring->totalModulePoints($learner, $courseId > 0 ? $courseId : null),
            'modulePointsMax' => $this->scoring->maxTotalModulePoints($courseId > 0 ? $courseId : null),
            'finalPoints' => $this->scoring->finalLabPoints($final),
            'moduleReport' => $this->scoring->moduleReport($learner, $courseId > 0 ? $courseId : null),
            'final' => $final,
        ]);
    }

    public function saveRecipient(Request $request): RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        $final = $learner->finalLabResult;
        if (! $final || ! $final->passed) {
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
}
