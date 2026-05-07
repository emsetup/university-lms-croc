<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseModuleContent extends Model
{
    protected $table = 'course_module_contents';

    protected $fillable = [
        'course_module_id',
        'theory_markdown',
        'practice_markdown',
    ];

    protected function casts(): array
    {
        return [
            'course_module_id' => 'int',
        ];
    }

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }
}

