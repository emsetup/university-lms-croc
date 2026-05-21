<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\FinalLabResult;
use App\Models\PortalActivityEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PortalActivityFeedService
{
    public const KIND_COURSE_VISIT = 'course_visit';

    public const KIND_COURSE_START = 'course_start';

    public const KIND_CERTIFICATE = 'certificate';

    public const KIND_FINAL_LAB = 'final_lab';

    public const KIND_MAINTENANCE = 'maintenance_blocked';

    public const KIND_ADMIN_PANEL = 'admin_panel';

    /** Окно (мин.), в котором действия одного пользователя считаются одним сеансом. */
    private const GROUP_SESSION_MINUTES = 60;

    /** @var array<string, string> */
    public const KIND_LABELS = [
        self::KIND_COURSE_VISIT => 'Заход в курс',
        self::KIND_COURSE_START => 'Начало курса',
        self::KIND_CERTIFICATE => 'Сертификат',
        self::KIND_FINAL_LAB => 'Итоговая лабораторная',
        self::KIND_MAINTENANCE => 'Заглушка обновления',
        self::KIND_ADMIN_PANEL => 'Админ-панель',
    ];

    /**
     * @param  array<int, int>|null  $scopedIds
     * @param  array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     user?: string|null,
     *     kinds?: array<int, string>|null,
     *     since?: string|null,
     * }  $filters
     * @return Collection<int, array{
     *     id: string,
     *     at: Carbon,
     *     email: string,
     *     text: string,
     *     kind: string,
     *     kind_label: string,
     *     active_today: bool,
     * }>
     */
    public function feed(?array $scopedIds, array $filters = [], int $limit = 50): Collection
    {
        if (is_array($scopedIds) && $scopedIds === []) {
            return collect();
        }

        $rows = $this->collectRows($scopedIds);
        $rows = $this->applyFilters($rows, $filters);
        $rows = $this->groupRows($rows);

        return $rows
            ->sortByDesc(fn (array $r) => $r['at']->timestamp)
            ->values()
            ->take(max(1, min($limit, 500)));
    }

    /**
     * @param  array<int, int>|null  $scopedIds
     * @return list<string>
     */
    public function suggestEmails(?array $scopedIds, int $limit = 80): array
    {
        if (is_array($scopedIds) && $scopedIds === []) {
            return [];
        }

        $emails = [];
        foreach ($this->collectRows($scopedIds, 200) as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $emails[$email] = true;
            }
            if (count($emails) >= $limit) {
                break;
            }
        }

        $list = array_keys($emails);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * @param  array<int, int>|null  $scopedIds
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRows(?array $scopedIds, int $perSource = 80): Collection
    {
        $rows = collect();

        if (Schema::hasTable('course_enrollments')) {
            $q = CourseEnrollment::query()
                ->with(['learner:id,email', 'course:id,title,slug']);
            if (is_array($scopedIds)) {
                $q->whereIn('course_id', $scopedIds);
            }
            foreach ($q->orderByDesc(DB::raw('COALESCE(last_seen_at, started_at, updated_at)'))->limit($perSource)->get() as $en) {
                $at = $en->last_seen_at ?? $en->started_at ?? $en->updated_at;
                if ($at === null) {
                    continue;
                }
                $title = (string) ($en->course?->title ?? 'курс');
                if ($en->last_seen_at !== null) {
                    $kind = self::KIND_COURSE_VISIT;
                    $text = 'Заходил в курс «'.$title.'»';
                } elseif ($en->started_at !== null) {
                    $kind = self::KIND_COURSE_START;
                    $text = 'Начал курс «'.$title.'»';
                } else {
                    $kind = self::KIND_COURSE_VISIT;
                    $text = 'Активность в курсе «'.$title.'»';
                }
                $rows->push($this->row(
                    'en:'.(int) $en->id,
                    $at,
                    (string) ($en->learner?->email ?? ''),
                    $text,
                    $kind,
                    $en->last_seen_at !== null && $en->last_seen_at->isToday(),
                ));
            }
        }

        if (Schema::hasTable('final_lab_results')) {
            $q = FinalLabResult::query()
                ->with(['learner:id,email', 'course:id,title,slug']);
            if (is_array($scopedIds)) {
                $q->whereIn('course_id', $scopedIds);
            }
            foreach ($q->orderByDesc(DB::raw('COALESCE(certificate_issued_at, completed_at, updated_at)'))->limit($perSource)->get() as $fl) {
                $at = $fl->certificate_issued_at ?? $fl->completed_at ?? $fl->updated_at;
                if ($at === null) {
                    continue;
                }
                $title = (string) ($fl->course?->title ?? 'курс');
                $hasCert = filled($fl->certificate_full_name) && filled($fl->certificate_serial);
                if ($hasCert) {
                    $kind = self::KIND_CERTIFICATE;
                    $text = 'Получил сертификат по курсу «'.$title.'»';
                } elseif ($fl->passed) {
                    $kind = self::KIND_FINAL_LAB;
                    $text = 'Прошёл итоговую лабораторную по курсу «'.$title.'»';
                } else {
                    continue;
                }
                $rows->push($this->row(
                    'fl:'.(int) $fl->id,
                    $at,
                    (string) ($fl->learner?->email ?? ''),
                    $text,
                    $kind,
                    $at instanceof Carbon && $at->isToday(),
                ));
            }
        }

        if (Schema::hasTable('portal_activity_events')) {
            $scopedLearnerIds = is_array($scopedIds)
                ? $this->learnerIdsForCourses($scopedIds)
                : null;

            if (! is_array($scopedIds)) {
                $q = PortalActivityEvent::query()
                    ->with('learner:id,email')
                    ->whereIn('type', [
                        PortalActivityEvent::TYPE_MAINTENANCE_BLOCKED,
                        PortalActivityEvent::TYPE_ADMIN_PANEL,
                    ])
                    ->orderByDesc('occurred_at')
                    ->limit($perSource);

                foreach ($q->get() as $ev) {
                    $rows->push($this->rowFromPortalEvent($ev));
                }
            } elseif ($scopedLearnerIds !== []) {
                $q = PortalActivityEvent::query()
                    ->with('learner:id,email')
                    ->where('type', PortalActivityEvent::TYPE_MAINTENANCE_BLOCKED)
                    ->whereIn('learner_id', $scopedLearnerIds)
                    ->orderByDesc('occurred_at')
                    ->limit($perSource);

                foreach ($q->get() as $ev) {
                    $row = $this->rowFromPortalEvent($ev);
                    if ($row !== null) {
                        $rows->push($row);
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $rows, array $filters): Collection
    {
        $dateFrom = $this->parseDate($filters['date_from'] ?? null, true);
        $dateTo = $this->parseDate($filters['date_to'] ?? null, false);
        $user = mb_strtolower(trim((string) ($filters['user'] ?? '')));
        $kinds = array_values(array_filter((array) ($filters['kinds'] ?? [])));
        $since = $this->parseSince($filters['since'] ?? null);

        if ($dateFrom !== null) {
            $rows = $rows->filter(fn (array $r) => $r['at']->gte($dateFrom));
        }
        if ($dateTo !== null) {
            $rows = $rows->filter(fn (array $r) => $r['at']->lte($dateTo));
        }
        if ($user !== '') {
            $rows = $rows->filter(function (array $r) use ($user) {
                $email = mb_strtolower((string) ($r['email'] ?? ''));

                return $email !== '' && str_contains($email, $user);
            });
        }
        if ($kinds !== []) {
            $allowed = array_flip($kinds);
            $rows = $rows->filter(fn (array $r) => isset($allowed[$r['kind']]));
        }
        if ($since !== null) {
            $rows = $rows->filter(fn (array $r) => $r['at']->gt($since));
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rowFromPortalEvent(PortalActivityEvent $ev): ?array
    {
        $at = $ev->occurred_at;
        if ($at === null) {
            return null;
        }

        if ($ev->type === PortalActivityEvent::TYPE_ADMIN_PANEL) {
            $path = (string) ($ev->path ?? '');

            return $this->row(
                'ev:'.(int) $ev->id,
                $at,
                (string) ($ev->learner?->email ?? ''),
                $this->adminPanelText($path),
                self::KIND_ADMIN_PANEL,
                $at->isToday(),
                ['route' => $this->humanizeAdminPath($path)],
            );
        }

        if ($ev->type === PortalActivityEvent::TYPE_MAINTENANCE_BLOCKED) {
            return $this->row(
                'ev:'.(int) $ev->id,
                $at,
                (string) ($ev->learner?->email ?? ''),
                'Попал на заглушку обновления портала',
                self::KIND_MAINTENANCE,
                $at->isToday(),
            );
        }

        return null;
    }

    /**
     * @param  array<int, int>  $courseIds
     * @return list<int>
     */
    private function learnerIdsForCourses(array $courseIds): array
    {
        if ($courseIds === [] || ! Schema::hasTable('course_enrollments')) {
            return [];
        }

        return CourseEnrollment::query()
            ->whereIn('course_id', $courseIds)
            ->distinct()
            ->pluck('learner_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function row(string $id, Carbon $at, string $email, string $text, string $kind, bool $activeToday, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'at' => $at,
            'email' => $email,
            'text' => $text,
            'kind' => $kind,
            'kind_label' => self::KIND_LABELS[$kind] ?? $kind,
            'active_today' => $activeToday,
            'grouped' => false,
            'steps' => [],
            'step_count' => 0,
        ], $extra);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function groupRows(Collection $rows): Collection
    {
        $sorted = $rows->sortByDesc(fn (array $r) => $r['at']->timestamp)->values();
        $out = collect();
        $count = $sorted->count();
        $i = 0;

        while ($i < $count) {
            $current = $sorted[$i];
            if (! $this->isGroupableKind((string) ($current['kind'] ?? ''))) {
                $out->push($current);
                $i++;

                continue;
            }

            $email = (string) ($current['email'] ?? '');
            $kind = (string) ($current['kind'] ?? '');
            $group = [$current];
            $j = $i + 1;

            while ($j < $count) {
                $next = $sorted[$j];
                if ((string) ($next['email'] ?? '') !== $email || (string) ($next['kind'] ?? '') !== $kind) {
                    break;
                }
                $oldest = $group[count($group) - 1]['at'];
                if (! $oldest instanceof Carbon || ! $next['at'] instanceof Carbon) {
                    break;
                }
                if ($oldest->diffInMinutes($next['at']) > self::GROUP_SESSION_MINUTES) {
                    break;
                }
                $group[] = $next;
                $j++;
            }

            $out->push(count($group) > 1 ? $this->mergeGroup($group) : $current);
            $i = $j;
        }

        return $out;
    }

    private function isGroupableKind(string $kind): bool
    {
        return $kind === self::KIND_ADMIN_PANEL;
    }

    /**
     * @param  list<array<string, mixed>>  $items  от новых к старым
     * @return array<string, mixed>
     */
    private function mergeGroup(array $items): array
    {
        usort($items, static fn (array $a, array $b) => $a['at'] <=> $b['at']);
        $newest = $items[count($items) - 1];
        $kind = (string) ($newest['kind'] ?? '');
        $ids = array_map(static fn (array $r) => (string) ($r['id'] ?? ''), $items);
        $steps = [];
        $tz = config('app.timezone');

        foreach ($items as $item) {
            $label = trim((string) ($item['route'] ?? ''));
            if ($label === '') {
                $label = $this->routeFromAdminText((string) ($item['text'] ?? ''));
            }
            if ($label === '') {
                $label = 'раздел';
            }
            $at = $item['at'];
            $steps[] = [
                'label' => $label,
                'at_iso' => $at instanceof Carbon ? $at->toIso8601String() : '',
                'at_display' => $at instanceof Carbon ? $at->timezone($tz)->format('H:i') : '',
            ];
        }

        $uniqueLabels = array_values(array_unique(array_column($steps, 'label')));
        $stepCount = count($steps);
        $uniqueCount = count($uniqueLabels);

        $text = match ($kind) {
            self::KIND_ADMIN_PANEL => $uniqueCount > 1
                ? 'Сеанс в админ-панели · '.$uniqueCount.' разделов'
                : (string) ($newest['text'] ?? 'Заходил в админ-панель'),
            default => (string) ($newest['text'] ?? ''),
        };

        $activeToday = (bool) collect($items)->contains(fn (array $r) => ! empty($r['active_today']));

        return [
            'id' => 'grp:'.substr(sha1(implode('|', $ids)), 0, 16),
            'at' => $newest['at'],
            'email' => (string) ($newest['email'] ?? ''),
            'text' => $text,
            'kind' => $kind,
            'kind_label' => self::KIND_LABELS[$kind] ?? $kind,
            'active_today' => $activeToday,
            'grouped' => true,
            'steps' => $steps,
            'step_count' => $stepCount,
        ];
    }

    private function routeFromAdminText(string $text): string
    {
        if (preg_match('/—\s*(.+)$/u', $text, $m) === 1) {
            return trim($m[1]);
        }

        return '';
    }

    private function adminPanelText(string $path): string
    {
        $place = $this->humanizeAdminPath($path);

        return $place !== ''
            ? 'Заходил в админ-панель — '.$place
            : 'Заходил в админ-панель';
    }

    private function humanizeAdminPath(string $path): string
    {
        $path = '/'.trim($path, '/');
        if ($path === '/adm' || $path === '/adm/') {
            return 'главная';
        }
        if (str_starts_with($path, '/adm/sobytiya')) {
            return 'события';
        }
        if (str_starts_with($path, '/adm/sotrudniki')) {
            return 'сотрудники';
        }
        if (str_starts_with($path, '/adm/kursy')) {
            return 'каталог курсов';
        }
        if (str_starts_with($path, '/adm/obuchayushchiesya')) {
            return 'обучающиеся портала';
        }
        if (str_starts_with($path, '/adm/nastroiki')) {
            return 'настройки';
        }
        if (str_starts_with($path, '/adm/docker')) {
            return 'библиотека Docker';
        }
        if (preg_match('#^/adm/kurs/([^/]+)#', $path, $m) === 1) {
            $slug = $m[1];
            if (Schema::hasTable('courses')) {
                $title = Course::query()->where('slug', $slug)->value('title');
                if (is_string($title) && $title !== '') {
                    return 'курс «'.$title.'»';
                }
            }

            return 'курс '.$slug;
        }

        return trim(str_replace('/adm/', '', $path), '/') ?: 'раздел';
    }

    private function parseDate(?string $value, bool $startOfDay): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            $d = Carbon::parse($value, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return $startOfDay ? $d->copy()->startOfDay() : $d->copy()->endOfDay();
    }

    private function parseSince(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function serializeForJson(Collection $items): array
    {
        $tz = config('app.timezone');

        return $items->map(function (array $r) use ($tz) {
            $at = $r['at'];
            $payload = [
                'id' => $r['id'],
                'email' => $r['email'],
                'text' => $r['text'],
                'kind' => $r['kind'],
                'kind_label' => $r['kind_label'],
                'active_today' => (bool) $r['active_today'],
                'grouped' => (bool) ($r['grouped'] ?? false),
                'step_count' => (int) ($r['step_count'] ?? 0),
                'steps' => $r['steps'] ?? [],
                'at_iso' => $at->toIso8601String(),
                'at_display' => $at->timezone($tz)->format('d.m.Y H:i'),
            ];
            if (! empty($r['grouped']) && $at instanceof Carbon && isset($r['steps'][0]['at_iso'])) {
                $first = Carbon::parse((string) $r['steps'][0]['at_iso']);
                if ($first->format('Y-m-d') !== $at->format('Y-m-d')) {
                    $payload['at_range'] = $first->timezone($tz)->format('d.m H:i').' — '.$at->timezone($tz)->format('d.m H:i');
                } else {
                    $payload['at_range'] = $first->timezone($tz)->format('H:i').' — '.$at->timezone($tz)->format('H:i');
                }
            }

            return $payload;
        })->values()->all();
    }
}
