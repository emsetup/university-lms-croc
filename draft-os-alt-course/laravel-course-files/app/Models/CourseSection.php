<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class CourseSection extends Model
{
    public const TYPE_TEXT = 'text';

    public const TYPE_QUIZ = 'quiz';

    public const TYPE_PRACTICE = 'practice';

    public const TYPE_EXAM = 'exam';

    public const TYPE_SURVEY = 'survey';

    public const VIEW_AUDIENCE_ALL = 'all';

    public const VIEW_AUDIENCE_RESTRICTED = 'restricted';

    protected $fillable = [
        'course_id',
        'course_module_id',
        'type',
        'title',
        'sort',
        'is_enabled',
        'view_audience',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'int',
            'course_module_id' => 'int',
            'sort' => 'int',
            'is_enabled' => 'bool',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function sectionSettings(): HasOne
    {
        return $this->hasOne(CourseSectionSetting::class, 'course_section_id');
    }

    /** Уникальный ключ этапа в порядке модуля (один раздел — один ключ). */
    public function backendStepKey(): string
    {
        return 's'.$this->id;
    }

    public static function idFromStepKey(string $key): ?int
    {
        if (preg_match('/^s(\d+)$/', $key, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** Ключ типа для legacy-прогресса и весов баллов. */
    public function legacyTypeKey(): string
    {
        return match ($this->type) {
            self::TYPE_TEXT => 'theory',
            self::TYPE_QUIZ => 'theory_quiz',
            self::TYPE_PRACTICE => 'practice',
            self::TYPE_EXAM => 'module_exam',
            self::TYPE_SURVEY => 'survey',
            default => 'theory',
        };
    }

    public function quizBankKind(): ?string
    {
        return match ($this->type) {
            self::TYPE_QUIZ => 'theory_quiz',
            self::TYPE_EXAM => 'module_exam',
            self::TYPE_SURVEY => 'survey',
            default => null,
        };
    }

    public function learnerRouteName(): ?string
    {
        return match ($this->type) {
            self::TYPE_TEXT => 'course.module.theory',
            self::TYPE_QUIZ => 'course.module.theory-quiz',
            self::TYPE_PRACTICE => 'course.module.practice',
            self::TYPE_EXAM => 'course.module.exam',
            self::TYPE_SURVEY => 'course.module.section.survey',
            default => null,
        };
    }

    /**
     * @return array{course: int, module: int}|array{course: int, module: int, section: int}
     */
    public function learnerRouteParams(int $courseId, int $moduleSequence, ?int $sectionSequence = null): array
    {
        if ($this->type === self::TYPE_SURVEY) {
            $sectionSequence ??= app(\App\Services\CourseSectionService::class)->sequenceForSection($this);

            return \App\Support\LearnerRoute::section($courseId, $moduleSequence, $sectionSequence);
        }

        return \App\Support\LearnerRoute::hub($courseId, $moduleSequence);
    }

    public static function typesList(): array
    {
        return [
            self::TYPE_TEXT => 'Текст (Markdown)',
            self::TYPE_QUIZ => 'Тест (вопросы)',
            self::TYPE_PRACTICE => 'Практика (Docker)',
            self::TYPE_EXAM => 'Экзамен (по одному вопросу)',
            self::TYPE_SURVEY => 'Опрос (сбор данных)',
        ];
    }
}
