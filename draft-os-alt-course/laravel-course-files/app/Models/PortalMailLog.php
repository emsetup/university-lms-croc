<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalMailLog extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const TYPE_ACCESS_GRANTED = 'access_granted';

    public const TYPE_STAFF_ADDED = 'staff_added';

    public const TYPE_COLLABORATOR = 'collaborator';

    public const TYPE_SURVEY_INVITE = 'survey_invite';

    public const TYPE_GENERIC = 'generic';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_ACCESS_GRANTED,
        self::TYPE_STAFF_ADDED,
        self::TYPE_COLLABORATOR,
        self::TYPE_SURVEY_INVITE,
        self::TYPE_GENERIC,
    ];

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SENT,
        self::STATUS_FAILED,
        self::STATUS_SKIPPED,
    ];

    protected $table = 'portal_mail_logs';

    protected $fillable = [
        'type',
        'status',
        'to_email',
        'to_name',
        'subject',
        'body_html',
        'body_text',
        'error',
        'meta',
        'learner_id',
        'sent_by_learner_id',
        'sent_by_email',
        'resend_of_id',
        'sent_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'sent_at' => 'datetime',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function sentByLearner(): BelongsTo
    {
        return $this->belongsTo(Learner::class, 'sent_by_learner_id');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_ACCESS_GRANTED => 'Доступ к материалу',
            self::TYPE_STAFF_ADDED => 'Права сотрудника',
            self::TYPE_COLLABORATOR => 'Соавтор курса',
            self::TYPE_SURVEY_INVITE => 'Приглашение на опрос',
            self::TYPE_GENERIC => 'Письмо',
            default => $type,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'В очереди',
            self::STATUS_SENT => 'Отправлено',
            self::STATUS_FAILED => 'Ошибка',
            self::STATUS_SKIPPED => 'Пропущено',
            default => $status,
        };
    }
}
