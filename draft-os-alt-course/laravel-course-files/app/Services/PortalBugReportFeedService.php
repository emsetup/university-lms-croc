<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PortalBugReport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PortalBugReportFeedService
{
    public const TYPE_LABELS = [
        PortalBugReport::TYPE_BUG => 'Ошибка',
        PortalBugReport::TYPE_SUGGESTION => 'Предложение',
        PortalBugReport::TYPE_OTHER => 'Другое',
    ];

    public const SCOPE_LABELS = [
        PortalBugReport::SCOPE_PORTAL => 'Портал',
        PortalBugReport::SCOPE_COURSE => 'Курс',
    ];

    public const STATUS_LABELS = [
        PortalBugReport::STATUS_NEW => 'Новое',
        PortalBugReport::STATUS_IN_PROGRESS => 'В работе',
        PortalBugReport::STATUS_DONE => 'Готово',
        PortalBugReport::STATUS_DISMISSED => 'Отклонено',
    ];

    /**
     * @param  list<int>|null  $restrictCourseIds  null = все; список = только тикеты этих курсов
     */
    private function applyInboxScope($query, ?array $restrictCourseIds)
    {
        if ($restrictCourseIds === null) {
            return $query;
        }
        if ($restrictCourseIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('scope', PortalBugReport::SCOPE_COURSE)
            ->whereIn('course_id', $restrictCourseIds);
    }

    /**
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    public function feed(Request $request, ?array $restrictCourseIds = null): array
    {
        if (! Schema::hasTable('portal_bug_reports')) {
            return ['items' => [], 'has_more' => false];
        }

        $limit = min(200, max(10, (int) $request->query('limit', 80)));
        $cursor = (int) $request->query('before_id', 0);

        $q = PortalBugReport::query()->with('course:id,title')->orderByDesc('id');
        $this->applyInboxScope($q, $restrictCourseIds);

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
            $q->where('user_email', 'like', '%'.$user.'%');
        }

        $types = array_filter((array) $request->query('types', []));
        if ($types !== []) {
            $q->whereIn('type', $types);
        }

        $scopes = array_filter((array) $request->query('scopes', []));
        if ($scopes !== []) {
            $q->whereIn('scope', $scopes);
        }

        $statuses = array_filter((array) $request->query('statuses', []));
        if ($statuses !== []) {
            $q->whereIn('status', $statuses);
        }

        $qText = trim((string) $request->query('q', ''));
        if ($qText !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $qText).'%';
            $q->where(function ($w) use ($like) {
                $w->where('message', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('page_url', 'like', $like);
            });
        }

        $rows = $q->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        return [
            'items' => $rows->map(fn (PortalBugReport $row) => $this->serializeListItem($row))->values()->all(),
            'has_more' => $hasMore,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(PortalBugReport $report): array
    {
        $report->loadMissing('course:id,title');
        $shots = [];
        foreach ((array) ($report->screenshots ?? []) as $i => $shot) {
            if (! is_array($shot) || empty($shot['path'])) {
                continue;
            }
            $shots[] = [
                'index' => $i,
                'name' => (string) ($shot['name'] ?? 'screenshot'),
                'mime' => (string) ($shot['mime'] ?? 'image/png'),
                'bytes' => (int) ($shot['bytes'] ?? 0),
                'url' => route('admin.bugs.screenshot', ['bug' => $report->id, 'index' => $i]),
            ];
        }

        $scope = (string) ($report->scope ?: PortalBugReport::SCOPE_PORTAL);

        return [
            'id' => (int) $report->id,
            'ticket' => '#'.$report->id,
            'type' => (string) $report->type,
            'type_label' => PortalBugReport::typeLabel((string) $report->type),
            'scope' => $scope,
            'scope_label' => PortalBugReport::scopeLabel($scope),
            'course_id' => $report->course_id,
            'course_title' => $report->course?->title,
            'status' => (string) $report->status,
            'status_label' => PortalBugReport::statusLabel((string) $report->status),
            'title' => $report->title,
            'message' => $report->message,
            'page_url' => $report->page_url,
            'page_title' => $report->page_title,
            'user_email' => $report->user_email,
            'learner_id' => $report->learner_id,
            'user_agent' => $report->user_agent,
            'ip' => $report->ip,
            'screenshot_count' => (int) $report->screenshot_count,
            'screenshots' => $shots,
            'admin_note' => $report->admin_note,
            'created_at' => $report->created_at?->toIso8601String(),
            'created_at_label' => $report->created_at?->timezone((string) config('portal.display_timezone', 'Europe/Moscow'))->format('d.m.Y H:i:s'),
            'resolved_at' => $report->resolved_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>|null  $restrictCourseIds
     * @return array{total: int, new: int, new_24h: int, open: int}
     */
    public function stats(?array $restrictCourseIds = null): array
    {
        if (! Schema::hasTable('portal_bug_reports')) {
            return ['total' => 0, 'new' => 0, 'new_24h' => 0, 'open' => 0];
        }

        $base = PortalBugReport::query();
        $this->applyInboxScope($base, $restrictCourseIds);

        return [
            'total' => (int) (clone $base)->count(),
            'new' => (int) (clone $base)->where('status', PortalBugReport::STATUS_NEW)->count(),
            'new_24h' => (int) (clone $base)->where('created_at', '>=', now()->subDay())->count(),
            'open' => (int) (clone $base)->whereIn('status', [
                PortalBugReport::STATUS_NEW,
                PortalBugReport::STATUS_IN_PROGRESS,
            ])->count(),
        ];
    }

    public function recentEmailSuggestions(int $limit = 40): Collection
    {
        if (! Schema::hasTable('portal_bug_reports')) {
            return collect();
        }

        return PortalBugReport::query()
            ->whereNotNull('user_email')
            ->orderByDesc('created_at')
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
    private function serializeListItem(PortalBugReport $row): array
    {
        $tz = (string) config('portal.display_timezone', 'Europe/Moscow');
        $preview = trim((string) ($row->title ?: $row->message));
        $preview = preg_replace('/\s+/u', ' ', $preview) ?? $preview;
        $preview = Str::limit($preview, 140, '…');
        $scope = (string) ($row->scope ?: PortalBugReport::SCOPE_PORTAL);

        return [
            'id' => (int) $row->id,
            'ticket' => '#'.$row->id,
            'created_at' => $row->created_at?->timezone($tz)->format('d.m.Y H:i:s'),
            'created_at_iso' => $row->created_at?->toIso8601String(),
            'type' => (string) $row->type,
            'type_label' => PortalBugReport::typeLabel((string) $row->type),
            'scope' => $scope,
            'scope_label' => PortalBugReport::scopeLabel($scope),
            'course_id' => $row->course_id,
            'course_title' => $row->course?->title,
            'status' => (string) $row->status,
            'status_label' => PortalBugReport::statusLabel((string) $row->status),
            'preview' => $preview,
            'page_url' => $row->page_url,
            'user_email' => $row->user_email,
            'screenshot_count' => (int) $row->screenshot_count,
        ];
    }
}
