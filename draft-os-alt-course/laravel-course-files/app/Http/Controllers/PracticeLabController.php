<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\CourseModulePracticeSetting;
use App\Models\PracticeImage;
use App\Support\LearnerPreviewContext;
use App\Support\LearnerRoute;
use App\Services\CourseModuleService;
use App\Services\PracticeLabService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PracticeLabController extends Controller
{
    /**
     * @return array{cm: \App\Models\CourseModule, id: int, seq: int, courseId: int}
     */
    private function routeContext(?Request $request = null): array
    {
        $request ??= request();
        $courseRoute = (int) $request->route('course', 0);
        $moduleRoute = (int) $request->route('module');
        $courseId = $courseRoute > 0 ? $courseRoute : LearnerPreviewContext::courseId();
        $cm = app(CourseModuleService::class)->findOrFailForCourseRoute($courseId, $moduleRoute);
        abort_unless((int) $cm->course_id === $courseId, 404);

        return [
            'cm' => $cm,
            'id' => (int) $cm->id,
            'seq' => app(CourseModuleService::class)->sequenceForModule($cm),
            'courseId' => $courseId,
        ];
    }

    public function start(Request $request): RedirectResponse
    {
        $learner = Learner::findOrFail(LearnerPreviewContext::learnerId());
        $resolved = $this->routeContext($request);
        $cm = $resolved['cm'];
        $mid = $resolved['id'];
        $courseId = $resolved['courseId'];
        $routeParams = LearnerRoute::hub($courseId, $resolved['seq']);
        $daemonKey = $cm->effectiveContentIndex();

        $imageOverride = null;
        if (class_exists(CourseModulePracticeSetting::class) && class_exists(PracticeImage::class)) {
            $setting = CourseModulePracticeSetting::query()->where('course_module_id', $mid)->first();
            if ($setting && (int) ($setting->practice_image_id ?? 0) > 0) {
                $tag = (string) PracticeImage::query()->where('id', (int) $setting->practice_image_id)->value('docker_tag');
                if ($tag !== '') {
                    $imageOverride = $tag;
                }
            }
            if ($setting && is_numeric($setting->daemon_image_key_override) && (int) $setting->daemon_image_key_override > 0) {
                $daemonKey = (int) $setting->daemon_image_key_override;
            }
        }

        try {
            $out = PracticeLabService::make()->startLab($learner, $courseId, $mid, $daemonKey, $imageOverride);
        } catch (\Throwable $e) {
            return redirect()
                ->route('course.module.practice', $routeParams)
                ->with('err', $e->getMessage());
        }
        $msg = $out['message'] ?? null;

        return redirect()
            ->route('course.module.practice', $routeParams)
            ->with($msg ? 'err' : 'ok', $msg ?? 'Стенд выделен. Откройте терминал по ссылке.');
    }

    public function check(Request $request): RedirectResponse
    {
        $learner = Learner::findOrFail(LearnerPreviewContext::learnerId());
        $resolved = $this->routeContext($request);
        $mid = $resolved['id'];
        $courseId = $resolved['courseId'];
        $routeParams = LearnerRoute::hub($courseId, $resolved['seq']);
        $out = PracticeLabService::make()->runCheck($learner, $courseId, $mid);
        $msg = $out['message'] ?? null;

        return redirect()
            ->route('course.module.practice', $routeParams)
            ->with($msg ? 'err' : 'ok', $msg ?? 'Проверка выполнена.');
    }

    public function accept(Request $request): RedirectResponse
    {
        $learner = Learner::findOrFail(LearnerPreviewContext::learnerId());
        $resolved = $this->routeContext($request);
        $mid = $resolved['id'];
        $courseId = $resolved['courseId'];
        $routeParams = LearnerRoute::hub($courseId, $resolved['seq']);
        try {
            PracticeLabService::make()->acceptPracticeResult($learner, $courseId, $mid);
        } catch (\Throwable $e) {
            return redirect()->route('course.module.practice', $routeParams)->with('err', $e->getMessage());
        }

        return redirect()->route('course.module.hub', $routeParams)->with('ok', 'Результат практики принят.');
    }

    public function finish(Request $request): RedirectResponse
    {
        $learner = Learner::findOrFail(LearnerPreviewContext::learnerId());
        $resolved = $this->routeContext($request);
        $mid = $resolved['id'];
        $courseId = $resolved['courseId'];
        $routeParams = LearnerRoute::hub($courseId, $resolved['seq']);
        PracticeLabService::make()->destroyLab($learner, $courseId, $mid);

        return redirect()->route('course.module.practice', $routeParams)->with('ok', 'Работа со стендом завершена.');
    }
}
