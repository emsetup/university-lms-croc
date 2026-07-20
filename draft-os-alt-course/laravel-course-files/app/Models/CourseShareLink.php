<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseShareLink extends Model
{
    public const TARGET_COURSE = 'course';

    public const TARGET_MODULE = 'module';

    public const TARGET_SECTION = 'section';

    protected $fillable = [
        'course_id',
        'target_type',
        'target_id',
        'token',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'int',
            'target_id' => 'int',
            'is_active' => 'bool',
            'expires_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
