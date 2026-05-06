<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseSectionSetting extends Model
{
    protected $table = 'course_section_settings';

    protected $fillable = [
        'course_section_id',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'course_section_id' => 'int',
            'settings' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }
}
