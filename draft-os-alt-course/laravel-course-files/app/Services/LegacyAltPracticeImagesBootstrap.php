<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseModulePracticeSetting;
use App\Models\PracticeImage;
use App\Support\LegacyAltPracticeImageCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Импорт системных образов Alt из config/practice_lab.php в practice_images
 * и привязка к модулям курса alt-os-features.
 */
final class LegacyAltPracticeImagesBootstrap
{
    public static function sync(): void
    {
        if (! Schema::hasTable('practice_images')) {
            return;
        }

        $entries = LegacyAltPracticeImageCatalog::entries();
        if ($entries === []) {
            return;
        }

        $recipeBootstrap = app(PracticeImageRecipeBootstrap::class);
        $imageIdsByModuleKey = [];

        DB::transaction(function () use ($entries, $recipeBootstrap, &$imageIdsByModuleKey): void {
            foreach ($entries as $entry) {
                $moduleKey = (int) $entry['module_key'];
                $tag = (string) $entry['docker_tag'];
                $slug = (string) $entry['slug'];

                $row = PracticeImage::query()->where('docker_tag', $tag)->first()
                    ?? PracticeImage::query()->where('slug', $slug)->first();

                $isNew = $row === null;
                if ($isNew) {
                    $row = PracticeImage::query()->create([
                        'title' => (string) $entry['title'],
                        'slug' => $slug,
                        'docker_tag' => $tag,
                        'description' => self::descriptionForModuleKey($moduleKey),
                        'base_template' => (string) $entry['template'],
                        'base_os' => 'alt',
                        'base_image_ref' => '',
                        'package_add' => [],
                        'package_remove' => [],
                        'features' => [],
                        'startup_script_text' => '',
                        'dockerfile_text' => '',
                        'check_script_text' => '',
                        'is_built' => true,
                        'last_build_status' => 'ok',
                        'last_built_at' => now(),
                    ]);
                    self::tryInitRecipeFromTemplate($recipeBootstrap, $row);
                } else {
                    $row->title = (string) $entry['title'];
                    $row->docker_tag = $tag;
                    if (trim((string) ($row->description ?? '')) === '') {
                        $row->description = self::descriptionForModuleKey($moduleKey);
                    }
                    if (trim((string) ($row->base_template ?? '')) === '') {
                        $row->base_template = (string) $entry['template'];
                    }
                    if (trim((string) ($row->base_os ?? '')) === '') {
                        $row->base_os = 'alt';
                    }
                    if (! $row->is_built) {
                        $row->is_built = true;
                        $row->last_build_status = 'ok';
                        $row->last_built_at = $row->last_built_at ?? now();
                    }
                    $row->save();
                }

                $imageIdsByModuleKey[$moduleKey] = (int) $row->id;
            }

            self::linkCourseModules($imageIdsByModuleKey);
        });
    }

    /**
     * @param  array<int, int>  $imageIdsByModuleKey
     */
    private static function linkCourseModules(array $imageIdsByModuleKey): void
    {
        if ($imageIdsByModuleKey === [] || ! Schema::hasTable('courses') || ! Schema::hasTable('course_modules')) {
            return;
        }
        if (! Schema::hasTable('course_module_practice_settings')) {
            return;
        }

        $course = Course::query()->where('slug', LegacyAltCourseContentBootstrap::SLUG)->first();
        if (! $course instanceof Course || ! $course->isLegacyAltCourse()) {
            return;
        }

        $modules = CourseModule::query()
            ->where('course_id', (int) $course->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        foreach ($modules as $cm) {
            $idx = $cm->effectiveContentIndex();
            $imgId = $imageIdsByModuleKey[$idx] ?? null;
            if ($imgId === null) {
                continue;
            }

            CourseModulePracticeSetting::query()->updateOrCreate(
                ['course_module_id' => (int) $cm->id],
                ['practice_image_id' => $imgId]
            );
        }

        if (Schema::hasColumn('courses', 'final_lab_practice_image_id')) {
            $finalId = $imageIdsByModuleKey[10] ?? null;
            if ($finalId !== null && (int) ($course->final_lab_practice_image_id ?? 0) !== $finalId) {
                $course->final_lab_practice_image_id = $finalId;
                $course->save();
            }
        }
    }

    private static function tryInitRecipeFromTemplate(PracticeImageRecipeBootstrap $recipeBootstrap, PracticeImage $row): void
    {
        try {
            $recipeBootstrap->initFromTemplate($row);
        } catch (Throwable $e) {
            Log::debug('LegacyAltPracticeImagesBootstrap: recipe init skipped for '.$row->slug.': '.$e->getMessage());
        }
    }

    private static function descriptionForModuleKey(int $moduleKey): string
    {
        if ($moduleKey === 10) {
            return 'Системный образ финальной лаборатории курса «ОС Альт» (config/practice_lab.php).';
        }

        return 'Системный образ практики модуля '.$moduleKey.' курса «ОС Альт» (config/practice_lab.php).';
    }
}
