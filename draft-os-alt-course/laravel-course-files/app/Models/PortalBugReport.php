<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalBugReport extends Model
{
    public const TYPE_BUG = 'bug';

    public const TYPE_SUGGESTION = 'suggestion';

    public const TYPE_OTHER = 'other';

    public const SCOPE_PORTAL = 'portal';

    public const SCOPE_COURSE = 'course';

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_DISMISSED = 'dismissed';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_BUG,
        self::TYPE_SUGGESTION,
        self::TYPE_OTHER,
    ];

    /** @var list<string> */
    public const SCOPES = [
        self::SCOPE_PORTAL,
        self::SCOPE_COURSE,
    ];

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_DISMISSED,
    ];

    protected $table = 'portal_bug_reports';

    protected $fillable = [
        'type',
        'scope',
        'course_id',
        'status',
        'title',
        'message',
        'page_url',
        'page_title',
        'learner_id',
        'user_email',
        'user_agent',
        'ip',
        'screenshots',
        'screenshot_count',
        'admin_note',
        'resolved_at',
    ];

    protected $casts = [
        'screenshots' => 'array',
        'screenshot_count' => 'integer',
        'course_id' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_BUG => 'Ошибка',
            self::TYPE_SUGGESTION => 'Предложение',
            self::TYPE_OTHER => 'Другое',
            default => $type,
        };
    }

    public static function scopeLabel(string $scope): string
    {
        return match ($scope) {
            self::SCOPE_PORTAL => 'Портал',
            self::SCOPE_COURSE => 'Курс',
            default => $scope,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_NEW => 'Новое',
            self::STATUS_IN_PROGRESS => 'В работе',
            self::STATUS_DONE => 'Готово',
            self::STATUS_DISMISSED => 'Отклонено',
            default => $status,
        };
    }
}
