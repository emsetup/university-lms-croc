<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CourseSurveySubmission extends Model
{
    protected $table = 'course_survey_submissions';

    protected $fillable = [
        'course_id',
        'course_module_id',
        'course_section_id',
        'learner_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'int',
            'course_module_id' => 'int',
            'course_section_id' => 'int',
            'learner_id' => 'int',
            'submitted_at' => 'datetime',
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

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CourseSurveyAnswer::class, 'submission_id');
    }
}
