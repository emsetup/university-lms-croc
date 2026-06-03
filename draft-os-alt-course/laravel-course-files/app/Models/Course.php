<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Course extends Model
{
    public function isLegacyAltCourse(): bool
    {
        // Единственный курс, который продолжает жить на legacy-контенте из config/course.php и config/snippets.
        return $this->slug === 'alt-os-features';
    }

    protected $fillable = [
        'created_by_portal_staff_id',
        'slug',
        'title',
        'summary',
        'tags',
        'is_published',
        'is_archived',
        'sort',
        'default_attempt_limit',
        'default_quiz_time_minutes',
        'default_pass_percent',
        'final_lab_enabled',
        'final_lab_practice_image_id',
        'difficulty_flags_enabled',
        'unlock_all_modules',
        'certificate_enabled',
        'certificate_title',
        'certificate_body',
        'certificate_tiers',
    ];

    protected $casts = [
        'is_published' => 'bool',
        'is_archived' => 'bool',
        'sort' => 'int',
        'tags' => 'array',
        'default_attempt_limit' => 'integer',
        'default_quiz_time_minutes' => 'integer',
        'default_pass_percent' => 'integer',
        'final_lab_enabled' => 'bool',
        'final_lab_practice_image_id' => 'integer',
        'difficulty_flags_enabled' => 'bool',
        'unlock_all_modules' => 'bool',
        'certificate_enabled' => 'bool',
        'certificate_title' => 'string',
        'certificate_body' => 'string',
        'certificate_tiers' => 'array',
    ];

    public function createdByPortalStaff(): BelongsTo
    {
        return $this->belongsTo(PortalStaff::class, 'created_by_portal_staff_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('sort')->orderBy('id');
    }

    public function courseModules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort')->orderBy('id');
    }

    public function finalLabPracticeImage(): BelongsTo
    {
        return $this->belongsTo(PracticeImage::class, 'final_lab_practice_image_id');
    }
}

