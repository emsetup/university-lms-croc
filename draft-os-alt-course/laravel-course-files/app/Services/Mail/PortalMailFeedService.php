<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\PortalMailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PortalMailFeedService
{
    public const TYPE_LABELS = [
        PortalMailLog::TYPE_ACCESS_GRANTED => 'Доступ',
        PortalMailLog::TYPE_STAFF_ADDED => 'Сотрудник',
        PortalMailLog::TYPE_COLLABORATOR => 'Соавтор',
        PortalMailLog::TYPE_SURVEY_INVITE => 'Опрос',
        PortalMailLog::TYPE_GENERIC => 'Письмо',
    ];

    public const STATUS_LABELS = [
        PortalMailLog::STATUS_SENT => 'Отправлено',
        PortalMailLog::STATUS_FAILED => 'Ошибка',
        PortalMailLog::STATUS_PENDING => 'В очереди',
        PortalMailLog::STATUS_SKIPPED => 'Пропущено',
    ];

    /**
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    public function feed(Request $request): array
    {
        if (! Schema::hasTable('portal_mail_logs')) {
            return ['items' => [], 'has_more' => false];
        }

        $limit = min(200, max(10, (int) $request->query('limit', 80)));
        $cursor = (int) $request->query('before_id', 0);

        $q = PortalMailLog::query()->orderByDesc('id');

        if ($cursor > 0) {
            $q->where('id', '<', $cursor);
        }

        $dateFrom = trim((string) $request->query('date_from', ''));
        if ($dateFrom !== '') {
            $q->where('created_at', '>=', $dateFrom.' 00:00:00');
        }
        $dateTo = trim((string) $request->query('date_to', ''));
        if ($dateTo !== '') {
            $q->where('created_at', '<=', $dateTo.' 23:59:59');
        }

        $user = trim((string) $request->query('user', ''));
        if ($user !== '') {
            $q->where(function ($w) use ($user): void {
                $w->where('to_email', 'like', '%'.$user.'%')
                    ->orWhere('sent_by_email', 'like', '%'.$user.'%');
            });
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && in_array($status, PortalMailLog::STATUSES, true)) {
            $q->where('status', $status);
        }

        $types = array_filter((array) $request->query('types', []));
        if ($types !== []) {
            $q->whereIn('type', $types);
        }

        $rows = $q->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $tz = (string) config('portal.display_timezone', 'Europe/Moscow');

        return [
            'items' => $rows->map(fn (PortalMailLog $row) => $this->serializeListItem($row, $tz))->values()->all(),
            'has_more' => $hasMore,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(PortalMailLog $log): array
    {
        $tz = (string) config('portal.display_timezone', 'Europe/Moscow');

        return [
            'id' => (int) $log->id,
            'type' => (string) $log->type,
            'type_label' => PortalMailLog::typeLabel((string) $log->type),
            'status' => (string) $log->status,
            'status_label' => PortalMailLog::statusLabel((string) $log->status),
            'to_email' => (string) $log->to_email,
            'to_name' => $log->to_name,
            'subject' => (string) $log->subject,
            'body_html' => (string) $log->body_html,
            'body_text' => $log->body_text,
            'error' => $log->error,
            'meta' => $log->meta,
            'learner_id' => $log->learner_id,
            'sent_by_email' => $log->sent_by_email,
            'resend_of_id' => $log->resend_of_id,
            'created_at' => $log->created_at?->timezone($tz)->format('d.m.Y H:i:s'),
            'sent_at' => $log->sent_at?->timezone($tz)->format('d.m.Y H:i:s'),
            'can_resend' => true,
        ];
    }

    public function recentEmailSuggestions(int $limit = 40): Collection
    {
        if (! Schema::hasTable('portal_mail_logs')) {
            return collect();
        }

        return PortalMailLog::query()
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('to_email')
            ->unique()
            ->filter()
            ->take($limit)
            ->values();
    }

    /**
     * @return array{total: int, sent_24h: int, failed_24h: int}
     */
    public function stats(): array
    {
        if (! Schema::hasTable('portal_mail_logs')) {
            return ['total' => 0, 'sent_24h' => 0, 'failed_24h' => 0];
        }

        $since = now()->subDay();

        return [
            'total' => (int) PortalMailLog::query()->count(),
            'sent_24h' => (int) PortalMailLog::query()
                ->where('status', PortalMailLog::STATUS_SENT)
                ->where('created_at', '>=', $since)
                ->count(),
            'failed_24h' => (int) PortalMailLog::query()
                ->where('status', PortalMailLog::STATUS_FAILED)
                ->where('created_at', '>=', $since)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListItem(PortalMailLog $row, string $tz): array
    {
        return [
            'id' => (int) $row->id,
            'created_at' => $row->created_at?->timezone($tz)->format('d.m.Y H:i:s'),
            'created_at_iso' => $row->created_at?->toIso8601String(),
            'type' => (string) $row->type,
            'type_label' => PortalMailLog::typeLabel((string) $row->type),
            'status' => (string) $row->status,
            'status_label' => PortalMailLog::statusLabel((string) $row->status),
            'to_email' => (string) $row->to_email,
            'subject' => (string) $row->subject,
            'sent_by_email' => $row->sent_by_email,
            'error' => $row->error ? mb_strimwidth((string) $row->error, 0, 120, '…') : null,
        ];
    }
}
