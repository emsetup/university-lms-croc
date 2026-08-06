<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Learner;
use App\Services\CourseModuleService;
use App\Services\CourseScoringService;
use App\Services\LearnerContentVisibilityService;
use App\Services\ModuleAccessGate;
use App\Support\CourseAudiencePlaque;
use App\Support\LearnerPreviewContext;
use App\Support\LearnerScoreDisplay;
use App\Support\LearnerSsoDisplayNamePersistence;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring,
        private ModuleAccessGate $accessGate,
        private CourseModuleService $courseModules,
        private LearnerContentVisibilityService $visibility,
    ) {}

    public function __invoke(): View
    {
        /** @var Learner $learner */
        $learner = Learner::query()
            ->with('moduleProgresses')
            ->findOrFail(LearnerPreviewContext::learnerId());
        LearnerSsoDisplayNamePersistence::syncIfPossible($learner);

        $courseId = LearnerPreviewContext::courseId();
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        $finalLabEnabled = $course ? (bool) ($course->final_lab_enabled ?? false) : true;
        $certificateEnabled = $course ? (bool) ($course->certificate_enabled ?? true) : true;
        $showModuleProgress = ! $course
            || ! Schema::hasColumn('courses', 'show_module_progress')
            || (bool) ($course->show_module_progress ?? true);
        $assessmentEnabled = ! $course
            || ! Schema::hasColumn('courses', 'assessment_enabled')
            || (bool) ($course->assessment_enabled ?? true);
        $showScorePercents = LearnerScoreDisplay::showPercents($course);
        $showScorePoints = LearnerScoreDisplay::showPoints($course);
        $modules = [];
        $sumPercent = 0;
        $modulesPassed = 0;

        if ($courseId > 0 && Schema::hasTable('course_modules')) {
            $mods = $this->visibility->visibleModulesForLearner($courseId, (int) $learner->id);
            foreach ($mods as $idx => $mod) {
                $id = (int) $mod->id;
                $meta = $this->courseModules->displayMeta($mod);
                $sequence = $idx + 1;
                $existing = $learner->progressExisting($id, $courseId);
                $unlocked = $this->accessGate->isModuleUnlocked($learner, $id);
                if ($existing === null && ! $unlocked) {
                    $modules[] = [
                        'id' => $id,
                        'sequence' => $sequence,
                        'letter' => $meta['letter'],
                        'title' => $meta['title'],
                        'summary' => $meta['summary'],
                        'percent' => 0,
                        'unlocked' => false,
                        'exam_passed' => false,
                    ];
                    $sumPercent += 0;

                    continue;
                }

                $p = $existing ?? $learner->progressFor($id, $courseId);
                $percent = $this->scoring->moduleProgressPercent($p);
                if ($p->module_exam_passed) {
                    $modulesPassed++;
                }
                $sumPercent += $percent;
                $modules[] = [
                    'id' => $id,
                    'sequence' => $sequence,
                    'letter' => $meta['letter'],
                    'title' => $meta['title'],
                    'summary' => $meta['summary'],
                    'percent' => $percent,
                    'unlocked' => $unlocked,
                    'exam_passed' => (bool) $p->module_exam_passed,
                ];
            }
        }

        $moduleCount = max(1, count($modules));
        $trackAvgPercent = (int) round($sumPercent / $moduleCount);
        $finalResult = $learner->finalLabResult;
        $finalBest = (int) ($finalResult->best_score ?? 0);
        $finalAttempts = (int) ($finalResult->attempts ?? 0);
        $allDone = $this->scoring->allModulesComplete($learner, $courseId > 0 ? $courseId : null);
        $finalDone = $finalLabEnabled ? (bool) optional($learner->finalLabResult)->passed : true;

        return view('dashboard', [
            'courseId' => $courseId,
            'modules' => $modules,
            'courseModuleCount' => $moduleCount,
            'trackModulesPassed' => $modulesPassed,
            'trackAvgPercent' => $trackAvgPercent,
            'modulePointsTotal' => $this->scoring->totalModulePointsSafe($learner),
            'modulePointsMax' => $this->scoring->maxTotalModulePoints(),
            'allDone' => $allDone,
            'finalDone' => $finalDone,
            'finalLabEnabled' => $finalLabEnabled,
            'certificateEnabled' => $certificateEnabled,
            'finalLabBestScore' => $finalBest,
            'finalLabAttempts' => $finalAttempts,
            'assessmentSnapshot' => $this->scoring->learnerAssessmentSnapshot($learner),
            'showModuleProgress' => $showModuleProgress,
            'assessmentEnabled' => $assessmentEnabled,
            'showScorePercents' => $showScorePercents,
            'showScorePoints' => $showScorePoints,
            'showFurtherCourseSection' => $assessmentEnabled || $finalLabEnabled || $certificateEnabled,
            'showInformativeCourseNotice' => $course
                && Schema::hasColumn('courses', 'assessment_enabled')
                && Schema::hasColumn('courses', 'certificate_enabled')
                && ! $assessmentEnabled
                && ! $certificateEnabled,
            'audiencePlaque' => CourseAudiencePlaque::forCourse($course),
        ]);
    }
}
