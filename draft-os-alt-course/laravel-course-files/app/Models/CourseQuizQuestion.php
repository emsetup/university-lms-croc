<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CourseQuizQuestion extends Model
{
    protected $table = 'course_quiz_questions';

    protected $fillable = [
        'quiz_bank_id',
        'sort',
        'question_text',
        'type',
        'points',
        'settings_json',
    ];

    protected $casts = [
        'quiz_bank_id' => 'int',
        'sort' => 'int',
        'points' => 'int',
        'settings_json' => 'array',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(CourseQuizBank::class, 'quiz_bank_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(CourseQuizOption::class, 'question_id')
            ->orderBy('sort')
            ->orderBy('id');
    }

    public function correctAnswers(): HasMany
    {
        return $this->hasMany(CourseQuizCorrectAnswer::class, 'question_id');
    }

    public function matchPairs(): HasMany
    {
        return $this->hasMany(CourseQuizMatchPair::class, 'question_id')
            ->orderBy('sort')
            ->orderBy('id');
    }
}

