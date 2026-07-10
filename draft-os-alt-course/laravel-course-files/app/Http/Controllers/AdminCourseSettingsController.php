<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AdminQuizController;
use App\Models\Course;
use App\Models\ContentViewAudienceRule;
use App\Models\CourseLearnerGroup;
use App\Models\CourseModule;
use App\Models\Learner;
use App\Models\CourseModuleContent;
use App\Models\CourseModulePracticeSetting;
use App\Models\CourseQuizQuestion;
use App\Models\CourseSection;
use App\Models\CourseSectionSetting;
use App\Models\ModuleProgress;
use App\Models\PracticeImage;
use App\Models\CourseQuizBank;
use App\Services\CourseContentService;
use App\Services\CourseChangeLogService;
use App\Services\CourseSectionService;
use App\Services\LearnerContentVisibilityService;
use App\Services\SurveyQuickLinkService;
use App\Services\LegacyAltPracticeImagesBootstrap;
use App\Services\PortalStaffAccess;
use App\Support\AdminCourseContentInspector;
use App\Support\CourseModuleMeta;
use App\Support\CourseQuizBankLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class AdminCourseSettingsController extends Controller
{
    public function __construct(private CourseChangeLogService $changeLog) {}

    public function courseSettings(Request $request, Course $adminCourse): View
    {
        $courseId = (int) $adminCourse->id;
        abort_unless($courseId > 0, 404);
        $gate = app(PortalStaffAccess::class);

        $t = (string) $request->query('tab', '');
        $settingsTab = match ($t) {
            'kurs' => 'kurs',
            'sertifikat' => 'sertifikat',
            'istoriya' => 'istoriya',
            'soavtory' => 'soavtory',
            'gruppy' => 'gruppy',
            default => 'moduli',
        };

        if ($settingsTab === 'soavtory') {
            $gate->assertCanManageCollaborators($courseId);
        } elseif ($settingsTab === 'gruppy') {
            $gate->assertCanEditCourseMeta($courseId);
        } elseif ($settingsTab === 'moduli') {
            $gate->assertCanAccessCourseModulesTab($courseId);
        } else {
            $gate->assertCanEditCourseMeta($courseId);
        }

        $course = $adminCourse;
        $course->loadMissing('finalLabPracticeImage:id,title,docker_tag');

        if ($course->isLegacyAltCourse()) {
            LegacyAltPracticeImagesBootstrap::sync();
            $course->loadMissing('finalLabPracticeImage:id,title,docker_tag');
        }

        $finalQuestionCount = 0;
        if ($course->isLegacyAltCourse()) {
            $path = config_path('snippets/final_lab_questions.json');
            $finalQuestionCount = count(CourseQuizBankLoader::loadJsonBank($path));
        } elseif (Schema::hasTable('course_quiz_banks')) {
            $bank = app(CourseContentService::class)->quizBankFor($course, null, 'final_lab');
            if ($bank) {
                $finalQuestionCount = Schema::hasTable('course_quiz_questions')
                    ? (int) CourseQuizQuestion::query()->where('quiz_bank_id', (int) $bank->id)->count()
                    : 0;
            }
        }

        $builtImages = app(PortalStaffAccess::class)
            ->scopePracticeImagesForStaff(
                PracticeImage::query()->where('is_built', true)
            )
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        $status = 'draft';
        if ($course->is_archived) {
            $status = 'archived';
        } elseif ($course->is_published) {
            $status = 'published';
        }

        $modules = CourseModule::query()
            ->where('course_id', $courseId)
            ->with([
                'sections' => static function ($q): void {
                    $q->orderBy('sort')->orderBy('id');
                },
                'practiceSetting.practiceImage:id,title,docker_tag',
            ])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $accessibleModuleIds = $gate->accessibleModulesForCourse($courseId)->flip()->all();
        if ($gate->usesGrantBasedAccess($courseId)) {
            $modules = $modules->filter(fn (CourseModule $m) => isset($accessibleModuleIds[(int) $m->id]))->values();
        }

        $changeLogEntries = $settingsTab === 'istoriya'
            ? $this->changeLog->entriesForCourse($courseId)
            : collect();

        $courseCreator = $this->changeLog->creatorForCourse($course);

        $collaboratorPayload = [];
        if ($settingsTab === 'soavtory') {
            $collabSvc = app(\App\Services\CourseCollaboratorService::class);
            $collaboratorPayload = [
                'collaborators' => $collabSvc->collaboratorsForCourse($course),
                'grantsByStaff' => $collabSvc->grantsGroupedByStaff($course),
                'grantTree' => $collabSvc->courseGrantTree($course),
                'collaboratorLimit' => $collabSvc->collaboratorLimit(),
                'collaboratorCount' => $collabSvc->countCollaboratorsWithEdit($course),
            ];
        }

        $groupsPayload = [];
        if ($settingsTab === 'gruppy') {
            $enrolledIds = $course->enrollments()->pluck('learner_id')->map(fn ($id) => (int) $id)->all();
            $groupsPayload = [
                'courseLearnerGroups' => CourseLearnerGroup::query()
                    ->where('course_id', $courseId)
                    ->with('members:id,email,sso_display_name')
                    ->withCount('members')
                    ->orderBy('sort')
                    ->orderBy('name')
                    ->get(),
                'courseEnrolledLearners' => $enrolledIds !== []
                    ? Learner::query()->whereIn('id', $enrolledIds)->orderBy('email')->get(['id', 'email', 'sso_display_name'])
                    : collect(),
            ];
        }

        return view('admin.course-settings', array_merge([
            'course' => $course,
            'courseStatus' => $status,
            'finalQuestionCount' => $finalQuestionCount,
            'builtImages' => $builtImages,
            'settingsTab' => $settingsTab,
            'modules' => $modules,
            'adminKey' => (string) $request->query('key', ''),
            'changeLogEntries' => $changeLogEntries,
            'courseCreator' => $courseCreator,
            'changeLogService' => $this->changeLog,
            'canViewStaffProfiles' => $gate->canManageStaff(),
            'canManageCollaborators' => $gate->canManageCollaborators($courseId),
            'canEditCourseMeta' => $gate->canEditCourseMeta($courseId),
            'canEditCourseStructure' => $gate->canEditCourseStructure($courseId),
        ], $collaboratorPayload, $groupsPayload));
    }

    public function saveCourseSettings(Request $request): RedirectResponse
    {
        $courseId = (int) session('admin_course_id');
        abort_unless($courseId > 0, 404);
        app(PortalStaffAccess::class)->assertCanEditCourseMeta($courseId);

        /** @var Course $course */
        $course = Course::query()->findOrFail($courseId);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:courses,slug,'.$course->id],
            'summary' => ['nullable', 'string', 'max:5000'],
            'course_status' => ['required', 'in:draft,published,archived'],
            'default_attempt_limit' => ['nullable', 'integer', 'min:1', 'max:99'],
            'default_quiz_time_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'default_pass_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'final_lab_enabled' => ['sometimes', 'boolean'],
            'difficulty_flags_enabled' => ['sometimes', 'boolean'],
            'unlock_all_modules' => ['sometimes', 'boolean'],
            'show_module_progress' => ['sometimes', 'boolean'],
            'assessment_enabled' => ['sometimes', 'boolean'],
            'meta_includes_dashboard_extras' => ['sometimes', 'boolean'],
            'audience_plaque_enabled' => ['sometimes', 'boolean'],
            'audience_plaque_kicker' => ['nullable', 'string', 'max:80'],
            'audience_plaque_title' => ['nullable', 'string', 'max:200'],
            'audience_plaque_teaser' => ['nullable', 'string', 'max:2000'],
            'audience_plaque_body' => ['nullable', 'string', 'max:20000'],
            'final_lab_practice_image_id' => ['nullable', 'integer', 'min:1'],
            'certificate_enabled' => ['sometimes', 'boolean'],
            'certificate_title' => ['nullable', 'string', 'max:200'],
            'certificate_body' => ['nullable', 'string', 'max:500'],
            'certificate_tiers' => ['nullable', 'string', 'max:20000'],
            'redirect_tab' => ['nullable', 'string', 'in:kurs,sertifikat'],
        ], [
            'slug.regex' => 'Slug: только латиница/цифры и дефис.',
        ]);

        $slug = strtolower((string) $data['slug']);
        $course->slug = $slug;
        $course->title = (string) $data['title'];
        $course->summary = (string) ($data['summary'] ?? '');

        $st = (string) $data['course_status'];
        $course->is_archived = $st === 'archived';
        $course->is_published = $st === 'published';

        $course->default_attempt_limit = isset($data['default_attempt_limit']) ? (int) $data['default_attempt_limit'] : null;
        $course->default_quiz_time_minutes = isset($data['default_quiz_time_minutes']) ? (int) $data['default_quiz_time_minutes'] : null;
        $course->default_pass_percent = isset($data['default_pass_percent']) ? (int) $data['default_pass_percent'] : null;

        $redirTab = (string) ($data['redirect_tab'] ?? 'kurs');
        if ($redirTab === 'kurs') {
            $course->final_lab_enabled = $request->boolean('final_lab_enabled');
            if (Schema::hasColumn('courses', 'difficulty_flags_enabled')) {
                $course->difficulty_flags_enabled = $request->boolean('difficulty_flags_enabled');
            }
            if (Schema::hasColumn('courses', 'unlock_all_modules')) {
                $course->unlock_all_modules = $request->boolean('unlock_all_modules');
            }
            if (Schema::hasColumn('courses', 'show_module_progress')) {
                $course->show_module_progress = $request->boolean('show_module_progress');
            }
            if (Schema::hasColumn('courses', 'assessment_enabled')) {
                $course->assessment_enabled = $request->boolean('assessment_enabled');
            }
            if (Schema::hasColumn('courses', 'certificate_enabled') && $request->boolean('meta_includes_dashboard_extras')) {
                $course->certificate_enabled = $request->boolean('certificate_enabled');
            }
            if (Schema::hasColumn('courses', 'audience_plaque_enabled')) {
                $course->audience_plaque_enabled = $request->boolean('audience_plaque_enabled');
                $course->audience_plaque_kicker = isset($data['audience_plaque_kicker'])
                    ? trim((string) $data['audience_plaque_kicker']) ?: null
                    : null;
                $course->audience_plaque_title = isset($data['audience_plaque_title'])
                    ? trim((string) $data['audience_plaque_title']) ?: null
                    : null;
                $course->audience_plaque_teaser = isset($data['audience_plaque_teaser'])
                    ? trim((string) $data['audience_plaque_teaser']) ?: null
                    : null;
                $course->audience_plaque_body = isset($data['audience_plaque_body'])
                    ? trim((string) $data['audience_plaque_body']) ?: null
                    : null;
            }

            $imgId = isset($data['final_lab_practice_image_id']) ? (int) $data['final_lab_practice_image_id'] : null;
            if ($imgId !== null && $imgId > 0) {
                if (! PracticeImage::query()->where('id', $imgId)->where('is_built', true)->exists()) {
                    return back()->withInput()->with('err', 'Выбранный Docker-образ не найден или ещё не собран.');
                }
                app(PortalStaffAccess::class)->assertCanAssignPracticeImageToCourse($imgId, (int) $course->id);
                $course->final_lab_practice_image_id = $imgId;
            } else {
                $course->final_lab_practice_image_id = null;
            }
        }

        if (Schema::hasColumn('courses', 'certificate_enabled')
            && ($request->has('certificate_tiers') || $request->has('certificate_enabled'))) {
            $certErr = $this->applyCertificateSettingsFromRequest($course, $request, $data);
            if ($certErr !== null) {
                return $certErr;
            }
        }

        $this->changeLog->logCourseDirty($course);
        $course->save();

        if ((int) session('admin_course_id', 0) === (int) $course->id) {
            session([
                'admin_course_title' => $course->title,
                'admin_course_slug' => $course->slug,
            ]);
        }

        return redirect()
            ->route('admin.course.settings', array_merge($this->adminCourseRouteParams(), ['tab' => $redirTab]))
            ->with('ok', 'Настройки курса сохранены.');
    }

    /**
     * @return array<string, string>
     */
    private function modulesListQuery(Request $request): array
    {
        $k = (string) $request->query('key', '');

        return $k !== '' ? ['key' => $k] : [];
    }

    private function redirectToCourseSettings(Request $request, ?string $fragment = null): RedirectResponse
    {
        $params = array_merge($this->adminCourseRouteParams(), $this->modulesListQuery($request));
        $url = route('admin.course.settings', $params);
        if ($fragment !== null && $fragment !== '') {
            $url .= '#'.ltrim($fragment, '#');
        }

        return redirect()->to($url);
    }

    public function storeModule(Request $request): RedirectResponse
    {
        $courseId = (int) session('admin_course_id');
        abort_unless($courseId > 0, 404);
        app(PortalStaffAccess::class)->assertCanEditCourseStructure($courseId);
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
        $this->changeLog->logModuleCreated($courseId, (string) $mod->title, (int) $mod->id);

        return $this->redirectToCourseSettings($request, 'ap-mod-'.$mod->id)
            ->with('ok', 'Модуль добавлен. Настройте разделы при необходимости.');
    }

    public function updateModule(Request $request, Course $adminCourse, CourseModule $courseModule): RedirectResponse
    {
        $this->assertModuleCourse($courseModule);
        $gate = app(PortalStaffAccess::class);
        abort_unless(
            $gate->canEditCourseMeta((int) $courseModule->course_id)
            || $gate->canEditModuleContent((int) $courseModule->id),
            403
        );
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
        $moduleChanges = $this->changeLog->describeModelDirty($courseModule, [
            'title' => 'Название',
            'summary' => 'Описание',
            'letter' => 'Буква',
            'content_source_index' => 'Пакет контента №',
        ]);
        $courseModule->save();
        app(\App\Services\CourseSectionService::class)->clearCache();
        $this->changeLog->logModuleUpdated(
            (int) $courseModule->course_id,
            (string) $courseModule->title,
            (int) $courseModule->id,
            $moduleChanges,
        );

        return $this->redirectToCourseSettings($request, 'ap-mod-'.$courseModule->id)
            ->with('ok', 'Модуль обновлён.');
    }

    public function destroyModule(Request $request, Course $adminCourse, CourseModule $courseModule): RedirectResponse
    {
        $this->assertModuleCourse($courseModule);
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $courseModule->course_id);
        if (ModuleProgress::query()->where('course_module_id', $courseModule->id)->exists()) {
            return $this->redirectToCourseSettings($request)
                ->with('err', 'Нельзя удалить модуль: есть сохранённый прогресс обучающихся. Сбросьте прогресс или отключите модуль.');
        }
        $moduleTitle = (string) $courseModule->title;
        $moduleId = (int) $courseModule->id;
        $courseModule->delete();
        app(\App\Services\CourseSectionService::class)->clearCache();
        $this->changeLog->logModuleDeleted((int) session('admin_course_id'), $moduleTitle, $moduleId);

        return $this->redirectToCourseSettings($request)
            ->with('ok', 'Модуль удалён.');
    }

    public function reorderModules(Request $request): RedirectResponse
    {
        $routeCourse = $request->route('adminCourse');
        $courseId = $routeCourse instanceof Course
            ? (int) $routeCourse->id
            : (int) session('admin_course_id');
        app(PortalStaffAccess::class)->assertCanEditCourseMeta($courseId);
        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|distinct',
        ]);
        $ids = array_values(array_map('intval', $data['order']));
        $owned = CourseModule::query()->where('course_id', $courseId)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $sortedIds = $ids;
        sort($sortedIds);
        if ($owned !== $sortedIds || $owned === []) {
            return $this->redirectToCourseSettings($request)
                ->with('err', 'Некорректный порядок модулей.');
        }
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $i => $moduleId) {
                CourseModule::query()->where('id', $moduleId)->update(['sort' => ($i + 1) * 10]);
            }
        });
        app(\App\Services\CourseSectionService::class)->clearCache();
        $this->changeLog->logModulesReordered($courseId, count($ids));

        return $this->redirectToCourseSettings($request)
            ->with('ok', 'Порядок модулей сохранён.');
    }

    public function moduleSections(Request $request, Course $adminCourse, CourseModule $courseModule): RedirectResponse
    {
        $this->assertModuleCourse($courseModule);

        return $this->redirectToCourseSettings($request, 'ap-mod-'.$courseModule->id);
    }

    public function modulePractice(Request $request, Course $adminCourse, CourseModule $courseModule): View
    {
        $this->assertModuleCourse($courseModule);
        $adminKey = (string) $request->query('key', '');
        $setting = CourseModulePracticeSetting::query()->firstOrNew(['course_module_id' => $courseModule->id]);
        $images = app(PortalStaffAccess::class)
            ->scopePracticeImagesForStaff(
                PracticeImage::query()->where('is_built', true)
            )
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        return view('admin.course-module-practice', [
            'adminKey' => $adminKey,
            'courseModule' => $courseModule,
            'setting' => $setting,
            'images' => $images,
        ]);
    }

    public function saveModulePractice(Request $request, Course $adminCourse, CourseModule $courseModule): RedirectResponse
    {
        $this->assertModuleCourse($courseModule);
        app(PortalStaffAccess::class)->assertCanEditModuleContent((int) $courseModule->id);
        $adminKey = (string) $request->query('key', '');
        $data = $request->validate([
            'practice_image_id' => 'nullable|integer|min:1',
            'daemon_image_key_override' => 'nullable|integer|min:1|max:99',
        ]);

        $practiceImageId = isset($data['practice_image_id']) ? (int) $data['practice_image_id'] : null;
        if ($practiceImageId !== null && ! PracticeImage::query()->where('id', $practiceImageId)->where('is_built', true)->exists()) {
            return redirect()
                ->route('admin.course.module.practice', array_merge($this->adminCourseRouteParams(), ['courseModule' => $courseModule->id, 'key' => $adminKey]))
                ->with('err', 'Выбранный образ не найден.');
        }
        if ($practiceImageId !== null && $practiceImageId > 0) {
            app(PortalStaffAccess::class)->assertCanAssignPracticeImageToCourse(
                $practiceImageId,
                (int) $courseModule->course_id
            );
        }

        CourseModulePracticeSetting::query()->updateOrCreate(
            ['course_module_id' => $courseModule->id],
            [
                'practice_image_id' => $practiceImageId,
                'daemon_image_key_override' => isset($data['daemon_image_key_override']) ? (int) $data['daemon_image_key_override'] : null,
            ]
        );
        $this->changeLog->logModulePracticeSaved(
            (int) $courseModule->course_id,
            (int) $courseModule->id,
            $practiceImageId,
        );

        return redirect()
            ->route('admin.course.module.practice', array_merge($this->adminCourseRouteParams(), ['courseModule' => $courseModule->id, 'key' => $adminKey]))
            ->with('ok', 'Настройки практики сохранены.');
    }

    public function storeSection(Request $request, Course $adminCourse, CourseModule $courseModule): RedirectResponse
    {
        $this->assertModuleCourse($courseModule);
        app(PortalStaffAccess::class)->assertCanEditModuleContent((int) $courseModule->id);
        $courseId = (int) session('admin_course_id');
        $data = $request->validate([
            'type' => 'required|in:text,quiz,practice,exam,survey',
            'title' => 'required|string|max:200',
        ]);
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
        $this->changeLog->logSectionCreated(
            $courseId,
            (string) $sec->title,
            (string) $sec->type,
            (int) $sec->id,
            (int) $courseModule->id,
        );

        return $this->redirectToCourseSettings($request, 'ap-mod-'.$courseModule->id)
            ->with('ok', 'Раздел добавлен.');
    }

    public function updateSection(Request $request, Course $adminCourse, CourseModule $courseModule, CourseSection $section): RedirectResponse
    {
        $this->assertSectionInModule($courseModule, $section);
        app(PortalStaffAccess::class)->assertCanEditSection((int) $section->id);
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'is_enabled' => 'nullable|in:0,1',
        ]);
        $section->title = $data['title'];
        if ($request->has('is_enabled')) {
            $section->is_enabled = (string) $request->input('is_enabled') === '1';
        }
        $sectionChanges = $this->changeLog->describeModelDirty($section, [
            'title' => 'Название',
            'is_enabled' => 'Включён',
        ]);
        $section->save();
        app(\App\Services\CourseSectionService::class)->clearCache();
        $this->changeLog->logSectionUpdated(
            (int) $section->course_id,
            (string) $section->title,
            (int) $section->id,
            $sectionChanges,
        );

        return $this->redirectToCourseSettings($request, 'ap-mod-'.$courseModule->id)
            ->with('ok', 'Раздел обновлён.');
    }

    public function destroySection(Request $request, Course $adminCourse, CourseModule $courseModule, CourseSection $section): RedirectResponse
    {
        $this->assertSectionInModule($courseModule, $section);
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) session('admin_course_id'));
        $sectionTitle = (string) $section->title;
        $sectionId = (int) $section->id;
        $courseId = (int) $section->course_id;
        $section->delete();
        app(\App\Services\CourseSectionService::class)->clearCache();
        $this->changeLog->logSectionDeleted($courseId, $sectionTitle, $sectionId);

        return $this->redirectToCourseSettings($request, 'ap-mod-'.$courseModule->id)
            ->with('ok', 'Раздел удалён.');
    }

    public function reorderSections(Request $request, Course $adminCourse, CourseModule $courseModule): RedirectResponse
    {
        abort_unless((int) $courseModule->course_id === (int) $adminCourse->id, 403);
        $this->assertModuleCourse($courseModule);
        app(PortalStaffAccess::class)->assertCanEditModuleContent((int) $courseModule->id);
        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|distinct',
        ]);
        $ids = array_values(array_map('intval', $data['order']));
        $owned = CourseSection::query()->where('course_module_id', $courseModule->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $sortedIds = $ids;
        sort($sortedIds);
        if ($owned !== $sortedIds || $owned === []) {
            return $this->redirectToCourseSettings($request, 'ap-mod-'.$courseModule->id)
                ->with('err', 'Некорректный порядок разделов.');
        }
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $i => $sectionId) {
                CourseSection::query()->where('id', $sectionId)->update(['sort' => ($i + 1) * 10]);
            }
        });
        app(\App\Services\CourseSectionService::class)->clearCache();
        $this->changeLog->logSectionsReordered((int) session('admin_course_id'), (int) $courseModule->id, count($ids));

        return $this->redirectToCourseSettings($request, 'ap-mod-'.$courseModule->id)
            ->with('ok', 'Порядок разделов сохранён.');
    }

    public function editSettings(Request $request, Course $adminCourse, CourseModule $courseModule, CourseSection $section): View
    {
        $adminKey = (string) $request->query('key', '');
        $this->assertSectionInModule($courseModule, $section);
        app(PortalStaffAccess::class)->assertCanViewSectionInAdmin((int) $section->id);
        $section->loadMissing('sectionSettings');
        $settings = $section->sectionSettings?->settings;
        if (! is_array($settings)) {
            $settings = self::defaultSettingsForType($section->type);
        }

        return view('admin.course-section-settings', [
            'adminKey' => $adminKey,
            'courseModule' => $courseModule,
            'section' => $section,
            'settings' => $settings,
        ]);
    }

    public function saveSettings(Request $request, Course $adminCourse, CourseModule $courseModule, CourseSection $section): RedirectResponse
    {
        $adminKey = (string) $request->query('key', '');
        $this->assertSectionInModule($courseModule, $section);
        app(PortalStaffAccess::class)->assertCanEditSection((int) $section->id);
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
        $this->changeLog->logSectionSettingsSaved(
            (int) $section->course_id,
            (string) $section->title,
            (int) $section->id,
            (string) $section->type,
        );

        return redirect()
            ->route('admin.course.module.section.settings', array_merge($this->adminCourseRouteParams(), [
                'courseModule' => $courseModule->id,
                'section' => $section->id,
            ]))
            ->with('ok', 'Настройки сохранены.');
    }

    public function sectionPanelData(Request $request, Course $adminCourse, CourseModule $courseModule, CourseSection $section): JsonResponse
    {
        $this->assertSectionInModule($courseModule, $section);
        app(PortalStaffAccess::class)->assertCanViewSectionInAdmin((int) $section->id);
        $courseId = (int) session('admin_course_id');
        abort_unless($courseId > 0, 404);

        $course = Course::query()->findOrFail((int) $section->course_id);
        $isLegacy = $course->isLegacyAltCourse();
        if ($isLegacy) {
            LegacyAltPracticeImagesBootstrap::sync();
        }
        $section->loadMissing('sectionSettings');
        $settings = is_array($section->sectionSettings?->settings) ? $section->sectionSettings->settings : self::defaultSettingsForType($section->type);

        $modIds = CourseModule::query()->where('course_id', $course->id)->orderBy('sort')->orderBy('id')->pluck('id')->all();
        $pos = array_search((int) $courseModule->id, array_map('intval', $modIds), true);
        $modOrdinal = $pos === false ? 1 : ($pos + 1);

        $contentSvc = app(CourseContentService::class);
        $contentRowExists = Schema::hasTable('course_module_contents')
            && CourseModuleContent::query()->where('course_module_id', $courseModule->id)->exists();

        $theoryMd = '';
        $practiceMd = '';
        if (Schema::hasTable('course_module_contents')) {
            $c = $contentSvc->contentForModule($courseModule);
            $theoryMd = (string) ($c['theory_markdown'] ?? '');
            $practiceMd = (string) ($c['practice_markdown'] ?? '');
        }
        if ($isLegacy && ! $contentRowExists) {
            $meta = CourseModuleMeta::resolved($courseModule->effectiveContentIndex());
            $theoryMd = (string) ($meta['theory'] ?? '');
            $practiceMd = (string) ($meta['practice'] ?? '');
        }

        $questions = [];
        $qCount = 0;
        $kind = match ($section->type) {
            CourseSection::TYPE_QUIZ => 'theory_quiz',
            CourseSection::TYPE_EXAM => 'module_exam',
            CourseSection::TYPE_SURVEY => 'survey',
            default => null,
        };
        if ($kind !== null && Schema::hasTable('course_quiz_banks')) {
            $bank = $contentSvc->quizBankOwnedBySection($section);
            if ($bank !== null) {
                $questions = $contentSvc->questionsForBank($bank);
                $qCount = count($questions);
            }
            if ($questions === [] && $isLegacy) {
                $idx = $courseModule->effectiveContentIndex();
                $raw = $kind === 'theory_quiz'
                    ? AdminCourseContentInspector::theoryQuizQuestions($idx)
                    : AdminCourseContentInspector::moduleExamQuestions($idx);
                $questions = array_values($raw);
                $qCount = count($questions);
            }
        }

        $isLegacyPanel = $isLegacy && ! $contentRowExists;
        $practiceImage = null;
        if (Schema::hasTable('course_module_practice_settings')) {
            $ps = CourseModulePracticeSetting::query()->where('course_module_id', $courseModule->id)->first();
            if ($ps !== null && $ps->practice_image_id) {
                $im = PracticeImage::query()->find((int) $ps->practice_image_id);
                if ($im !== null) {
                    $practiceImage = [
                        'id' => (int) $im->id,
                        'title' => (string) $im->title,
                        'docker_tag' => (string) $im->docker_tag,
                        'description' => (string) ($im->description ?? ''),
                        'layers_note' => 'Слои задаются в Dockerfile образа (редактор Docker-образов).',
                    ];
                }
            }
        }

        $dockerImages = Schema::hasTable('practice_images')
            ? app(PortalStaffAccess::class)
                ->scopePracticeImagesForStaff(
                    PracticeImage::query()->where('is_built', true)
                )
                ->orderBy('title')
                ->orderBy('id')
                ->get(['id', 'title', 'docker_tag'])
                ->map(fn (PracticeImage $p) => [
                    'id' => (int) $p->id,
                    'title' => (string) $p->title,
                    'docker_tag' => (string) $p->docker_tag,
                ])->values()->all()
            : [];

        $adminKey = (string) $request->query('key', '');
        $rp = array_merge($this->adminCourseRouteParams(), $adminKey !== '' ? ['key' => $adminKey] : []);

        $inheritHints = $this->inheritHintsFromCourse($course);

        return response()->json([
            'ok' => true,
            'is_legacy' => $isLegacyPanel,
            'course' => [
                'id' => (int) $course->id,
                'title' => (string) $course->title,
                'default_attempt_limit' => $course->default_attempt_limit,
                'default_quiz_time_minutes' => $course->default_quiz_time_minutes,
                'default_pass_percent' => $course->default_pass_percent,
                'inherit_hints' => $inheritHints,
            ],
            'module' => [
                'id' => (int) $courseModule->id,
                'title' => (string) $courseModule->title,
                'ordinal' => $modOrdinal,
            ],
            'section' => [
                'id' => (int) $section->id,
                'type' => (string) $section->type,
                'title' => (string) $section->title,
                'is_enabled' => (bool) $section->is_enabled,
            ],
            'settings' => $settings,
            'theory_markdown' => $theoryMd,
            'practice_markdown' => $practiceMd,
            'questions' => $questions,
            'question_count' => $qCount,
            'practice_image' => $practiceImage,
            'docker_images' => $dockerImages,
            'save_url' => route('admin.course.section.panel.save', array_merge($rp, ['courseModule' => $courseModule->id, 'section' => $section->id])),
            'survey_responses_url' => $section->type === CourseSection::TYPE_SURVEY
                ? route('admin.course.module.section.survey-responses', array_merge($rp, ['courseModule' => $courseModule->id, 'section' => $section->id]))
                : null,
            'quick_link' => $section->type === CourseSection::TYPE_SURVEY
                ? app(SurveyQuickLinkService::class)->metaForSection(
                    $section,
                    route('admin.course.section.quick-link.generate', array_merge($rp, ['courseModule' => $courseModule->id, 'section' => $section->id])),
                    route('admin.course.section.quick-link.revoke', array_merge($rp, ['courseModule' => $courseModule->id, 'section' => $section->id]))
                )
                : null,
            'visibility' => app(LearnerContentVisibilityService::class)->audiencePayloadForResource(
                ContentViewAudienceRule::RESOURCE_SECTION,
                (int) $section->id,
                (int) $course->id,
            ),
            'visibility_save_url' => route('admin.course.section.visibility.update', array_merge($rp, ['courseModule' => $courseModule->id, 'section' => $section->id])),
            'learner_search_url' => route('admin.course.learners.search', $rp),
            'learner_resolve_url' => route('admin.course.learners.resolve', $rp),
        ]);
    }

    public function sectionQuickLinkGenerate(Course $adminCourse, CourseModule $courseModule, CourseSection $section): JsonResponse
    {
        $this->assertSectionInModule($courseModule, $section);
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) session('admin_course_id'));

        if ($section->type !== CourseSection::TYPE_SURVEY) {
            return response()->json(['ok' => false, 'message' => 'Быстрая ссылка доступна только для опросов.'], 422);
        }

        $link = app(SurveyQuickLinkService::class)->generate($section);

        return response()->json([
            'ok' => true,
            'url' => app(SurveyQuickLinkService::class)->learnerUrl($link),
        ]);
    }

    public function sectionQuickLinkRevoke(Course $adminCourse, CourseModule $courseModule, CourseSection $section): JsonResponse
    {
        $this->assertSectionInModule($courseModule, $section);
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) session('admin_course_id'));

        if ($section->type !== CourseSection::TYPE_SURVEY) {
            return response()->json(['ok' => false, 'message' => 'Быстрая ссылка доступна только для опросов.'], 422);
        }

        app(SurveyQuickLinkService::class)->revoke($section);

        return response()->json(['ok' => true]);
    }

    public function sectionPanelSave(Request $request, Course $adminCourse, CourseModule $courseModule, CourseSection $section): JsonResponse
    {
        $this->assertSectionInModule($courseModule, $section);
        app(PortalStaffAccess::class)->assertCanEditSection((int) $section->id);

        $payload = $request->json()->all();
        if ($payload === []) {
            $decoded = json_decode((string) $request->input('payload', ''), true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        if ($payload === []) {
            return response()->json(['ok' => false, 'message' => 'Пустое тело запроса.'], 422);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($payload, [
            'title' => 'required|string|max:200',
            'type' => 'required|in:text,quiz,practice,exam,survey',
            'is_enabled' => 'required|boolean',
            'attempts_from_course' => 'sometimes|boolean',
            'time_from_course' => 'sometimes|boolean',
            'pass_from_course' => 'sometimes|boolean',
            'attempt_limit' => 'nullable|integer|min:1|max:99',
            'time_limit_minutes' => 'nullable|integer|min:0|max:10080',
            'pass_percent' => 'nullable|integer|min:1|max:100',
            'theory_markdown' => 'nullable|string|max:8000000',
            'practice_markdown' => 'nullable|string|max:8000000',
            'practice_image_id' => 'nullable|integer|min:0',
            'questions' => 'nullable|array',
            'shuffle' => 'sometimes|boolean',
            'one_by_one' => 'sometimes|boolean',
            'breakdown_visible_minutes' => 'nullable|integer|min:0|max:10080',
            'min_read_seconds' => 'nullable|integer|min:0|max:86400',
            'anonymous' => 'sometimes|boolean',
            'blocks_progress' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => (string) $validator->errors()->first()], 422);
        }
        $p = $validator->validated();

        $course = Course::query()->findOrFail((int) $section->course_id);
        if ($course->isLegacyAltCourse()
            && (! Schema::hasTable('course_module_contents')
                || ! CourseModuleContent::query()->where('course_module_id', $courseModule->id)->exists())) {
            return response()->json(['ok' => false, 'message' => 'Курс в legacy-режиме: сначала выполните миграции с переносом контента в БД или используйте классические редакторы.'], 422);
        }

        try {
            DB::transaction(function () use ($course, $courseModule, $section, $p, $payload): void {
                $section->title = (string) $p['title'];
                $section->type = (string) $p['type'];
                $section->is_enabled = (bool) $p['is_enabled'];
                $section->save();

                $section->loadMissing('sectionSettings');
                $prev = is_array($section->sectionSettings?->settings) ? $section->sectionSettings->settings : [];
                $af = (bool) ($p['attempts_from_course'] ?? false);
                $tf = (bool) ($p['time_from_course'] ?? false);
                $pf = (bool) ($p['pass_from_course'] ?? false);

                $merged = match ($section->type) {
                    CourseSection::TYPE_TEXT => array_merge($prev, [
                        'min_read_seconds' => isset($p['min_read_seconds']) ? (int) $p['min_read_seconds'] : (int) ($prev['min_read_seconds'] ?? 0),
                        'time_limit_minutes' => $tf ? null : (isset($p['time_limit_minutes']) && $p['time_limit_minutes'] !== null && $p['time_limit_minutes'] !== '' ? (int) $p['time_limit_minutes'] : ($prev['time_limit_minutes'] ?? null)),
                        'attempts_from_course' => $af,
                        'time_from_course' => $tf,
                        'pass_from_course' => $pf,
                        'attempt_limit' => $af ? null : (isset($p['attempt_limit']) && $p['attempt_limit'] !== null && $p['attempt_limit'] !== '' ? (int) $p['attempt_limit'] : ($prev['attempt_limit'] ?? null)),
                        'pass_percent' => $pf ? null : (int) ($p['pass_percent'] ?? $prev['pass_percent'] ?? 70),
                    ]),
                    CourseSection::TYPE_QUIZ => array_merge($prev, [
                        'attempts_from_course' => $af,
                        'time_from_course' => $tf,
                        'pass_from_course' => $pf,
                        'attempt_limit' => $af ? null : (isset($p['attempt_limit']) && $p['attempt_limit'] !== null ? (int) $p['attempt_limit'] : null),
                        'time_limit_minutes' => $tf ? null : (int) ($p['time_limit_minutes'] ?? $prev['time_limit_minutes'] ?? 30),
                        'pass_percent' => $pf ? null : (int) ($p['pass_percent'] ?? $prev['pass_percent'] ?? 70),
                        'shuffle' => (bool) ($p['shuffle'] ?? ($prev['shuffle'] ?? false)),
                        'penalties' => is_array($prev['penalties'] ?? null) ? $prev['penalties'] : ['2' => 10],
                    ]),
                    CourseSection::TYPE_PRACTICE => array_merge($prev, [
                        'attempts_from_course' => $af,
                        'time_from_course' => $tf,
                        'pass_from_course' => $pf,
                        'attempt_limit' => $af ? null : (isset($p['attempt_limit']) && $p['attempt_limit'] !== null ? (int) $p['attempt_limit'] : null),
                        'time_limit_minutes' => $tf ? null : (isset($p['time_limit_minutes']) && $p['time_limit_minutes'] !== null && $p['time_limit_minutes'] !== '' ? (int) $p['time_limit_minutes'] : ($prev['time_limit_minutes'] ?? null)),
                    ]),
                    CourseSection::TYPE_SURVEY => array_merge($prev, [
                        'attempt_limit' => isset($p['attempt_limit']) && $p['attempt_limit'] !== null && $p['attempt_limit'] !== '' ? (int) $p['attempt_limit'] : ($prev['attempt_limit'] ?? 1),
                        'time_limit_minutes' => isset($p['time_limit_minutes']) && $p['time_limit_minutes'] !== null && $p['time_limit_minutes'] !== '' ? (int) $p['time_limit_minutes'] : ($prev['time_limit_minutes'] ?? null),
                        'shuffle' => (bool) ($p['shuffle'] ?? ($prev['shuffle'] ?? false)),
                        'one_by_one' => (bool) ($p['one_by_one'] ?? ($prev['one_by_one'] ?? true)),
                        'blocks_progress' => (bool) ($p['blocks_progress'] ?? ($prev['blocks_progress'] ?? false)),
                        'anonymous' => (bool) ($p['anonymous'] ?? ($prev['anonymous'] ?? false)),
                    ]),
                    CourseSection::TYPE_EXAM => array_merge($prev, [
                        'attempts_from_course' => $af,
                        'time_from_course' => $tf,
                        'pass_from_course' => $pf,
                        'attempt_limit' => $af ? null : (int) ($p['attempt_limit'] ?? $prev['attempt_limit'] ?? 2),
                        'time_limit_minutes' => $tf ? null : (int) ($p['time_limit_minutes'] ?? $prev['time_limit_minutes'] ?? 60),
                        'pass_percent' => $pf ? null : (int) ($p['pass_percent'] ?? $prev['pass_percent'] ?? 70),
                        'one_by_one' => (bool) ($p['one_by_one'] ?? ($prev['one_by_one'] ?? true)),
                        'breakdown_visible_minutes' => (int) ($p['breakdown_visible_minutes'] ?? ($prev['breakdown_visible_minutes'] ?? 30)),
                        'penalties' => is_array($prev['penalties'] ?? null) ? $prev['penalties'] : ['2' => 10],
                    ]),
                    default => $prev,
                };

                if ($section->type === CourseSection::TYPE_QUIZ && ($merged['pass_percent'] ?? null) === null && ! $pf) {
                    $merged['pass_percent'] = (int) ($prev['pass_percent'] ?? 70);
                }

                CourseSectionSetting::query()->updateOrCreate(
                    ['course_section_id' => $section->id],
                    ['settings' => $merged]
                );

                if (Schema::hasTable('course_module_contents')) {
                    $contentSvc = app(CourseContentService::class);
                    $row = $contentSvc->contentForModule($courseModule);
                    $t = (string) ($row['theory_markdown'] ?? '');
                    $pr = (string) ($row['practice_markdown'] ?? '');
                    if ($section->type === CourseSection::TYPE_TEXT && array_key_exists('theory_markdown', $payload)) {
                        $t = (string) $p['theory_markdown'];
                    }
                    if ($section->type === CourseSection::TYPE_PRACTICE && array_key_exists('practice_markdown', $payload)) {
                        $pr = (string) $p['practice_markdown'];
                    }
                    if ($section->type === CourseSection::TYPE_TEXT || $section->type === CourseSection::TYPE_PRACTICE) {
                        $contentSvc->upsertContentForModule($courseModule, $t, $pr);
                    }
                }

                if ($section->type === CourseSection::TYPE_PRACTICE && Schema::hasTable('course_module_practice_settings')) {
                    $imgId = isset($p['practice_image_id']) ? (int) $p['practice_image_id'] : null;
                    if ($imgId !== null && $imgId <= 0) {
                        $imgId = null;
                    }
                    if ($imgId !== null && ! PracticeImage::query()->whereKey($imgId)->where('is_built', true)->exists()) {
                        throw new \InvalidArgumentException('Docker-образ не найден или не собран.');
                    }
                    if ($imgId !== null && $imgId > 0) {
                        app(PortalStaffAccess::class)->assertCanAssignPracticeImageToCourse($imgId, (int) $course->id);
                    }
                    $row = CourseModulePracticeSetting::query()->firstOrNew(['course_module_id' => $courseModule->id]);
                    $row->practice_image_id = $imgId;
                    $row->save();
                }

                if (in_array($section->type, [CourseSection::TYPE_QUIZ, CourseSection::TYPE_EXAM, CourseSection::TYPE_SURVEY], true) && isset($p['questions']) && is_array($p['questions'])) {
                    $kind = match ($section->type) {
                        CourseSection::TYPE_QUIZ => 'theory_quiz',
                        CourseSection::TYPE_EXAM => 'module_exam',
                        CourseSection::TYPE_SURVEY => 'survey',
                        default => 'theory_quiz',
                    };
                    $contentSvc = app(CourseContentService::class);
                    $defaults = match ($kind) {
                        'theory_quiz' => ['pass_percent' => 70, 'time_limit_minutes' => 30, 'attempt_limit' => null, 'shuffle' => false, 'one_by_one' => true, 'breakdown_visible_minutes' => 15, 'penalties_json' => ['2' => 10]],
                        'survey' => ['pass_percent' => 0, 'time_limit_minutes' => null, 'attempt_limit' => 1, 'shuffle' => false, 'one_by_one' => true, 'breakdown_visible_minutes' => 0, 'penalties_json' => null],
                        default => ['pass_percent' => 70, 'time_limit_minutes' => 60, 'attempt_limit' => 2, 'shuffle' => false, 'one_by_one' => true, 'breakdown_visible_minutes' => 30, 'penalties_json' => ['2' => 10]],
                    };
                    $bank = $contentSvc->ensureQuizBankForSection($course, $courseModule, $section, $kind, $defaults);
                    $quiz = app(AdminQuizController::class);
                    $allowPoints = $kind === 'module_exam';
                    $v = $quiz->validateQuizBankFormat($p['questions'], $kind, $allowPoints);
                    if ($v['ok'] !== true) {
                        throw new \InvalidArgumentException((string) $v['message']);
                    }
                    $quiz->persistQuizBankItems($bank, $v['data']);
                }
            });
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        app(CourseSectionService::class)->clearCache();
        $this->changeLog->logSectionPanelSaved(
            (int) $section->course_id,
            (string) $section->title,
            (int) $section->id,
            (string) $section->type,
        );

        return response()->json(['ok' => true]);
    }

    /**
     * @return array{attempts: string, time: string, pass: string}
     */
    private function inheritHintsFromCourse(Course $course): array
    {
        $a = $course->default_attempt_limit;
        $t = $course->default_quiz_time_minutes;
        $p = $course->default_pass_percent;
        $attempts = ($a === null || ! is_numeric($a) || (int) $a < 1)
            ? 'без ограничения'
            : (string) (int) $a;
        $time = ($t === null || ! is_numeric($t) || (int) $t < 1)
            ? 'не задано в курсе'
            : ((int) $t).' мин';
        $pass = ($p === null || ! is_numeric($p) || (int) $p < 1)
            ? 'порог по умолчанию системы'
            : ((int) $p).'%';

        return [
            'attempts' => $attempts,
            'time' => $time,
            'pass' => $pass,
        ];
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
            CourseSection::TYPE_SURVEY => [
                'time_limit_minutes' => null,
                'attempt_limit' => 1,
                'pass_percent' => null,
                'shuffle' => false,
                'one_by_one' => true,
                'blocks_progress' => true,
                'anonymous' => false,
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCertificateSettingsFromRequest(Course $course, Request $request, array $data): ?RedirectResponse
    {
        $course->certificate_enabled = $request->boolean('certificate_enabled');
        $course->certificate_title = isset($data['certificate_title']) ? (string) $data['certificate_title'] : null;
        $course->certificate_body = isset($data['certificate_body']) ? (string) $data['certificate_body'] : null;
        $tiersJson = isset($data['certificate_tiers']) ? trim((string) $data['certificate_tiers']) : '';
        if ($tiersJson !== '') {
            $decoded = json_decode($tiersJson, true);
            if (! is_array($decoded)) {
                return back()->withInput()->with('err', 'Сертификат: уровни должны быть корректным JSON.');
            }
            $tiers = [];
            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $min = isset($row['min_percent']) ? (int) $row['min_percent'] : 0;
                $min = max(0, min(100, $min));
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $key = strtolower(preg_replace('/[^a-z0-9\-_]/', '', (string) ($row['key'] ?? '')));
                if (strlen($key) > 40) {
                    $key = substr($key, 0, 40);
                }
                $tiers[] = [
                    'key' => $key,
                    'min_percent' => $min,
                    'label' => mb_substr($label, 0, 120, 'UTF-8'),
                ];
            }
            if ($tiers === []) {
                return back()->withInput()->with('err', 'Сертификат: добавьте хотя бы один уровень с текстом.');
            }
            usort($tiers, static fn ($a, $b) => ((int) $b['min_percent']) <=> ((int) $a['min_percent']));
            $course->certificate_tiers = $tiers;
        } else {
            $course->certificate_tiers = null;
        }

        return null;
    }
}
