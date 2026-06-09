<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseSurveyAnswer extends Model
{
    protected $table = 'course_survey_answers';

    protected $fillable = [
        'submission_id',
        'question_id',
        'question_type',
        'answer_text',
        'answer_json',
    ];

    protected function casts(): array
    {
        return [
            'submission_id' => 'int',
            'question_id' => 'int',
            'answer_json' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CourseSurveySubmission::class, 'submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(CourseQuizQuestion::class, 'question_id');
    }
}
