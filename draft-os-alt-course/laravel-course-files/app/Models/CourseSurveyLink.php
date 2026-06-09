<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseSurveyLink extends Model
{
    protected $fillable = [
        'course_section_id',
        'token',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'course_section_id' => 'int',
            'is_active' => 'bool',
            'expires_at' => 'datetime',
        ];
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
