<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class PortalLearnerGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'color',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'int',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Learner::class, 'portal_learner_group_members')
            ->withTimestamps();
    }
}
