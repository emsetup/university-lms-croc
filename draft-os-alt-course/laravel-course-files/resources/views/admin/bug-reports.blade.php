@extends('layouts.admin')

@section('title', 'Баги и предложения — Панель администратора')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/portal-bug-report.css') }}?v={{ @filemtime(public_path('css/portal-bug-report.css')) ?: 1 }}">
@endpush
@push('scripts')
    <script src="{{ asset('js/admin-bug-reports.js') }}?v={{ @filemtime(public_path('js/admin-bug-reports.js')) ?: 1 }}" defer></script>
@endpush

@section('content')
    @php
        $stats = $bugStats ?? ['total' => 0, 'new' => 0, 'new_24h' => 0, 'open' => 0];
    @endphp
    <div class="ap-page ap-logs-page ap-fade"
         data-ap-bug-page
         data-ap-bug-open="{{ (int) request()->query('open', 0) }}">
        <header class="ap-logs-hero">
            <div class="ap-logs-hero__text">
                <p class="ap-logs-hero__eyebrow">Обратная связь · inbox</p>
                <h1 class="ap-logs-hero__title">Баги и предложения</h1>
                <p class="ap-logs-hero__lead">
                    Сообщения с кнопки «Сообщить» на страницах портала: ошибки, идеи и скриншоты от авторизованных пользователей.
                    @if (! empty($bugInboxScoped))
                        Вам доступны тикеты по курсам, где вы указаны как автор.
                    @endif
                </p>
            </div>
            <div class="ap-logs-hero__badge @if (($stats['new'] ?? 0) > 0) ap-logs-hero__badge--alert @endif" title="Новые">
                <span class="ap-logs-hero__badge-value">{{ (int) ($stats['new'] ?? 0) }}</span>
                <span class="ap-logs-hero__badge-label">новых</span>
            </div>
        </header>

        <div class="ap-logs-stats" role="list">
            <article class="ap-logs-stat" role="listitem">
                <div class="ap-logs-stat__body">
                    <span class="ap-logs-stat__label">Всего</span>
                    <span class="ap-logs-stat__value">{{ (int) ($stats['total'] ?? 0) }}</span>
                </div>
            </article>
            <article class="ap-logs-stat" role="listitem">
                <div class="ap-logs-stat__body">
                    <span class="ap-logs-stat__label">Открытых</span>
                    <span class="ap-logs-stat__value">{{ (int) ($stats['open'] ?? 0) }}</span>
                </div>
            </article>
            <article class="ap-logs-stat" role="listitem">
                <div class="ap-logs-stat__body">
                    <span class="ap-logs-stat__label">За 24 часа</span>
                    <span class="ap-logs-stat__value">{{ (int) ($stats['new_24h'] ?? 0) }}</span>
                </div>
            </article>
            <article class="ap-logs-stat" role="listitem">
                <div class="ap-logs-stat__body">
                    <span class="ap-logs-stat__label">Разделы</span>
                    <span class="ap-logs-stat__value" style="font-size:1rem;">
                        <a href="{{ route('admin.incidents.index') }}">Логи</a>
                        ·
                        <a href="{{ route('admin.mail.index') }}">Почта</a>
                    </span>
                </div>
            </article>
        </div>

        <section class="ap-logs-shell">
            @include('admin.partials.bug-reports-panel', [
                'bugFeedUrl' => $bugFeedUrl,
                'typeLabels' => $typeLabels,
                'scopeLabels' => $scopeLabels ?? \App\Services\PortalBugReportFeedService::SCOPE_LABELS,
                'statusLabels' => $statusLabels,
                'emailSuggestions' => $emailSuggestions,
            ])
        </section>
    </div>
@endsection
