<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseQuizCorrectAnswer extends Model
{
    protected $table = 'course_quiz_correct_answers';

    protected $fillable = [
        'question_id',
        'option_id',
    ];

    protected $casts = [
        'question_id' => 'int',
        'option_id' => 'int',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(CourseQuizQuestion::class, 'question_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(CourseQuizOption::class, 'option_id');
    }
}

