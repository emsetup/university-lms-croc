<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Факт прохождения опроса (для запрета повторной сдачи и прогресса).
 * Не связан с конкретными ответами — при анонимном опросе ответы живут без learner_id.
 */
final class CourseSurveyParticipant extends Model
{
    protected $table = 'course_survey_participants';

    protected $fillable = [
        'course_section_id',
        'learner_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'course_section_id' => 'int',
            'learner_id' => 'int',
            'submitted_at' => 'datetime',
        ];
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
