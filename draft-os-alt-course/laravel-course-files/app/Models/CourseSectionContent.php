<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseSectionContent extends Model
{
    protected $table = 'course_section_contents';

    protected $fillable = [
        'course_section_id',
        'body_markdown',
    ];

    protected function casts(): array
    {
        return [
            'course_section_id' => 'int',
        ];
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }
}
