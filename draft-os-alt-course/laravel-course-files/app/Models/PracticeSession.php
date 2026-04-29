<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeSession extends Model
{
    protected $fillable = [
        'learner_id',
        'module_id',
        'daemon_lab_id',
        'status',
        'terminal_url',
        'last_check_log',
        'last_check_passed',
        'last_check_score',
        'last_check_max_score',
        'last_check_hints',
        'last_check_at',
        'accepted_at',
        'accepted_check_log',
        'accepted_practice_score',
        'terminal_snapshots',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_check_passed' => 'boolean',
            'last_check_hints' => 'array',
            'terminal_snapshots' => 'array',
            'last_check_at' => 'datetime',
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['provisioning', 'ready', 'check_pass', 'check_partial', 'check_fail'], true)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
