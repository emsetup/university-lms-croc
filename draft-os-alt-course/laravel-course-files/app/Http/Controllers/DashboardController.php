<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Services\CourseScoringService;
use App\Services\ModuleAccessGate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring,
        private ModuleAccessGate $accessGate
    ) {}

    public function __invoke(): View
    {
        /** @var Learner $learner */
        $learner = Learner::query()
            ->with('moduleProgresses')
            ->findOrFail(session('learner_id'));

        $modules = [];
        $sumPercent = 0;
        $modulesPassed = 0;
        $moduleCount = CourseScoringService::moduleCount();
        for ($i = 1; $i <= $moduleCount; $i++) {
            $meta = config('course.modules.'.$i);
            $existing = $learner->progressExisting($i);
            $unlocked = $this->accessGate->isModuleUnlocked($learner, $i);
            if ($existing === null && ! $unlocked) {
                $modules[] = [
                    'id' => $i,
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

            $p = $existing ?? $learner->progressFor($i);
            $percent = $this->scoring->moduleProgressPercent($p);
            if ($p->module_exam_passed) {
                $modulesPassed++;
            }
            $sumPercent += $percent;
            $modules[] = [
                'id' => $i,
                'letter' => $meta['letter'],
                'title' => $meta['title'],
                'summary' => $meta['summary'],
                'percent' => $percent,
                'unlocked' => $unlocked,
                'exam_passed' => (bool) $p->module_exam_passed,
            ];
        }

        $trackAvgPercent = (int) round($sumPercent / $moduleCount);

        return view('dashboard', [
            'modules' => $modules,
            'courseModuleCount' => $moduleCount,
            'trackModulesPassed' => $modulesPassed,
            'trackAvgPercent' => $trackAvgPercent,
            'modulePointsTotal' => $this->scoring->totalModulePointsSafe($learner),
            'modulePointsMax' => $this->scoring->maxTotalModulePoints(),
            'allDone' => $this->scoring->allModulesComplete($learner),
            'finalDone' => (bool) optional($learner->finalLabResult)->passed,
        ]);
    }
}
