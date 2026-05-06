<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Services\CourseModuleService;
use App\Services\PracticeLabService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PracticeLabController extends Controller
{
    public function start(Request $request, int $module): RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        $courseId = (int) session('course_id', 0);
        $cm = app(CourseModuleService::class)->findOrFailForCourse($courseId, $module);
        $daemonKey = $cm->effectiveContentIndex();
        $out = PracticeLabService::make()->startLab($learner, $courseId, (int) $cm->id, $daemonKey);
        $msg = $out['message'] ?? null;

        return redirect()
            ->route('modules.practice', $module)
            ->with($msg ? 'err' : 'ok', $msg ?? 'Стенд выделен. Откройте терминал по ссылке.');
    }

    public function check(Request $request, int $module): RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        $courseId = (int) session('course_id', 0);
        app(CourseModuleService::class)->findOrFailForCourse($courseId, $module);
        $out = PracticeLabService::make()->runCheck($learner, $courseId, $module);
        $msg = $out['message'] ?? null;

        return redirect()
            ->route('modules.practice', $module)
            ->with($msg ? 'err' : 'ok', $msg ?? 'Проверка выполнена.');
    }

    public function accept(Request $request, int $module): RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        $courseId = (int) session('course_id', 0);
        app(CourseModuleService::class)->findOrFailForCourse($courseId, $module);
        try {
            PracticeLabService::make()->acceptPracticeResult($learner, $courseId, $module);
        } catch (\Throwable $e) {
            return redirect()->route('modules.practice', $module)->with('err', $e->getMessage());
        }

        return redirect()->route('modules.hub', $module)->with('ok', 'Результат практики принят.');
    }

    public function finish(Request $request, int $module): RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        $courseId = (int) session('course_id', 0);
        app(CourseModuleService::class)->findOrFailForCourse($courseId, $module);
        PracticeLabService::make()->destroyLab($learner, $courseId, $module);

        return redirect()->route('modules.practice', $module)->with('ok', 'Работа со стендом завершена.');
    }
}
