<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Course extends Model
{
    public function isLegacyAltCourse(): bool
    {
        // Единственный курс, который продолжает жить на legacy-контенте из config/course.php и config/snippets.
        return $this->slug === 'alt-os-features';
    }

    protected $fillable = [
        'slug',
        'title',
        'summary',
        'is_published',
        'is_archived',
        'sort',
    ];

    protected $casts = [
        'is_published' => 'bool',
        'is_archived' => 'bool',
        'sort' => 'int',
    ];

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
}

