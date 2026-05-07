<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseModulePracticeSetting extends Model
{
    protected $table = 'course_module_practice_settings';

    protected $fillable = [
        'course_module_id',
        'practice_image_id',
        'daemon_image_key_override',
    ];

    protected function casts(): array
    {
        return [
            'course_module_id' => 'int',
            'practice_image_id' => 'int',
            'daemon_image_key_override' => 'int',
        ];
    }

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function practiceImage(): BelongsTo
    {
        return $this->belongsTo(PracticeImage::class, 'practice_image_id');
    }
}

