<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\CourseChangeLog;
use App\Models\PortalStaff;
use App\Support\LearnerDisplay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class CourseChangeLogService
{
    /** @var array<string, string> */
    private const COURSE_FIELD_LABELS = [
        'title' => 'Название',
        'slug' => 'Slug',
        'summary' => 'Краткое описание',
        'is_published' => 'Опубликован',
        'is_archived' => 'В архиве',
        'sort' => 'Порядок сортировки',
        'default_attempt_limit' => 'Лимит попыток (по умолчанию)',
        'default_quiz_time_minutes' => 'Время теста, мин (по умолчанию)',
        'default_pass_percent' => 'Порог зачёта, % (по умолчанию)',
        'final_lab_enabled' => 'Итоговая лабораторная',
        'final_lab_practice_image_id' => 'Docker-образ итоговой лабораторной',
        'difficulty_flags_enabled' => 'Флаги сложности',
        'unlock_all_modules' => 'Открыть все модули',
        'show_module_progress' => 'Показывать прогресс модулей',
        'assessment_enabled' => 'Оценивание',
        'show_score_percents' => 'Показывать проценты обучающимся',
        'show_score_points' => 'Показывать баллы обучающимся',
        'quiz_breakdown_mode' => 'Разбор теста (все / только ошибки)',
        'audience_plaque_enabled' => 'Плашка аудитории',
        'audience_plaque_kicker' => 'Плашка: подзаголовок',
        'audience_plaque_title' => 'Плашка: заголовок',
        'audience_plaque_teaser' => 'Плашка: тизер',
        'audience_plaque_body' => 'Плашка: текст',
        'certificate_enabled' => 'Сертификат',
        'certificate_title' => 'Сертификат: заголовок',
        'certificate_body' => 'Сертификат: текст',
        'certificate_tiers' => 'Сертификат: уровни',
    ];

    /** @var array<string, string> */
    private const SECTION_TYPE_LABELS = [
        'text' => 'Теория',
        'quiz' => 'Тест',
        'practice' => 'Практика',
        'exam' => 'Экзамен',
        'survey' => 'Опрос',
    ];

    public function log(
        int $courseId,
        string $action,
        string $area,
        string $summary,
        ?array $details = null,
    ): void {
        if ($courseId <= 0 || ! Schema::hasTable('course_change_logs')) {
            return;
        }

        $staffId = $this->currentStaffId();

        CourseChangeLog::query()->create([
            'course_id' => $courseId,
            'portal_staff_id' => $staffId > 0 ? $staffId : null,
            'action' => mb_substr($action, 0, 64, 'UTF-8'),
            'area' => mb_substr($area, 0, 32, 'UTF-8'),
            'summary' => mb_substr(trim($summary), 0, 500, 'UTF-8'),
            'details' => $details,
        ]);
    }

    /**
     * @return list<array{field: string, label: string, old: string, new: string}>
     */
    public function describeCourseDirty(Course $course): array
    {
        $out = [];
        foreach ($course->getDirty() as $field => $newValue) {
            if (! isset(self::COURSE_FIELD_LABELS[$field])) {
                continue;
            }
            $oldValue = $course->getOriginal($field);
            $out[] = [
                'field' => (string) $field,
                'label' => self::COURSE_FIELD_LABELS[$field],
                'old' => $this->formatCourseValue($field, $oldValue),
                'new' => $this->formatCourseValue($field, $newValue),
            ];
        }

        return $out;
    }

    public function logCourseDirty(Course $course, string $summaryPrefix = 'Обновлены настройки курса'): void
    {
        $changes = $this->describeCourseDirty($course);
        if ($changes === []) {
            return;
        }

        $labels = array_map(static fn (array $c) => $c['label'], $changes);
        $summary = $summaryPrefix;
        if ($labels !== []) {
            $summary .= ': '.implode(', ', array_slice($labels, 0, 4));
            if (count($labels) > 4) {
                $summary .= ' и ещё '.(count($labels) - 4);
            }
        }

        $this->log(
            (int) $course->id,
            'course.updated',
            'course',
            $summary,
            ['changes' => $changes],
        );
    }

    public function logCourseCreated(Course $course): void
    {
        $this->log(
            (int) $course->id,
            'course.created',
            'course',
            'Курс создан',
            [
                'title' => (string) $course->title,
                'slug' => (string) $course->slug,
            ],
        );
    }

    public function logCoursePublished(Course $course): void
    {
        $this->log((int) $course->id, 'course.published', 'course', 'Курс опубликован');
    }

    public function logCourseArchived(Course $course): void
    {
        $this->log((int) $course->id, 'course.archived', 'course', 'Курс перенесён в архив');
    }

    public function logCourseUnarchived(Course $course): void
    {
        $this->log((int) $course->id, 'course.unarchived', 'course', 'Курс восстановлен из архива');
    }

    public function logModuleCreated(int $courseId, string $title, int $moduleId): void
    {
        $this->log(
            $courseId,
            'module.created',
            'module',
            'Добавлен модуль «'.$title.'»',
            ['module_id' => $moduleId, 'title' => $title],
        );
    }

    public function logModuleUpdated(int $courseId, string $title, int $moduleId, array $changes = []): void
    {
        $this->log(
            $courseId,
            'module.updated',
            'module',
            'Обновлён модуль «'.$title.'»',
            ['module_id' => $moduleId, 'title' => $title, 'changes' => $changes],
        );
    }

    public function logModuleDeleted(int $courseId, string $title, int $moduleId): void
    {
        $this->log(
            $courseId,
            'module.deleted',
            'module',
            'Удалён модуль «'.$title.'»',
            ['module_id' => $moduleId, 'title' => $title],
        );
    }

    public function logModulesReordered(int $courseId, int $count): void
    {
        $this->log(
            $courseId,
            'module.reordered',
            'module',
            'Изменён порядок модулей ('.$count.')',
            ['count' => $count],
        );
    }

    public function logContentVisibilityChanged(
        int $courseId,
        string $resourceType,
        int $resourceId,
        string $viewAudience,
        int $ruleCount,
    ): void {
        $target = match ($resourceType) {
            'course' => 'курсу',
            'module' => 'модулю #'.$resourceId,
            'section' => 'разделу #'.$resourceId,
            default => $resourceType.' #'.$resourceId,
        };
        $mode = $viewAudience === 'restricted'
            ? 'ограниченный ('.$ruleCount.' правил)'
            : 'все обучающиеся';
        $this->log(
            $courseId,
            'visibility.updated',
            $resourceType,
            'Доступ к '.$target.': '.$mode,
            [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'view_audience' => $viewAudience,
                'rule_count' => $ruleCount,
            ],
        );
    }

    public function logSectionCreated(int $courseId, string $sectionTitle, string $type, int $sectionId, int $moduleId): void
    {
        $typeLabel = self::SECTION_TYPE_LABELS[$type] ?? $type;
        $this->log(
            $courseId,
            'section.created',
            'section',
            'Добавлен раздел «'.$sectionTitle.'» ('.$typeLabel.')',
            [
                'section_id' => $sectionId,
                'module_id' => $moduleId,
                'title' => $sectionTitle,
                'type' => $type,
            ],
        );
    }

    public function logSectionUpdated(int $courseId, string $sectionTitle, int $sectionId, array $changes = []): void
    {
        $this->log(
            $courseId,
            'section.updated',
            'section',
            'Обновлён раздел «'.$sectionTitle.'»',
            ['section_id' => $sectionId, 'title' => $sectionTitle, 'changes' => $changes],
        );
    }

    public function logSectionDeleted(int $courseId, string $sectionTitle, int $sectionId): void
    {
        $this->log(
            $courseId,
            'section.deleted',
            'section',
            'Удалён раздел «'.$sectionTitle.'»',
            ['section_id' => $sectionId, 'title' => $sectionTitle],
        );
    }

    public function logSectionsReordered(int $courseId, int $moduleId, int $count): void
    {
        $this->log(
            $courseId,
            'section.reordered',
            'section',
            'Изменён порядок разделов модуля ('.$count.')',
            ['module_id' => $moduleId, 'count' => $count],
        );
    }

    public function logSectionSettingsSaved(int $courseId, string $sectionTitle, int $sectionId, string $type): void
    {
        $typeLabel = self::SECTION_TYPE_LABELS[$type] ?? $type;
        $this->log(
            $courseId,
            'section.settings_saved',
            'section',
            'Сохранены настройки раздела «'.$sectionTitle.'» ('.$typeLabel.')',
            ['section_id' => $sectionId, 'title' => $sectionTitle, 'type' => $type],
        );
    }

    public function logSectionPanelSaved(int $courseId, string $sectionTitle, int $sectionId, string $type): void
    {
        $typeLabel = self::SECTION_TYPE_LABELS[$type] ?? $type;
        $this->log(
            $courseId,
            'section.panel_saved',
            'section',
            'Сохранён раздел «'.$sectionTitle.'» ('.$typeLabel.')',
            ['section_id' => $sectionId, 'title' => $sectionTitle, 'type' => $type],
        );
    }

    public function logModulePracticeSaved(int $courseId, int $moduleId, ?int $practiceImageId): void
    {
        $this->log(
            $courseId,
            'module.practice_saved',
            'module',
            'Сохранены настройки практики модуля',
            ['module_id' => $moduleId, 'practice_image_id' => $practiceImageId],
        );
    }

    /**
     * @return list<array{field: string, label: string, old: string, new: string}>
     */
    public function describeModelDirty(Model $model, array $labels): array
    {
        $out = [];
        foreach ($model->getDirty() as $field => $newValue) {
            if (! isset($labels[$field])) {
                continue;
            }
            $oldValue = $model->getOriginal($field);
            $out[] = [
                'field' => (string) $field,
                'label' => (string) $labels[$field],
                'old' => $this->formatScalar($oldValue),
                'new' => $this->formatScalar($newValue),
            ];
        }

        return $out;
    }

    /**
     * @return Collection<int, CourseChangeLog>
     */
    public function entriesForCourse(int $courseId, int $limit = 100): Collection
    {
        if (! Schema::hasTable('course_change_logs')) {
            return collect();
        }

        return CourseChangeLog::query()
            ->where('course_id', $courseId)
            ->with(['portalStaff.learner:id,email,sso_display_name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get();
    }

    public function staffDisplay(?PortalStaff $staff): string
    {
        if ($staff === null) {
            return 'Система';
        }

        $staff->loadMissing('learner');
        $name = $staff->learner ? LearnerDisplay::portalDisplayName($staff->learner) : '';
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($staff->learner?->email ?? ''));
        if ($email !== '') {
            return $email;
        }

        return 'Сотрудник #'.(int) $staff->id;
    }

    public function staffEmail(?PortalStaff $staff): string
    {
        if ($staff === null) {
            return '';
        }

        $staff->loadMissing('learner');

        return trim((string) ($staff->learner?->email ?? ''));
    }

    public function creatorForCourse(Course $course): array
    {
        $course->loadMissing('createdByPortalStaff.learner');
        $staff = $course->createdByPortalStaff;
        if ($staff === null) {
            return [
                'staff_id' => null,
                'name' => '—',
                'email' => '',
                'initials' => '—',
            ];
        }

        $email = $this->staffEmail($staff);
        $name = $this->staffDisplay($staff);

        return [
            'staff_id' => (int) $staff->id,
            'name' => $name,
            'email' => $email,
            'initials' => LearnerDisplay::initials($email, $name !== '—' ? $name : ''),
        ];
    }

    public function areaLabel(string $area): string
    {
        return match ($area) {
            'course' => 'Курс',
            'module' => 'Модуль',
            'section' => 'Раздел',
            'certificate' => 'Сертификат',
            default => $area,
        };
    }

    private function currentStaffId(): int
    {
        $gate = PortalStaffAccess::fromLearnerId((int) session('learner_id', 0));
        if ($gate === null) {
            return 0;
        }

        return (int) $gate->staff()->id;
    }

    private function formatCourseValue(string $field, mixed $value): string
    {
        if ($field === 'is_published' || $field === 'is_archived'
            || str_ends_with($field, '_enabled')) {
            return $this->formatBool($value);
        }

        if ($field === 'certificate_tiers' && is_array($value)) {
            return count($value).' уровн.';
        }

        if ($field === 'summary' || $field === 'audience_plaque_body') {
            $s = trim((string) $value);
            if (mb_strlen($s, 'UTF-8') > 80) {
                return mb_substr($s, 0, 77, 'UTF-8').'…';
            }

            return $s !== '' ? $s : '—';
        }

        return $this->formatScalar($value);
    }

    private function formatBool(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Да' : 'Нет';
    }

    private function formatScalar(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—';
        }

        return trim((string) $value);
    }

    public function logCollaboratorGrantsSynced(Course $course, PortalStaff $staff): void
    {
        $learner = $staff->learner;
        $email = $learner ? (string) $learner->email : ('#'.$staff->id);

        $this->log(
            (int) $course->id,
            'collaborator.grants_changed',
            'collaborator',
            'Обновлены права соавтора '.$email,
            ['portal_staff_id' => (int) $staff->id, 'email' => $email],
        );
    }

    public function logCollaboratorRemoved(Course $course, PortalStaff $staff): void
    {
        $learner = $staff->learner;
        $email = $learner ? (string) $learner->email : ('#'.$staff->id);

        $this->log(
            (int) $course->id,
            'collaborator.removed',
            'collaborator',
            'Соавтор удалён: '.$email,
            ['portal_staff_id' => (int) $staff->id, 'email' => $email],
        );
    }

    public function logCollaboratorAdded(Course $course, PortalStaff $staff): void
    {
        $learner = $staff->learner;
        $email = $learner ? (string) $learner->email : ('#'.$staff->id);

        $this->log(
            (int) $course->id,
            'collaborator.added',
            'collaborator',
            'Добавлен соавтор '.$email,
            ['portal_staff_id' => (int) $staff->id, 'email' => $email],
        );
    }
}
