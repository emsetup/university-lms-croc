<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class CourseLearnerGroup extends Model
{
    protected $fillable = [
        'course_id',
        'name',
        'description',
        'color',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'int',
            'sort' => 'int',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Learner::class, 'course_learner_group_members')
            ->withTimestamps();
    }
}
