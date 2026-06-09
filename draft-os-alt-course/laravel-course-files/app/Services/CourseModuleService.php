<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseModuleContent;
use App\Services\CourseContentService;
use App\Support\CourseModuleMeta;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use App\Support\LearnerPreviewContext;

final class CourseModuleService
{
    /**
     * @return Collection<int, CourseModule>
     */
    public function orderedModulesForCourse(int $courseId): Collection
    {
        if ($courseId < 1 || ! Schema::hasTable('course_modules')) {
            return collect();
        }

        return CourseModule::query()
            ->where('course_id', $courseId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<int>
     */
    public function orderedModuleIdsForCourse(int $courseId): array
    {
        return $this->orderedModulesForCourse($courseId)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    public function findForCourse(int $courseId, int $courseModuleId): ?CourseModule
    {
        if ($courseId < 1 || $courseModuleId < 1) {
            return null;
        }

        return CourseModule::query()
            ->where('course_id', $courseId)
            ->where('id', $courseModuleId)
            ->first();
    }

    public function findOrFailForCourse(int $courseId, int $courseModuleId): CourseModule
    {
        $m = $this->findForCourse($courseId, $courseModuleId);
        if ($m === null) {
            throw (new ModelNotFoundException)->setModel(CourseModule::class, [$courseModuleId]);
        }

        return $m;
    }

    public function findBySequenceForCourse(int $courseId, int $sequence): ?CourseModule
    {
        if ($courseId < 1 || $sequence < 1) {
            return null;
        }

        return $this->orderedModulesForCourse($courseId)->get($sequence - 1);
    }

    /**
     * Модуль по порядковому номеру в курсе (1..N) или по legacy id в URL (старые закладки).
     */
    public function findOrFailForCourseRoute(int $courseId, int $moduleRoute): CourseModule
    {
        $m = $this->findBySequenceForCourse($courseId, $moduleRoute);
        if ($m === null) {
            $m = $this->findForCourse($courseId, $moduleRoute);
        }
        if ($m === null) {
            throw (new ModelNotFoundException)->setModel(CourseModule::class, [$moduleRoute]);
        }

        return $m;
    }

    /**
     * Порядковый номер модуля внутри курса (1..N), по sort/id.
     *
     * Важно: id модуля — глобальный auto-increment и не годится для UI нумерации.
     */
    public function sequenceForModule(CourseModule $module): int
    {
        if (! Schema::hasTable('course_modules')) {
            return 1;
        }

        $courseId = (int) $module->course_id;
        $sort = (int) ($module->sort ?? 0);
        $id = (int) ($module->id ?? 0);
        if ($courseId < 1 || $id < 1) {
            return 1;
        }

        $before = CourseModule::query()
            ->where('course_id', $courseId)
            ->where(function ($q) use ($sort, $id): void {
                $q->where('sort', '<', $sort)
                    ->orWhere(function ($q2) use ($sort, $id): void {
                        $q2->where('sort', '=', $sort)->where('id', '<', $id);
                    });
            })
            ->count();

        return max(1, (int) $before + 1);
    }

    /**
     * Метаданные для UI: заголовок/описание из БД, теория и прочее из конфига по content_source_index.
     *
     * @return array<string, mixed>
     */
    public function displayMeta(CourseModule $module): array
    {
        $course = $module->relationLoaded('course')
            ? $module->course
            : $module->loadMissing('course:id,slug')->course;
        $isLegacyAlt = $course instanceof Course && $course->isLegacyAltCourse();

        $idx = $module->content_source_index !== null && (int) $module->content_source_index > 0
            ? (int) $module->content_source_index
            : null;
        if ($isLegacyAlt && $idx !== null) {
            $base = CourseModuleMeta::resolved($idx);
            if (Schema::hasTable('course_module_contents')
                && CourseModuleContent::query()->where('course_module_id', $module->id)->exists()) {
                /** @var CourseContentService $content */
                $content = app(CourseContentService::class);
                $c = $content->contentForModule($module);
                $base['theory'] = (string) ($c['theory_markdown'] ?? '');
                $base['practice'] = (string) ($c['practice_markdown'] ?? '');
            }
        } else {
            $base = [
                'letter' => (string) ($module->letter ?? ''),
                'title' => $module->title,
                'summary' => $module->summary,
                'theory' => '',
                'practice' => '',
            ];

            /** @var CourseContentService $content */
            $content = app(CourseContentService::class);
            $c = $content->contentForModule($module);
            $base['theory'] = $c['theory_markdown'] ?? '';
            $base['practice'] = $c['practice_markdown'] ?? '';
        }

        $base['title'] = $module->title;
        $base['summary'] = $module->summary;
        if ($module->letter !== null && $module->letter !== '') {
            $base['letter'] = $module->letter;
        }

        return $base;
    }

    public function selectedCourseIsLegacyAlt(): bool
    {
        $courseId = LearnerPreviewContext::courseId();
        if ($courseId < 1 || ! Schema::hasTable('courses')) {
            return false;
        }
        $course = Course::query()->select(['id', 'slug'])->find($courseId);

        return $course instanceof Course && $course->isLegacyAltCourse();
    }

    public function moduleCountForCourse(int $courseId): int
    {
        $n = $this->orderedModulesForCourse($courseId)->count();

        return max(0, $n);
    }
}
