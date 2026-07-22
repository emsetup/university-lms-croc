@extends('layouts.admin')

@section('title', 'Почта — Панель администратора')

@php
    $mailTab = $mailTab ?? 'zhurnal';
@endphp

@push('styles')
    <style>
        .ap-mail-status { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:600; }
        .ap-mail-status--sent { background:#e8f7ee; color:#0f7a3a; }
        .ap-mail-status--failed { background:#fdecec; color:#b42318; }
        .ap-mail-status--pending { background:#eef2f6; color:#475569; }
        .ap-mail-status--skipped { background:#f4f1ea; color:#8a6d3b; }
        .ap-logs-entry__sub { margin-top:4px; font-size:12px; color:#64748b; }
        .ap-mail-entry {
            display:grid;
            grid-template-columns:minmax(0,1fr) auto;
            gap:0.75rem 1rem;
            align-items:start;
            padding:0.75rem 0.85rem;
        }
        .ap-mail-entry__actions {
            display:flex;
            flex-wrap:wrap;
            gap:0.4rem;
            justify-content:flex-end;
        }
        .ap-mail-entry__actions .btn { white-space:nowrap; }
        .ap-mail-tabs {
            display:flex;
            flex-wrap:wrap;
            gap:0.25rem 1.5rem;
            margin:0 0 1.25rem;
            padding:0 0 0;
            border-bottom:1px solid var(--ap-border, #e5e7eb);
        }
        .ap-mail-tabs__a {
            display:inline-block;
            padding:10px 2px 12px;
            margin-bottom:-1px;
            font-size:0.92rem;
            font-weight:600;
            text-decoration:none;
            color:var(--ap-text-secondary, #64748b);
            border-bottom:2px solid transparent;
        }
        .ap-mail-tabs__a:hover { color:var(--ap-text-primary, #111827); text-decoration:none; }
        .ap-mail-tabs__a--active {
            color:var(--ap-text-primary, #111827);
            border-bottom-color:var(--ap-brand, #00a84d);
        }
        .ap-mail-templates__intro { margin:0 0 1.1rem; color:#64748b; max-width:52rem; }
        .ap-mail-templates__list { display:flex; flex-direction:column; gap:1rem; }
        .ap-mail-template {
            border:1px solid var(--ap-border, #e5e7eb);
            border-radius:12px;
            background:#fff;
            padding:1rem 1.15rem 1.05rem;
        }
        .ap-mail-template__head {
            display:flex;
            gap:1rem;
            justify-content:space-between;
            align-items:flex-start;
            margin-bottom:0.85rem;
        }
        .ap-mail-template__eyebrow {
            margin:0 0 0.25rem;
            font-size:0.72rem;
            letter-spacing:0.08em;
            text-transform:uppercase;
            color:#00a84d;
            font-weight:700;
        }
        .ap-mail-template__title { margin:0 0 0.35rem; font-size:1.15rem; }
        .ap-mail-template__subject { margin:0; color:#64748b; font-size:0.9rem; }
        .ap-mail-template__subject span { color:#94a3b8; }
        .ap-mail-template__body {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
            gap:1rem 1.5rem;
        }
        .ap-mail-template__block h3 {
            margin:0 0 0.45rem;
            font-size:0.8rem;
            text-transform:uppercase;
            letter-spacing:0.06em;
            color:#94a3b8;
        }
        .ap-mail-template__copy,
        .ap-mail-template__triggers {
            margin:0;
            padding-left:1.1rem;
            color:#334155;
            line-height:1.45;
        }
        .ap-mail-template__preview {
            margin-top:1rem;
            border:1px solid #e6ebf0;
            border-radius:10px;
            overflow:hidden;
            background:#eef1f3;
        }
        .ap-mail-template__preview iframe {
            display:block;
            width:100%;
            min-height:520px;
            border:0;
            background:#eef1f3;
        }
        .ap-mail-detail-modal .ap-modal__panel { max-width:720px; width:min(720px, 96vw); }
        .ap-mail-preview iframe { background:#eef1f3; }
    </style>
@endpush
@push('scripts')
    <script src="{{ asset('js/admin-mail-logs.js') }}" defer></script>
@endpush

@section('content')
    @php
        $stats = $mailStats ?? ['total' => 0, 'sent_24h' => 0, 'failed_24h' => 0];
    @endphp
    <div class="ap-page ap-logs-page ap-fade" data-ap-mail-page>
        <header class="ap-logs-hero">
            <div class="ap-logs-hero__text">
                <p class="ap-logs-hero__eyebrow">Мониторинг · только администратор</p>
                <h1 class="ap-logs-hero__title">Почта</h1>
                <p class="ap-logs-hero__lead">
                    Уведомления портала через Exchange (EWS): доступ, права сотрудников, приглашения на опрос.
                    Можно просмотреть текст и отправить письмо снова.
                </p>
            </div>
            <div class="ap-logs-hero__badge @if (($stats['failed_24h'] ?? 0) > 0) ap-logs-hero__badge--alert @endif" title="Ошибки за 24 часа">
                <span class="ap-logs-hero__badge-value">{{ (int) ($stats['failed_24h'] ?? 0) }}</span>
                <span class="ap-logs-hero__badge-label">ошибок / 24 ч</span>
            </div>
        </header>

        <nav class="ap-mail-tabs" aria-label="Разделы почты">
            <a class="ap-mail-tabs__a @if ($mailTab === 'zhurnal') ap-mail-tabs__a--active @endif" href="{{ route('admin.mail.index') }}">Журнал</a>
            <a class="ap-mail-tabs__a @if ($mailTab === 'shablony') ap-mail-tabs__a--active @endif" href="{{ route('admin.mail.templates') }}">Шаблоны и триггеры</a>
        </nav>

        @if ($mailTab === 'shablony')
            @include('admin.partials.mail-templates-panel', [
                'mailTemplates' => $mailTemplates ?? [],
            ])
        @else
            <div class="ap-logs-stats" role="list">
                <article class="ap-logs-stat" role="listitem">
                    <div class="ap-logs-stat__body">
                        <span class="ap-logs-stat__label">Всего писем</span>
                        <span class="ap-logs-stat__value">{{ (int) ($stats['total'] ?? 0) }}</span>
                    </div>
                </article>
                <article class="ap-logs-stat" role="listitem">
                    <div class="ap-logs-stat__body">
                        <span class="ap-logs-stat__label">Отправлено за 24 ч</span>
                        <span class="ap-logs-stat__value">{{ (int) ($stats['sent_24h'] ?? 0) }}</span>
                    </div>
                </article>
                <article class="ap-logs-stat" role="listitem">
                    <div class="ap-logs-stat__body">
                        <span class="ap-logs-stat__label">Ошибки за 24 ч</span>
                        <span class="ap-logs-stat__value">{{ (int) ($stats['failed_24h'] ?? 0) }}</span>
                    </div>
                </article>
                <article class="ap-logs-stat" role="listitem">
                    <div class="ap-logs-stat__body">
                        <span class="ap-logs-stat__label">Разделы</span>
                        <span class="ap-logs-stat__value" style="font-size:1rem;">
                            <a href="{{ route('admin.incidents.index') }}">Логи сбоев</a>
                        </span>
                    </div>
                </article>
            </div>

            <section class="ap-logs-shell">
                @include('admin.partials.mail-logs-panel', [
                    'mailFeedUrl' => $mailFeedUrl,
                    'typeLabels' => $typeLabels,
                    'statusLabels' => $statusLabels,
                    'emailSuggestions' => $emailSuggestions,
                ])
            </section>
        @endif
    </div>
@endsection
