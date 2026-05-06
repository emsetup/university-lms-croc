<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseEnrollment extends Model
{
    protected $fillable = [
        'course_id',
        'learner_id',
        'started_at',
        'last_seen_at',
    ];

    protected $casts = [
        'course_id' => 'int',
        'learner_id' => 'int',
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}

