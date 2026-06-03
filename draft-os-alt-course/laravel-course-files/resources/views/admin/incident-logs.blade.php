@extends('layouts.admin')

@section('title', 'Логи — Панель администратора')

@push('scripts')
    <script src="{{ asset('js/admin-incident-logs.js') }}" defer></script>
@endpush

@section('content')
    @php
        $stats = $serverStats ?? [];
        $disk = $stats['disk'] ?? [];
        $mem = $stats['memory'] ?? [];
        $db = $stats['database'] ?? [];
        $fmtBytes = static function (?int $b): string {
            if ($b === null || $b < 0) {
                return '—';
            }
            $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
            $i = 0;
            $v = (float) $b;
            while ($v >= 1024 && $i < count($units) - 1) {
                $v /= 1024;
                $i++;
            }
            return round($v, $i > 0 ? 1 : 0).' '.$units[$i];
        };
        $diskFree = $disk['free_bytes'] ?? null;
        $diskTotal = $disk['total_bytes'] ?? null;
        $diskUsedPct = ($diskFree !== null && $diskTotal !== null && $diskTotal > 0)
            ? min(100, max(0, (int) round(100 - ($diskFree / $diskTotal) * 100)))
            : null;
        $incidents24 = (int) ($db['incidents_24h'] ?? 0);
        $loadStr = (! empty($stats['load_avg']) && is_array($stats['load_avg']))
            ? number_format((float) ($stats['load_avg'][0] ?? 0), 2)
            : '—';
    @endphp
    <div class="ap-page ap-logs-page ap-fade">
        <header class="ap-logs-hero">
            <div class="ap-logs-hero__text">
                <p class="ap-logs-hero__eyebrow">Мониторинг · только администратор</p>
                <h1 class="ap-logs-hero__title">Логи и сервер</h1>
                <p class="ap-logs-hero__lead">
                    Ресурсы хоста и журнал сбоев: HTTP, PHP и ошибки в браузере у обучающихся.
                </p>
            </div>
            <div class="ap-logs-hero__badge @if ($incidents24 > 0) ap-logs-hero__badge--alert @endif" title="Инциденты за 24 часа">
                <span class="ap-logs-hero__badge-value">{{ $incidents24 }}</span>
                <span class="ap-logs-hero__badge-label">за 24 ч</span>
            </div>
        </header>

        <div class="ap-logs-stats" role="list">
            <article class="ap-logs-stat" role="listitem">
                <div class="ap-logs-stat__icon ap-logs-stat__icon--mem" aria-hidden="true">
                    @include('partials.ap-icon', ['name' => 'terminal', 'size' => 'sm'])
                </div>
                <div class="ap-logs-stat__body">
                    <span class="ap-logs-stat__label">Память PHP</span>
                    <span class="ap-logs-stat__value">{{ $fmtBytes($mem['usage_bytes'] ?? null) }}</span>
                    <span class="ap-logs-stat__sub">пик {{ $fmtBytes($mem['peak_bytes'] ?? null) }} · {{ $mem['limit'] ?? '—' }}</span>
                </div>
            </article>

            <article class="ap-logs-stat" role="listitem">
                <div class="ap-logs-stat__ring" aria-hidden="true"
                     style="--ap-logs-pct: {{ $diskUsedPct ?? 0 }}%;">
                    <span class="ap-logs-stat__ring-val">{{ $diskUsedPct !== null ? $diskUsedPct.'%' : '—' }}</span>
                </div>
                <div class="ap-logs-stat__body">
                    <span class="ap-logs-stat__label">Диск</span>
                    <span class="ap-logs-stat__value">{{ $fmtBytes($diskFree) }}</span>
                    <span class="ap-logs-stat__sub">свободно из {{ $fmtBytes($diskTotal) }}</span>
                    @if ($diskUsedPct !== null)
                        <span class="ap-logs-stat__bar" aria-hidden="true"><span style="width:{{ $diskUsedPct }}%"></span></span>
                    @endif
                </div>
            </article>

            <article class="ap-logs-stat" role="listitem">
                <div class="ap-logs-stat__icon ap-logs-stat__icon--load" aria-hidden="true">
                    @include('partials.ap-icon', ['name' => 'panel', 'size' => 'sm'])
                </div>
                <div class="ap-logs-stat__body">
                    <span class="ap-logs-stat__label">Нагрузка</span>
                    <span class="ap-logs-stat__value">{{ $loadStr }}</span>
                    <span class="ap-logs-stat__sub">
                        @if (! empty($stats['load_avg']) && is_array($stats['load_avg']))
                            {{ implode(' · ', array_map(fn ($v) => number_format((float) $v, 2), $stats['load_avg'])) }}
                        @else
                            load average
                        @endif
                        · PHP {{ $stats['php_version'] ?? '—' }}
                    </span>
                </div>
            </article>

            <article class="ap-logs-stat ap-logs-stat--incidents" role="listitem">
                <div class="ap-logs-stat__icon ap-logs-stat__icon--alert" aria-hidden="true">
                    @include('partials.ap-icon', ['name' => 'clipboard-check', 'size' => 'sm'])
                </div>
                <div class="ap-logs-stat__body">
                    <span class="ap-logs-stat__label">Всего в журнале</span>
                    <span class="ap-logs-stat__value">{{ (int) ($db['incidents_total'] ?? 0) }}</span>
                    <span class="ap-logs-stat__sub">записей в базе</span>
                </div>
            </article>
        </div>

        <section class="ap-logs-shell">
            @include('admin.partials.incident-logs-panel', [
                'incidentFeedUrl' => $incidentFeedUrl,
                'sourceLabels' => $sourceLabels,
                'emailSuggestions' => $emailSuggestions,
            ])
        </section>
    </div>
@endsection
