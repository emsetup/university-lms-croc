<?php

namespace App\Http\Controllers;

use App\Models\CourseModule;
use App\Models\CourseModulePracticeSetting;
use App\Models\CourseSection;
use App\Models\CourseSectionSetting;
use App\Models\ModuleProgress;
use App\Models\PracticeImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AdminCourseSettingsController extends Controller
{
    public function modulesIndex(Request $request): View
    {
                $courseId = (int) session('admin_course_id');
        $modules = CourseModule::query()
            ->where('course_id', $courseId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return view('admin.course-modules-index', [            'modules' => $modules,
        ]);
    }

    public function storeModule(Request $request): RedirectResponse
    {
                $courseId = (int) session('admin_course_id');
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'summary' => 'nullable|string|max:5000',
            'letter' => 'nullable|string|max:8',
            'content_source_index' => 'nullable|integer|min:1|max:99',
        ]);
        $maxSort = (int) CourseModule::query()->where('course_id', $courseId)->max('sort');
        $mod = CourseModule::query()->create([
            'course_id' => $courseId,
            'sort' => $maxSort > 0 ? $maxSort + 10 : 10,
            'title' => $data['title'],
            'summary' => (string) ($data['summary'] ?? ''),
            'letter' => isset($data['letter']) && $data['letter'] !== '' ? (string) $data['letter'] : null,
            'content_source_index' => isset($data['content_source_index']) ? (int) $data['content_source_index'] : null,
        ]);
        $template = CourseModule::query()
            ->where('course_id', $courseId)
            ->where('id', '!=', $mod->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->first();
        if ($template !== null) {
            $this->cloneSectionsFromModule($courseId, $template, $mod);
        } else {
            $this->seedDefaultSectionsForModule($courseId, $mod);
        }
        app(\App\Services\CourseSectionService::class)->clearCache();

        return redirect()
            ->route('admin.course.settings')
            ->with('ok', 'Модуль добавлен. Настройте разделы при необходимости.');
    }

    public function updateModule(Request $request, CourseModule $courseModule): RedirectResponse
    {
                $this->assertModuleCourse($courseModule);
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'summary' => 'nullable|string|max:5000',
            'letter' => 'nullable|string|max:8',
            'content_source_index' => 'nullable|integer|min:1|max:99',
        ]);
        $courseModule->title = $data['title'];
        $courseModule->summary = (string) ($data['summary'] ?? '');
        $courseModule->letter = isset($data['letter']) && $data['letter'] !== '' ? (string) $data['letter'] : null;
        $courseModule->content_source_index = isset($data['content_source_index']) ? (int) $data['content_source_index'] : null;
        $courseModule->save();
        app(\App\Services\CourseSectionService::class)->clearCache();

        return redirect()
            ->route('admin.course.settings')
            ->with('ok', 'Модуль обновлён.');
    }

    public function destroyModule(Request $request, CourseModule $courseModule): RedirectResponse
    {
                $this->assertModuleCourse($courseModule);
        if (ModuleProgress::query()->where('course_module_id', $courseModule->id)->exists()) {
            return redirect()
                ->route('admin.course.settings')
                ->with('err', 'Нельзя удалить модуль: есть сохранённый прогресс обучающихся. Сбросьте прогресс или отключите модуль.');
        }
        $courseModule->delete();
        app(\App\Services\CourseSectionService::class)->clearCache();

        return redirect()
            ->route('admin.course.settings')
            ->with('ok', 'Модуль удалён.');
    }

    public function reorderModules(Request $request): RedirectResponse
    {
                $courseId = (int) session('admin_course_id');
        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|distinct',
        ]);
        $ids = array_values(array_map('intval', $data['order']));
        $owned = CourseModule::query()->where('course_id', $courseId)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $sortedIds = $ids;
        sort($sortedIds);
        if ($owned !== $sortedIds || $owned === []) {
            return redirect()
                ->route('admin.course.settings')
                ->with('err', 'Некорректный порядок модулей.');
        }
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $i => $moduleId) {
                CourseModule::query()->where('id', $moduleId)->update(['sort' => ($i + 1) * 10]);
            }
        });
        app(\App\Services\CourseSectionService::class)->clearCache();

        return redirect()
            ->route('admin.course.settings')
            ->with('ok', 'Порядок модулей сохранён.');
    }

    public function moduleSections(Request $request, CourseModule $courseModule): View
    {
        $this->assertModuleCourse($courseModule);
                $sections = CourseSection::query()
            ->where('course_module_id', $courseModule->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->with('sectionSettings')
            ->get();

        return view('admin.course-module-sections', [            'courseModule' => $courseModule,
            'sections' => $sections,
            'types' => CourseSection::typesList(),
        ]);
    }

    public function modulePractice(Request $request, CourseModule $courseModule): View
    {
        $this->assertModuleCourse($courseModule);
                $setting = CourseModulePracticeSetting::query()->firstOrNew(['course_module_id' => $courseModule->id]);
        $images = PracticeImage::query()
            ->where('is_built', true)
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        return view('admin.course-module-practice', [            'courseModule' => $courseModule,
            'setting' => $setting,
            'images' => $images,
        ]);
    }

    public function saveModulePractice(Request $request, CourseModule $courseModule): RedirectResponse
    {
        $this->assertModuleCourse($courseModule);
                $data = $request->validate([
            'practice_image_id' => 'nullable|integer|min:1',
            'daemon_image_key_override' => 'nullable|integer|min:1|max:99',
        ]);

        $practiceImageId = isset($data['practice_image_id']) ? (int) $data['practice_image_id'] : null;
        if ($practiceImageId !== null && ! PracticeImage::query()->where('id', $practiceImageId)->where('is_built', true)->exists()) {
            return redirect()
                ->route('admin.course.module.practice', ['courseModule' => $courseModule->id])
                ->with('err', 'Выбранный образ не найден.');
        }

        CourseModulePracticeSetting::query()->updateOrCreate(
            ['course_module_id' => $courseModule->id],
            [
                'practice_image_id' => $practiceImageId,
                'daemon_image_key_override' => isset($data['daemon_image_key_override']) ? (int) $data['daemon_image_key_override'] : null,
            ]
        );

        return redirect()
            ->route('admin.course.module.practice', ['courseModule' => $courseModule->id])
            ->with('ok', 'Настройки практики сохранены.');
    }

    public function storeSection(Request $request, CourseModule $courseModule): RedirectResponse
    {
                $this->assertModuleCourse($courseModule);
        $courseId = (int) session('admin_course_id');
        $data = $request->validate([
            'type' => 'required|in:text,quiz,practice,exam',
            'title' => 'required|string|max:200',
        ]);
        if (CourseSection::query()->where('course_module_id', $courseModule->id)->where('type', $data['type'])->exists()) {
            return redirect()
                ->route('admin.course.module.sections', ['courseModule' => $courseModule->id])
                ->with('err', 'Тип раздела «'.$data['type'].'» уже есть у этого модуля.');
        }
        $maxSort = (int) CourseSection::query()->where('course_module_id', $courseModule->id)->max('sort');

        $sec = CourseSection::query()->create([
            'course_id' => $courseId,
            'course_module_id' => $courseModule->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'sort' => $maxSort + 10,
            'is_enabled' => true,
        ]);
        CourseSectionSetting::query()->create([
            'course_section_id' => $sec->id,
            'settings' => self::defaultSettingsForType($data['type']),
        ]);
        app(\App\Services\CourseSectionService::class)->clearCache();

        return redirect()
            ->route('admin.course.module.sections', ['courseModule' => $courseModule->id])
            ->with('ok', 'Раздел добавлен.');
    }

    public function updateSection(Request $request, CourseModule $courseModule, CourseSection $section): RedirectResponse
    {
                $this->assertSectionInModule($courseModule, $section);
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'is_enabled' => 'nullable|in:0,1',
        ]);
        $section->title = $data['title'];
        if ($request->has('is_enabled')) {
            $section->is_enabled = (string) $request->input('is_enabled') === '1';
        }
        $section->save();
        app(\App\Services\CourseSectionService::class)->clearCache();

        return redirect()
            ->route('admin.course.module.sections', ['courseModule' => $courseModule->id])
            ->with('ok', 'Раздел обновлён.');
    }

    public function destroySection(Request $request, CourseModule $courseModule, CourseSection $section): RedirectResponse
    {
                $this->assertSectionInModule($courseModule, $section);
        $section->delete();
        app(\App\Services\CourseSectionService::class)->clearCache();

        return redirect()
            ->route('admin.course.module.sections', ['courseModule' => $courseModule->id])
            ->with('ok', 'Раздел удалён.');
    }

    public function reorderSections(Request $request, CourseModule $courseModule): RedirectResponse
    {
                $this->assertModuleCourse($courseModule);
        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|distinct',
        ]);
        $ids = array_values(array_map('intval', $data['order']));
        $owned = CourseSection::query()->where('course_module_id', $courseModule->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $sortedIds = $ids;
        sort($sortedIds);
        if ($owned !== $sortedIds || $owned === []) {
            return redirect()
                ->route('admin.course.module.sections', ['courseModule' => $courseModule->id])
                ->with('err', 'Некорректный порядок разделов.');
        }
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $i => $sectionId) {
                CourseSection::query()->where('id', $sectionId)->update(['sort' => ($i + 1) * 10]);
            }
        });
        app(\App\Services\CourseSectionService::class)->clearCache();

        return redirect()
            ->route('admin.course.module.sections', ['courseModule' => $courseModule->id])
            ->with('ok', 'Порядок сохранён.');
    }

    public function editSettings(Request $request, CourseModule $courseModule, CourseSection $section): View
    {
                $this->assertSectionInModule($courseModule, $section);
        $section->loadMissing('sectionSettings');
        $settings = $section->sectionSettings?->settings;
        if (! is_array($settings)) {
            $settings = self::defaultSettingsForType($section->type);
        }

        return view('admin.course-section-settings', [            'courseModule' => $courseModule,
            'section' => $section,
            'settings' => $settings,
        ]);
    }

    public function saveSettings(Request $request, CourseModule $courseModule, CourseSection $section): RedirectResponse
    {
                $this->assertSectionInModule($courseModule, $section);
        $merged = match ($section->type) {
            CourseSection::TYPE_TEXT => $request->validate([
                'min_read_seconds' => 'nullable|integer|min:0|max:86400',
                'time_limit_minutes' => 'nullable|integer|min:0|max:600',
            ]),
            CourseSection::TYPE_QUIZ => $request->validate([
                'time_limit_minutes' => 'nullable|integer|min:1|max:600',
                'attempt_limit' => 'nullable|integer|min:1|max:50',
                'pass_percent' => 'required|integer|min:1|max:100',
                'shuffle' => 'sometimes|boolean',
                'penalty_attempt_2' => 'nullable|integer|min:0|max:100',
                'penalty_attempt_3' => 'nullable|integer|min:0|max:100',
                'penalty_attempt_4' => 'nullable|integer|min:0|max:100',
            ]),
            CourseSection::TYPE_PRACTICE => $request->validate([
                'attempt_limit' => 'nullable|integer|min:1|max:50',
                'time_limit_minutes' => 'nullable|integer|min:0|max:10080',
            ]),
            CourseSection::TYPE_EXAM => $request->validate([
                'time_limit_minutes' => 'nullable|integer|min:1|max:600',
                'attempt_limit' => 'required|integer|min:1|max:20',
                'pass_percent' => 'required|integer|min:1|max:100',
                'one_by_one' => 'sometimes|boolean',
                'breakdown_visible_minutes' => 'nullable|integer|min:0|max:10080',
                'penalty_attempt_2' => 'nullable|integer|min:0|max:100',
                'penalty_attempt_3' => 'nullable|integer|min:0|max:100',
                'penalty_attempt_4' => 'nullable|integer|min:0|max:100',
            ]),
            default => [],
        };

        if ($section->type === CourseSection::TYPE_QUIZ || $section->type === CourseSection::TYPE_EXAM) {
            $penalties = [];
            foreach ([2, 3, 4] as $n) {
                $k = 'penalty_attempt_'.$n;
                if (isset($merged[$k]) && $merged[$k] !== null && $merged[$k] !== '') {
                    $penalties[(string) $n] = (int) $merged[$k];
                }
                unset($merged[$k]);
            }
            $merged['penalties'] = $penalties;
        }

        if ($section->type === CourseSection::TYPE_QUIZ) {
            $merged['shuffle'] = $request->boolean('shuffle');
            if (empty($merged['attempt_limit'])) {
                $merged['attempt_limit'] = null;
            }
        }
        if ($section->type === CourseSection::TYPE_EXAM) {
            $merged['one_by_one'] = $request->boolean('one_by_one');
        }
        if ($section->type === CourseSection::TYPE_TEXT) {
            if (empty($merged['time_limit_minutes'])) {
                $merged['time_limit_minutes'] = null;
            }
        }

        $row = CourseSectionSetting::query()->firstOrNew(['course_section_id' => $section->id]);
        $row->settings = $merged;
        $row->save();
        app(\App\Services\CourseSectionService::class)->clearCache();

        return redirect()
            ->route('admin.course.module.section.settings', [
                'courseModule' => $courseModule->id,
                'section' => $section->id,
            ])
            ->with('ok', 'Настройки сохранены.');
    }

    private function assertModuleCourse(CourseModule $courseModule): void
    {
        $courseId = (int) session('admin_course_id');
        abort_unless($courseId > 0 && (int) $courseModule->course_id === $courseId, 403);
    }

    private function assertSectionInModule(CourseModule $courseModule, CourseSection $section): void
    {
        $courseId = (int) session('admin_course_id');
        abort_unless(
            $courseId > 0
            && (int) $section->course_id === $courseId
            && (int) $section->course_module_id === (int) $courseModule->id,
            403
        );
    }

    private function cloneSectionsFromModule(int $courseId, CourseModule $from, CourseModule $to): void
    {
        $from->load(['sections.sectionSettings']);
        foreach ($from->sections as $sec) {
            $n = CourseSection::query()->create([
                'course_id' => $courseId,
                'course_module_id' => $to->id,
                'type' => $sec->type,
                'title' => $sec->title,
                'sort' => $sec->sort,
                'is_enabled' => $sec->is_enabled,
            ]);
            $st = $sec->sectionSettings;
            CourseSectionSetting::query()->create([
                'course_section_id' => $n->id,
                'settings' => is_array($st?->settings) ? $st->settings : self::defaultSettingsForType($sec->type),
            ]);
        }
    }

    private function seedDefaultSectionsForModule(int $courseId, CourseModule $module): void
    {
        $sort = 10;
        foreach ([
            CourseSection::TYPE_TEXT => 'Теория',
            CourseSection::TYPE_QUIZ => 'Тест по теории',
            CourseSection::TYPE_PRACTICE => 'Практика',
            CourseSection::TYPE_EXAM => 'Итоговый тест',
        ] as $type => $title) {
            $sec = CourseSection::query()->create([
                'course_id' => $courseId,
                'course_module_id' => $module->id,
                'type' => $type,
                'title' => $title,
                'sort' => $sort,
                'is_enabled' => true,
            ]);
            CourseSectionSetting::query()->create([
                'course_section_id' => $sec->id,
                'settings' => self::defaultSettingsForType($type),
            ]);
            $sort += 10;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultSettingsForType(string $type): array
    {
        return match ($type) {
            CourseSection::TYPE_TEXT => [
                'min_read_seconds' => 0,
                'time_limit_minutes' => null,
            ],
            CourseSection::TYPE_QUIZ => [
                'time_limit_minutes' => 30,
                'attempt_limit' => null,
                'pass_percent' => 70,
                'penalties' => ['2' => 10],
                'shuffle' => false,
            ],
            CourseSection::TYPE_PRACTICE => [
                'attempt_limit' => null,
                'time_limit_minutes' => null,
            ],
            CourseSection::TYPE_EXAM => [
                'time_limit_minutes' => 60,
                'attempt_limit' => 2,
                'pass_percent' => 70,
                'penalties' => ['2' => 10],
                'one_by_one' => true,
                'breakdown_visible_minutes' => 30,
            ],
            default => [],
        };
    }
}
