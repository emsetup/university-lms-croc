<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Learner;
use App\Models\PracticeSession;
use App\Support\CourseModuleMeta;
use App\Services\CourseScoringService;
use App\Services\InstructorProgressResetService;
use App\Services\TeacherCourseAnalyticsService;
use App\Services\TeacherLearnerProfileDetailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherCourseReportController extends Controller
{
    public function __construct(
        private TeacherCourseAnalyticsService $analytics,
        private CourseScoringService $scoring,
        private TeacherLearnerProfileDetailService $learnerDetail,
        private InstructorProgressResetService $instructorReset
    ) {}

    public function index(): View
    {
        return view('teacher-course-report', [
            'learnerRows' => $this->analytics->learnerRows(),
        ]);
    }

    public function learner(int $learner): View
    {
        $l = Learner::query()
            ->with(['moduleProgresses', 'finalLabResult', 'courseEnrollments'])
            ->findOrFail($learner);
        $courseId = $this->learnerCourseId($l);

        return view('teacher-learner-profile', [
            'learner' => $l,
            'summaryRow' => $this->analytics->rowForLearner($l),
            'moduleReport' => $this->scoring->moduleReport($l, $courseId),
            'modulePanels' => $this->learnerDetail->modulePanels($l),
        ]);
    }

    public function moduleShow(int $learner, int $module): View
    {
        $l = Learner::query()
            ->with(['moduleProgresses', 'finalLabResult', 'courseEnrollments'])
            ->findOrFail($learner);
        $panel = $this->learnerDetail->modulePanel($l, $module);
        abort_if($panel === null, 404);

        return view('teacher-learner-module', [
            'learner' => $l,
            'module' => $module,
            'panel' => $panel,
            'summaryRow' => $this->analytics->rowForLearner($l),
        ]);
    }

    public function resetAttempt(Request $request, int $learner, int $module): RedirectResponse
    {
        $request->validate([
            'step' => 'required|in:theory_quiz,module_exam,practice',
            'confirm' => 'accepted',
            'note' => 'nullable|string|max:500',
        ]);
        $l = Learner::query()->with('courseEnrollments')->findOrFail($learner);
        $courseId = $this->learnerCourseId($l);
        $panel = $this->learnerDetail->modulePanel($l, $module);
        abort_if($panel === null, 404);
        $p = $l->progressFor($module, $courseId);
        $step = (string) $request->input('step');
        if ($step === InstructorProgressResetService::STEP_THEORY_QUIZ) {
            $hist = $p->theory_quiz_history ?? [];
            if ((int) $p->theory_quiz_attempts < 1 && ! is_array($p->theory_quiz_last_result) && (! is_array($hist) || count($hist) === 0)) {
                return $this->redirectModuleWithKey($request, $learner, $module)
                    ->with('err', 'Нет зафиксированных попыток теста по теории — сброс не требуется.');
            }
        }
        if ($step === InstructorProgressResetService::STEP_MODULE_EXAM) {
            $hist = $p->module_exam_history ?? [];
            if ((int) $p->module_exam_attempts < 1 && ! is_array($p->module_exam_last_result) && (! is_array($hist) || count($hist) === 0)) {
                return $this->redirectModuleWithKey($request, $learner, $module)
                    ->with('err', 'Нет зафиксированных попыток экзамена — сброс не требуется.');
            }
        }
        if ($step === InstructorProgressResetService::STEP_PRACTICE) {
            $idx = (int) ($panel['content_source_index'] ?? 1);
            if (CourseModuleMeta::shouldSkipPractice($idx)) {
                return $this->redirectModuleWithKey($request, $learner, $module)
                    ->with('err', 'В этом модуле практика не предусмотрена.');
            }
            $hasSession = PracticeSession::query()
                ->where('learner_id', $l->id)
                ->where('course_id', $courseId)
                ->where('module_id', $module)
                ->exists();
            if (! $p->practice_done_at && ! $hasSession) {
                return $this->redirectModuleWithKey($request, $learner, $module)
                    ->with('err', 'Практика не начиналась и не отмечена — сброс не требуется.');
            }
        }
        $this->instructorReset->reset(
            $l,
            $courseId,
            $module,
            $step,
            $request->filled('note') ? (string) $request->input('note') : null
        );

        return $this->redirectModuleWithKey($request, $learner, $module)
            ->with('ok', 'Сброс выполнен: у обучающегося освобождена ещё одна попытка по выбранному шагу. Снимок состояния сохранён в журнале ниже.');
    }

    private function learnerCourseId(Learner $learner): int
    {
        $id = (int) $learner->courseEnrollments()->orderBy('id')->value('course_id');
        if ($id > 0) {
            return $id;
        }

        return (int) Course::query()->where('slug', 'alt-os-features')->value('id');
    }

    private function redirectModuleWithKey(Request $request, int $learner, int $module): RedirectResponse
    {
        $url = route('teacher.course-report.learner.module', ['learner' => $learner, 'module' => $module], false);
        if ($request->filled('key')) {
            $url .= '?key='.urlencode((string) $request->query('key'));
        }

        return redirect()->to($url);
    }
}
