<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseQuizOption extends Model
{
    protected $table = 'course_quiz_options';

    protected $fillable = [
        'question_id',
        'sort',
        'option_text',
    ];

    protected $casts = [
        'question_id' => 'int',
        'sort' => 'int',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(CourseQuizQuestion::class, 'question_id');
    }
}

