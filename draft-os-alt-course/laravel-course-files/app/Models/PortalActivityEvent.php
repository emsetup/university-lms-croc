<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalActivityEvent extends Model
{
    public const TYPE_MAINTENANCE_BLOCKED = 'maintenance_blocked';

    public const TYPE_ADMIN_PANEL = 'admin_panel';

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        self::TYPE_MAINTENANCE_BLOCKED => 'Заглушка обновления',
        self::TYPE_ADMIN_PANEL => 'Админ-панель',
    ];

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
