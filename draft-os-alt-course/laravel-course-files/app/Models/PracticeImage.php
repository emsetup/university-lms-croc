<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PracticeImage extends Model
{
    protected $table = 'practice_images';

    protected $fillable = [
        'created_by_portal_staff_id',
        'title',
        'description',
        'slug',
        'docker_tag',
        'base_template',
        'base_os',
        'base_image_ref',
        'package_add',
        'package_remove',
        'features',
        'startup_script_text',
        'dockerfile_text',
        'check_script_text',
        'is_built',
        'last_build_status',
        'last_build_log',
        'last_built_at',
        'export_path',
    ];

    protected function casts(): array
    {
        return [
            'is_built' => 'bool',
            'last_built_at' => 'datetime',
            'package_add' => 'array',
            'package_remove' => 'array',
            'features' => 'array',
        ];
    }

    public function createdByPortalStaff(): BelongsTo
    {
        return $this->belongsTo(PortalStaff::class, 'created_by_portal_staff_id');
    }

    public function modulePracticeSettings(): HasMany
    {
        return $this->hasMany(CourseModulePracticeSetting::class, 'practice_image_id');
    }
}

