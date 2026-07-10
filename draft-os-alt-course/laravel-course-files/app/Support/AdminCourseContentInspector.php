<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Services\CourseContentService;
use App\Services\CourseSectionService;
use Illuminate\Support\Facades\Schema;

/**
 * Сводка по содержимому модуля для админ-панели (теория, тесты, практика).
 */
final class AdminCourseContentInspector
{
    /** @var list<array{key: string, label: string}> */
    private const LEGACY_CONTENT_COLUMNS = [
        ['key' => 'text', 'label' => 'Теория'],
        ['key' => 'quiz', 'label' => 'Тест'],
        ['key' => 'practice', 'label' => 'Практика'],
        ['key' => 'exam', 'label' => 'Итоговый тест'],
        ['key' => 'docker', 'label' => 'Docker'],
    ];

    /** @var list<string> */
    private const COLUMN_TYPE_ORDER = [
        CourseSection::TYPE_TEXT,
        CourseSection::TYPE_QUIZ,
        CourseSection::TYPE_PRACTICE,
        CourseSection::TYPE_EXAM,
        CourseSection::TYPE_SURVEY,
    ];

    /**
     * Колонки таблицы «Содержимое»: по одному столбцу на позицию раздела в модуле
     * (максимум разделов среди модулей курса), а не по каждому разделу всех модулей.
     *
     * @return list<array{key: string, label: string, type?: string, slot?: int, section_id?: int, course_module_id?: int}>
     */
    public static function contentColumnsForCourse(int $courseId, bool $legacyAlt = false): array
    {
        if ($legacyAlt || $courseId < 1 || ! Schema::hasTable('course_sections')) {
            return self::LEGACY_CONTENT_COLUMNS;
        }

        $sectionSvc = app(CourseSectionService::class);
        $slots = [];
        $modules = CourseModule::query()
            ->where('course_id', $courseId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        foreach ($modules as $cm) {
            if (! $sectionSvc->useDbSectionsForModule((int) $cm->id)) {
                return self::LEGACY_CONTENT_COLUMNS;
            }
            foreach ($sectionSvc->enabledSectionsForCourseModule((int) $cm->id)->values() as $slot => $sec) {
                if (! isset($slots[$slot])) {
                    $slots[$slot] = self::columnFromSlot((int) $slot, $sec);
                }
            }
        }

        if ($slots === []) {
            return self::LEGACY_CONTENT_COLUMNS;
        }

        ksort($slots);

        return array_values($slots);
    }

    public static function sectionAtSlot(CourseModule $cm, int $slot): ?CourseSection
    {
        if ($slot < 0 || ! Schema::hasTable('course_sections')) {
            return null;
        }

        $sections = app(CourseSectionService::class)
            ->enabledSectionsForCourseModule((int) $cm->id)
            ->values();

        $section = $sections->get($slot);

        return $section instanceof CourseSection ? $section : null;
    }

    /**
     * @return array{key: string, label: string, type: string, slot: int}
     */
    public static function columnFromSlot(int $slot, CourseSection $sec): array
    {
        $col = self::columnFromSection($sec);
        $col['key'] = 'slot'.$slot;
        $col['slot'] = $slot;
        unset($col['section_id'], $col['course_module_id']);

        return $col;
    }

    /**
     * @return array{key: string, label: string, type: string, section_id: int, course_module_id: int}
     */
    public static function columnFromSection(CourseSection $sec): array
    {
        $title = trim((string) $sec->title);
        $defaultLabels = [
            CourseSection::TYPE_TEXT => 'Теория',
            CourseSection::TYPE_QUIZ => 'Тест',
            CourseSection::TYPE_PRACTICE => 'Практика',
            CourseSection::TYPE_EXAM => 'Итоговый тест',
            CourseSection::TYPE_SURVEY => 'Опрос',
        ];
        $type = (string) $sec->type;

        return [
            'key' => 's'.(int) $sec->id,
            'label' => $title !== '' ? $title : ($defaultLabels[$type] ?? $type),
            'type' => $type,
            'section_id' => (int) $sec->id,
            'course_module_id' => (int) $sec->course_module_id,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function questionsForSection(CourseSection $section): array
    {
        if (! Schema::hasTable('course_quiz_banks')) {
            return [];
        }

        $bank = app(CourseContentService::class)->quizBankOwnedBySection($section);
        if ($bank === null) {
            return [];
        }

        return app(CourseContentService::class)->questionsForBank($bank);
    }

    public static function surveyResponseCount(int $sectionId): int
    {
        if ($sectionId < 1 || ! Schema::hasTable('course_survey_submissions')) {
            return 0;
        }

        return (int) \App\Models\CourseSurveySubmission::query()
            ->where('course_section_id', $sectionId)
            ->count();
    }

    /**
     * @param  array{key: string, label: string, type?: string, section_id?: int, course_module_id?: int}  $col
     * @return array{
     *     has_section: bool,
     *     filled: bool,
     *     meta: string,
     *     preview_url: ?string,
     *     preview_title: ?string,
     *     stats_url: ?string,
     *     stats_label: ?string,
     *     col_type: string,
     *     docker_image: ?string
     * }
     */
    public static function cellForModuleColumn(
        Course $course,
        CourseModule $cm,
        int $contentPackageIndex,
        array $col,
        ?string $dockerImage = null
    ): array {
        $empty = [
            'has_section' => false,
            'filled' => false,
            'meta' => '',
            'preview_url' => null,
            'preview_title' => null,
            'stats_url' => null,
            'stats_label' => null,
            'col_type' => (string) ($col['type'] ?? ''),
            'docker_image' => null,
        ];

        $section = null;
        if (isset($col['slot'])) {
            $section = self::sectionAtSlot($cm, (int) $col['slot']);
        } else {
            $sectionId = (int) ($col['section_id'] ?? 0);
            if ($sectionId > 0 && (int) ($col['course_module_id'] ?? 0) === (int) $cm->id) {
                $section = CourseSection::query()->find($sectionId);
            }
        }

        if (! $section instanceof CourseSection
            || (int) $section->course_module_id !== (int) $cm->id
            || (int) $section->course_id !== (int) $course->id
            || ! $section->is_enabled) {
            return $empty;
        }

        $sectionId = (int) $section->id;

        $rp = ['adminCourse' => (string) $course->slug];
        $previewBase = array_merge($rp, [
            'module' => $contentPackageIndex,
            'section' => $sectionId,
        ]);
        $type = (string) $section->type;
        $db = self::databaseModuleContentSummary($course, $cm);
        $trackPreviewUrl = route('admin.course.preview.section', $previewBase);

        return match ($type) {
            CourseSection::TYPE_TEXT => self::withTrackPreview([
                'has_section' => true,
                'filled' => (int) $db['theory_chars'] > 0,
                'meta' => ((int) $db['theory_chars'] > 0)
                    ? number_format((int) $db['theory_chars'], 0, ',', ' ').' симв.'
                    : '0 симв.',
                'preview_url' => route('admin.theory.preview-section', $previewBase),
                'preview_title' => 'Просмотр: '.$section->title,
                'stats_url' => null,
                'stats_label' => null,
                'col_type' => $type,
                'docker_image' => null,
            ], $trackPreviewUrl),
            CourseSection::TYPE_QUIZ => self::withTrackPreview(self::quizLikeCell(
                $section,
                self::questionsForSection($section),
                route('admin.theory.preview-section', $previewBase),
                'Просмотр: '.$section->title,
                $type,
            ), $trackPreviewUrl),
            CourseSection::TYPE_EXAM => self::withTrackPreview(self::examLikeCell(
                $section,
                self::questionsForSection($section),
                (int) $cm->id,
                $contentPackageIndex,
                route('admin.theory.preview-section', $previewBase),
                'Просмотр: '.$section->title,
                $type,
            ), $trackPreviewUrl),
            CourseSection::TYPE_SURVEY => self::withTrackPreview(self::surveyCell(
                $section,
                self::questionsForSection($section),
                route('admin.theory.preview-section', $previewBase),
                route('admin.course.module.section.survey-responses', array_merge($rp, [
                    'courseModule' => (int) $cm->id,
                    'section' => $sectionId,
                ])),
                $type,
            ), $trackPreviewUrl),
            CourseSection::TYPE_PRACTICE => self::withTrackPreview([
                'has_section' => true,
                'filled' => (string) $db['practice_markdown'] !== '',
                'meta' => self::practiceSummaryLine((string) $db['practice_markdown']),
                'preview_url' => route('admin.theory.preview-section', $previewBase),
                'preview_title' => 'Просмотр: '.$section->title,
                'stats_url' => null,
                'stats_label' => null,
                'col_type' => $type,
                'docker_image' => $dockerImage,
            ], $trackPreviewUrl),
            default => self::withTrackPreview(array_merge($empty, ['has_section' => true, 'col_type' => $type]), $trackPreviewUrl),
        };
    }

    /**
     * @param  array<string, mixed>  $cell
     * @return array<string, mixed>
     */
    private static function withTrackPreview(array $cell, string $trackPreviewUrl): array
    {
        $cell['track_preview_url'] = $trackPreviewUrl;

        return $cell;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return array{has_section: bool, filled: bool, meta: string, preview_url: ?string, preview_title: ?string, stats_url: ?string, stats_label: ?string, col_type: string, docker_image: ?string}
     */
    private static function quizLikeCell(
        CourseSection $section,
        array $questions,
        string $previewUrl,
        string $previewTitle,
        string $type
    ): array {
        $count = count($questions);
        $match = self::countMatchDrag($questions);
        $meta = $count < 1 ? '0 вопр.' : $count.' вопр.';
        if ($match > 0) {
            $meta .= ' · '.$match.' сопост.';
        }

        return [
            'has_section' => true,
            'filled' => $count > 0,
            'meta' => $meta,
            'preview_url' => $previewUrl,
            'preview_title' => $previewTitle,
            'stats_url' => null,
            'stats_label' => null,
            'col_type' => $type,
            'docker_image' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return array{has_section: bool, filled: bool, meta: string, preview_url: ?string, preview_title: ?string, stats_url: ?string, stats_label: ?string, col_type: string, docker_image: ?string}
     */
    private static function examLikeCell(
        CourseSection $section,
        array $questions,
        int $courseModuleId,
        int $contentPackageIndex,
        string $previewUrl,
        string $previewTitle,
        string $type
    ): array {
        $count = count($questions);
        $match = self::countMatchDrag($questions);
        $timeMin = app(CourseSectionService::class)->examTimeLimitMinutes($courseModuleId, $contentPackageIndex, false);
        if ($count < 1) {
            $meta = '0 вопр.';
        } else {
            $meta = $count.' вопр. · '.$timeMin.' мин';
            if ($match > 0) {
                $meta .= ' · '.$match.' сопост.';
            }
        }

        return [
            'has_section' => true,
            'filled' => $count > 0,
            'meta' => $meta,
            'preview_url' => $previewUrl,
            'preview_title' => $previewTitle,
            'stats_url' => null,
            'stats_label' => null,
            'col_type' => $type,
            'docker_image' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return array{has_section: bool, filled: bool, meta: string, preview_url: ?string, preview_title: ?string, stats_url: ?string, stats_label: ?string, col_type: string, docker_image: ?string}
     */
    private static function surveyCell(
        CourseSection $section,
        array $questions,
        string $previewUrl,
        string $statsUrl,
        string $type
    ): array {
        $count = count($questions);
        $responses = self::surveyResponseCount((int) $section->id);
        $match = self::countMatchDrag($questions);
        $meta = $count < 1 ? '0 вопр.' : $count.' вопр.';
        if ($match > 0) {
            $meta .= ' · '.$match.' сопост.';
        }
        if ($responses > 0) {
            $meta .= ' · '.$responses.' отв.';
        }

        return [
            'has_section' => true,
            'filled' => $count > 0,
            'meta' => $meta,
            'preview_url' => $previewUrl,
            'preview_title' => 'Просмотр: '.$section->title,
            'stats_url' => $statsUrl,
            'stats_label' => 'Ответы',
            'col_type' => $type,
            'docker_image' => null,
        ];
    }

    public static function moduleHasSectionType(int $courseModuleId, string $type): bool
    {
        if ($courseModuleId < 1 || ! Schema::hasTable('course_sections')) {
            return false;
        }

        return app(CourseSectionService::class)
            ->enabledSectionsForCourseModule($courseModuleId)
            ->contains(fn (CourseSection $sec): bool => (string) $sec->type === $type);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function questionsForModuleSections(CourseModule $cm, string $sectionType): array
    {
        if (! Schema::hasTable('course_quiz_banks')) {
            return [];
        }

        $contentSvc = app(CourseContentService::class);
        $sectionSvc = app(CourseSectionService::class);
        $questions = [];

        foreach ($sectionSvc->enabledSectionsForCourseModule((int) $cm->id) as $sec) {
            if ((string) $sec->type !== $sectionType) {
                continue;
            }
            $bank = $contentSvc->quizBankForSection($sec);
            if ($bank === null) {
                continue;
            }
            foreach ($contentSvc->questionsForBank($bank) as $q) {
                $questions[] = $q;
            }
        }

        return array_values($questions);
    }

    /**
     * Сводка по модулю курса из БД (course_module_contents, course_quiz_*).
     *
     * @return array{
     *     theory_chars: int,
     *     theory_markdown: string,
     *     practice_markdown: string,
     *     theory_quiz: list<array<string, mixed>>,
     *     exam: list<array<string, mixed>>,
     *     exam_time_min: int,
     *     has_practice_section: bool
     * }
     */
    public static function databaseModuleContentSummary(Course $course, CourseModule $cm): array
    {
        $contentSvc = app(CourseContentService::class);
        $stored = $contentSvc->contentForModule($cm);
        $theoryMd = (string) ($stored['theory_markdown'] ?? '');
        $practiceMd = (string) ($stored['practice_markdown'] ?? '');
        $tq = self::questionsForModuleSections($cm, CourseSection::TYPE_QUIZ);
        $ex = self::questionsForModuleSections($cm, CourseSection::TYPE_EXAM);
        $idx = $cm->effectiveContentIndex();
        $examMin = app(CourseSectionService::class)->examTimeLimitMinutes((int) $cm->id, $idx, false);

        return [
            'theory_chars' => mb_strlen($theoryMd),
            'theory_markdown' => $theoryMd,
            'practice_markdown' => $practiceMd,
            'theory_quiz' => array_values($tq),
            'exam' => array_values($ex),
            'exam_time_min' => $examMin,
            'has_practice_section' => self::moduleHasSectionType((int) $cm->id, CourseSection::TYPE_PRACTICE),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function theoryQuizQuestions(int $module): array
    {
        $q = config('course.module_quizzes.'.$module.'.theory_quiz', []);

        return is_array($q) ? array_values($q) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function moduleExamQuestions(int $module): array
    {
        $q = config('course.module_quizzes.'.$module.'.module_exam', []);

        return is_array($q) ? array_values($q) : [];
    }

    public static function practiceMarkdown(int $module): string
    {
        $meta = CourseModuleMeta::resolved($module);
        $p = $meta['practice'] ?? '';

        return is_string($p) ? $p : '';
    }

    /**
     * Длина текста теории после подстановки сниппетов (как у студента на странице теории).
     */
    public static function theoryCharacterCount(int $module): int
    {
        $meta = CourseModuleMeta::resolved($module);
        $t = $meta['theory'] ?? '';

        return is_string($t) ? mb_strlen($t) : 0;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     */
    public static function countMatchDrag(array $questions): int
    {
        $n = 0;
        foreach ($questions as $q) {
            if (! empty($q['match_drag'])) {
                $n++;
            }
        }

        return $n;
    }

    public static function practiceSummaryLine(string $markdown): string
    {
        if ($markdown === '') {
            return 'нет текста';
        }
        $chars = mb_strlen($markdown);
        $lines = substr_count($markdown, "\n") + 1;

        return sprintf('~%s симв., ~%d стр.', number_format($chars, 0, ',', ' '), $lines);
    }

    /**
     * Имя образа из practice_lab.images для модуля (как в {@see \App\Services\PracticeLabService::imageForModule}).
     * null — ключа нет или строка пустая.
     */
    public static function practiceLabDockerImageForModule(int $module): ?string
    {
        $images = config('practice_lab.images', []);
        if (! is_array($images)) {
            return null;
        }
        $key = (string) $module;
        if (! isset($images[$key])) {
            return null;
        }
        $v = trim((string) $images[$key]);

        return $v !== '' ? $v : null;
    }

    /**
     * Количество вопросов в банках модуля (включая course_section_id).
     */
    public static function dbQuestionCountForModule(int $courseId, int $courseModuleId, string $kind): int
    {
        if (! Schema::hasTable('course_quiz_banks') || ! Schema::hasTable('course_quiz_questions')) {
            return 0;
        }

        $bankIds = \App\Models\CourseQuizBank::query()
            ->where('course_id', $courseId)
            ->where('course_module_id', $courseModuleId)
            ->where('kind', $kind)
            ->pluck('id');

        if ($bankIds->isEmpty()) {
            return 0;
        }

        return (int) \App\Models\CourseQuizQuestion::query()
            ->whereIn('quiz_bank_id', $bankIds->all())
            ->count();
    }
}
