<?php

namespace App\Services;

use App\Models\PortalIncidentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PortalIncidentFeedService
{
    public const SOURCE_LABELS = [
        PortalIncidentLog::SOURCE_HTTP => 'HTTP',
        PortalIncidentLog::SOURCE_CLIENT => 'Браузер',
        PortalIncidentLog::SOURCE_EXCEPTION => 'Исключение',
    ];

    /**
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    public function feed(Request $request): array
    {
        if (! Schema::hasTable('portal_incident_logs')) {
            return ['items' => [], 'has_more' => false];
        }

        $limit = min(200, max(10, (int) $request->query('limit', 80)));
        $cursor = (int) $request->query('before_id', 0);

        $q = PortalIncidentLog::query()->orderByDesc('id');

        if ($cursor > 0) {
            $q->where('id', '<', $cursor);
        }

        $dateFrom = trim((string) $request->query('date_from', ''));
        if ($dateFrom !== '') {
            $q->where('occurred_at', '>=', $dateFrom.' 00:00:00');
        }
        $dateTo = trim((string) $request->query('date_to', ''));
        if ($dateTo !== '') {
            $q->where('occurred_at', '<=', $dateTo.' 23:59:59');
        }

        $user = trim((string) $request->query('user', ''));
        if ($user !== '') {
            $q->where('user_email', 'like', '%'.$user.'%');
        }

        $status = (int) $request->query('status', 0);
        if ($status > 0) {
            $q->where('status_code', $status);
        }

        $sources = array_filter((array) $request->query('sources', []));
        if ($sources !== []) {
            $q->whereIn('source', $sources);
        }

        $rows = $q->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        return [
            'items' => $rows->map(fn (PortalIncidentLog $row) => $this->serializeListItem($row))->values()->all(),
            'has_more' => $hasMore,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(PortalIncidentLog $incident): array
    {
        return [
            'id' => (int) $incident->id,
            'source' => $incident->source,
            'source_label' => PortalIncidentLog::sourceLabel((string) $incident->source),
            'status_code' => $incident->status_code,
            'severity' => $incident->severity,
            'summary' => $incident->summary,
            'detail' => $incident->detail,
            'url' => $incident->url,
            'http_method' => $incident->http_method,
            'user_email' => $incident->user_email,
            'learner_id' => $incident->learner_id,
            'ip' => $incident->ip,
            'user_agent' => $incident->user_agent,
            'occurred_at' => $incident->occurred_at?->toIso8601String(),
        ];
    }

    public function recentEmailSuggestions(int $limit = 40): Collection
    {
        if (! Schema::hasTable('portal_incident_logs')) {
            return collect();
        }

        return PortalIncidentLog::query()
            ->whereNotNull('user_email')
            ->orderByDesc('occurred_at')
            ->limit(500)
            ->pluck('user_email')
            ->unique()
            ->filter()
            ->take($limit)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListItem(PortalIncidentLog $row): array
    {
        return [
            'id' => (int) $row->id,
            'occurred_at' => $row->occurred_at?->format('d.m.Y H:i:s'),
            'occurred_at_iso' => $row->occurred_at?->toIso8601String(),
            'status_code' => $row->status_code,
            'severity' => $row->severity,
            'source' => $row->source,
            'source_label' => PortalIncidentLog::sourceLabel((string) $row->source),
            'summary' => $row->summary,
            'url' => $row->url,
            'user_email' => $row->user_email,
            'http_method' => $row->http_method,
        ];
    }
}
