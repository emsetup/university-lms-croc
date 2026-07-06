<?php

namespace App\Http\Controllers;

use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\Learner;
use App\Services\CourseContentService;
use App\Services\CourseModuleService;
use App\Services\CourseSectionService;
use App\Services\ModuleAccessGate;
use App\Services\SurveyResponseService;
use App\Support\LearnerPreviewContext;
use App\Support\LearnerRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SurveyController extends Controller
{
    public function __construct(
        private CourseModuleService $courseModules,
        private CourseSectionService $sections,
        private CourseContentService $content,
        private ModuleAccessGate $access,
        private SurveyResponseService $surveys,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $ctx = $this->routeContext($request);
        $learner = $this->learner();
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $sec = $ctx['section'];
        if ($r = $this->access->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->access->redirectIfSectionHidden($learner, (int) $sec->id, $mid)) {
            return $r;
        }
        if ($r = $this->access->redirectIfStepBlocked($learner, $mid, $sec->backendStepKey())) {
            return $r;
        }

        $bank = $this->content->quizBankForSection($sec);
        $questions = $bank ? $this->content->questionsForBank($bank) : [];
        if ($questions === []) {
            return redirect()->route('course.module.hub', LearnerRoute::hub($ctx['courseId'], $ctx['moduleSequence']))
                ->with('err', 'В опросе пока нет вопросов.');
        }

        $settings = $this->sections->mergedSettings($sec);
        $existing = $this->surveys->submissionForLearner((int) $sec->id, (int) $learner->id);

        return view('modules.survey', [
            'courseId' => $ctx['courseId'],
            'module' => $cm,
            'moduleSequence' => $ctx['moduleSequence'],
            'section' => $sec,
            'sectionSequence' => $ctx['sectionSequence'],
            'questions' => $questions,
            'settings' => $settings,
            'anonymous' => (bool) ($settings['anonymous'] ?? false),
            'submitted' => $existing !== null,
            'submission' => $existing,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $ctx = $this->routeContext($request);
        $learner = $this->learner();
        $cm = $ctx['cm'];
        $mid = $ctx['mid'];
        $sec = $ctx['section'];
        if ($r = $this->access->redirectIfModuleLocked($learner, $mid)) {
            return $r;
        }
        if ($r = $this->access->redirectIfSectionHidden($learner, (int) $sec->id, $mid)) {
            return $r;
        }
        if ($r = $this->access->redirectIfStepBlocked($learner, $mid, $sec->backendStepKey())) {
            return $r;
        }

        $surveyParams = LearnerRoute::section($ctx['courseId'], $ctx['moduleSequence'], $ctx['sectionSequence']);
        if ($this->surveys->hasSubmission((int) $sec->id, (int) $learner->id)) {
            return redirect()->route('course.module.section.survey', $surveyParams)
                ->with('err', 'Вы уже отправили ответы на этот опрос.');
        }

        $course = $cm->loadMissing('course')->course;
        $bank = $this->content->quizBankForSection($sec);
        if (! $bank) {
            return redirect()->route('course.module.hub', LearnerRoute::hub($ctx['courseId'], $ctx['moduleSequence']))
                ->with('err', 'Банк вопросов опроса не найден.');
        }

        $questions = $this->content->questionsForBank($bank);
        $v = $this->surveys->validateSubmission($request, $questions, $bank);
        if ($v['ok'] !== true) {
            return back()->withInput()->with('err', $v['message']);
        }

        $this->surveys->storeSubmission($learner, $course, $cm, $sec, $bank, $questions, $request);

        return redirect()->route('course.module.section.survey', $surveyParams)
            ->with('ok', 'Спасибо! Ваши ответы сохранены.');
    }

    private function learner(): Learner
    {
        return Learner::findOrFail(LearnerPreviewContext::learnerId());
    }

    /**
     * @return array{
     *     cm: CourseModule,
     *     mid: int,
     *     moduleSequence: int,
     *     courseId: int,
     *     section: CourseSection,
     *     sectionSequence: int
     * }
     */
    private function routeContext(Request $request): array
    {
        $courseRoute = (int) $request->route('course', 0);
        $moduleRoute = (int) $request->route('module');
        $sectionRoute = (int) $request->route('section');
        $courseId = $courseRoute > 0 ? $courseRoute : LearnerPreviewContext::courseId();
        $cm = $this->courseModules->findOrFailForCourseRoute($courseId, $moduleRoute);
        abort_unless((int) $cm->course_id === $courseId, 404);
        $sec = $this->sectionOrAbort($cm, $sectionRoute);

        return [
            'cm' => $cm,
            'mid' => (int) $cm->id,
            'moduleSequence' => $this->courseModules->sequenceForModule($cm),
            'courseId' => $courseId,
            'section' => $sec,
            'sectionSequence' => $this->sections->sequenceForSection($sec),
        ];
    }

    private function sectionOrAbort(CourseModule $cm, int $sectionRoute): CourseSection
    {
        $sec = $this->sections->findOrFailBySequenceForModuleRoute((int) $cm->id, $sectionRoute);
        abort_unless(
            $sec->type === CourseSection::TYPE_SURVEY && $sec->is_enabled,
            404
        );

        return $sec;
    }
}
