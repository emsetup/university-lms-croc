<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Services\CourseContentService;
use App\Services\CourseSectionService;
use App\Services\LearnerCourseAvailability;
use App\Services\SurveyQuickLinkService;
use App\Services\SurveyResponseService;
use App\Support\LearnerPreviewContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SurveyQuickLinkController extends Controller
{
    public function __construct(
        private SurveyQuickLinkService $quickLinks,
        private CourseSectionService $sections,
        private CourseContentService $content,
        private SurveyResponseService $surveys,
    ) {}

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $link = $this->quickLinks->resolve($token);
        if ($link === null) {
            return redirect()->route('portal')->with('err', 'Ссылка на опрос недействительна или отключена.');
        }

        $sec = $link->courseSection;
        $course = $sec->loadMissing('course')->course;
        if (! LearnerCourseAvailability::isOpenForLearning($course)) {
            return redirect()->route('portal')->with('err', 'Этот курс снят с обучения. Быстрая ссылка на опрос недоступна.');
        }

        $learner = $this->learner();
        $this->quickLinks->ensureEnrollment($learner, $course);

        $bank = $this->content->quizBankForSection($sec);
        $questions = $bank ? $this->content->questionsForBank($bank) : [];
        if ($questions === []) {
            return redirect()->route('portal')->with('err', 'В опросе пока нет вопросов.');
        }

        $settings = $this->sections->mergedSettings($sec);
        $existing = $this->surveys->submissionForLearner((int) $sec->id, (int) $learner->id);

        return view('modules.survey', [
            'courseId' => (int) $course->id,
            'module' => $sec->loadMissing('courseModule')->courseModule,
            'moduleSequence' => null,
            'section' => $sec,
            'sectionSequence' => null,
            'questions' => $questions,
            'settings' => $settings,
            'anonymous' => (bool) ($settings['anonymous'] ?? false),
            'submitted' => $existing !== null,
            'submission' => $existing,
            'quickLinkMode' => true,
            'quickLinkToken' => $token,
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $link = $this->quickLinks->resolve($token);
        if ($link === null) {
            return redirect()->route('portal')->with('err', 'Ссылка на опрос недействительна или отключена.');
        }

        $sec = $link->courseSection;
        $course = $sec->loadMissing('course')->course;
        if (! LearnerCourseAvailability::isOpenForLearning($course)) {
            return redirect()->route('portal')->with('err', 'Этот курс снят с обучения. Быстрая ссылка на опрос недоступна.');
        }

        $learner = $this->learner();
        $this->quickLinks->ensureEnrollment($learner, $course);

        if ($this->surveys->hasSubmission((int) $sec->id, (int) $learner->id)) {
            return redirect()->route('survey.quick', ['token' => $token])
                ->with('err', 'Вы уже отправили ответы на этот опрос.');
        }

        $cm = $sec->loadMissing('courseModule')->courseModule;
        $bank = $this->content->quizBankForSection($sec);
        if (! $bank) {
            return redirect()->route('portal')->with('err', 'Банк вопросов опроса не найден.');
        }

        $questions = $this->content->questionsForBank($bank);
        $v = $this->surveys->validateSubmission($request, $questions, $bank);
        if ($v['ok'] !== true) {
            return back()->withInput()->with('err', $v['message']);
        }

        $this->surveys->storeSubmission($learner, $course, $cm, $sec, $bank, $questions, $request);

        return redirect()->route('survey.quick', ['token' => $token])
            ->with('ok', 'Спасибо! Ваши ответы сохранены.');
    }

    private function learner(): Learner
    {
        return Learner::findOrFail(LearnerPreviewContext::learnerId());
    }
}
