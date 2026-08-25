<?php

namespace App\Models;

use App\Support\PracticeTerminalUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeSession extends Model
{
    /** module_id в practice_sessions: 0 = финальная лаба; иначе course_modules.id */
    public const FINAL_LAB_SESSION_MODULE_ID = 0;

    protected $fillable = [
        'learner_id',
        'course_id',
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
            'course_id' => 'int',
            'module_id' => 'int',
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

    public function getTerminalUrlAttribute(?string $value): ?string
    {
        return PracticeTerminalUrl::toHttpsProxy($value);
    }

    public function setTerminalUrlAttribute(?string $value): void
    {
        $this->attributes['terminal_url'] = PracticeTerminalUrl::toHttpsProxy($value);
    }
}
