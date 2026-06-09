<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CourseQuizBank extends Model
{
    protected $table = 'course_quiz_banks';

    protected $fillable = [
        'course_id',
        'course_module_id',
        'course_section_id',
        'kind',
        'pass_percent',
        'time_limit_minutes',
        'attempt_limit',
        'shuffle',
        'one_by_one',
        'breakdown_visible_minutes',
        'penalties_json',
    ];

    protected $casts = [
        'course_id' => 'int',
        'course_module_id' => 'int',
        'course_section_id' => 'int',
        'pass_percent' => 'int',
        'time_limit_minutes' => 'int',
        'attempt_limit' => 'int',
        'shuffle' => 'bool',
        'one_by_one' => 'bool',
        'breakdown_visible_minutes' => 'int',
        'penalties_json' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CourseQuizQuestion::class, 'quiz_bank_id')
            ->orderBy('sort')
            ->orderBy('id');
    }
}

