<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalIncidentLog extends Model
{
    public const SOURCE_HTTP = 'http';

    public const SOURCE_CLIENT = 'client';

    public const SOURCE_EXCEPTION = 'exception';

    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

    public $timestamps = false;

    protected $fillable = [
        'source',
        'status_code',
        'severity',
        'summary',
        'detail',
        'url',
        'http_method',
        'learner_id',
        'user_email',
        'ip',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'status_code' => 'integer',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            self::SOURCE_HTTP => 'HTTP',
            self::SOURCE_CLIENT => 'Браузер',
            self::SOURCE_EXCEPTION => 'Исключение',
            default => $source,
        };
    }
}
