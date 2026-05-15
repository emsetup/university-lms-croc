<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalActivityEvent extends Model
{
    public const TYPE_MAINTENANCE_BLOCKED = 'maintenance_blocked';

    public $timestamps = false;

    protected $fillable = [
        'learner_id',
        'type',
        'path',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
