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

    protected $fillable = [
        'course_id',
        'course_module_id',
        'type',
        'title',
        'sort',
        'is_enabled',
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

    public function backendStepKey(): string
    {
        return match ($this->type) {
            self::TYPE_TEXT => 'theory',
            self::TYPE_QUIZ => 'theory_quiz',
            self::TYPE_PRACTICE => 'practice',
            self::TYPE_EXAM => 'module_exam',
            default => 'theory',
        };
    }

    public static function typesList(): array
    {
        return [
            self::TYPE_TEXT => 'Текст (Markdown)',
            self::TYPE_QUIZ => 'Тест (вопросы)',
            self::TYPE_PRACTICE => 'Практика (Docker)',
            self::TYPE_EXAM => 'Экзамен (по одному вопросу)',
        ];
    }
}
