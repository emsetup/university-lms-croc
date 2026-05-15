@extends('layouts.course')

@section('title', 'Образовательный портал')

@section('content')
<div class="portal-home">
    @php
        $courseCount = count($courses);
        $catalogBadgeLabel = $courseCount === 1 ? '1 курс' : (
            (($courseCount % 100) > 10 && ($courseCount % 100) < 15)
                ? $courseCount.' курсов'
                : (($courseCount % 10) === 1 ? $courseCount.' курс' : (
                    ($courseCount % 10) >= 2 && ($courseCount % 10) <= 4 ? $courseCount.' курса' : $courseCount.' курсов'
                ))
        );
        $greetName = $portalWelcomeName;
        if ($greetName === null && ! empty($learnerEmail)) {
            $local = (string) (explode('@', (string) $learnerEmail, 2)[0] ?? '');
            $greetName = \Illuminate\Support\Str::title(str_replace(['.', '_', '-'], ' ', $local));
            if (trim((string) $greetName) === '') {
                $greetName = 'участник';
            }
        }
        $portalPlaceholderSlots = [
            ['l1' => 'Скоро здесь появится', 'l2' => 'новый курс', 'v' => 0],
            ['l1' => 'В разработке', 'l2' => 'следите за обновлениями', 'v' => 1],
            ['l1' => 'Новый трек знаний', 'l2' => 'на подходе', 'v' => 2],
            ['l1' => 'Материалы курса', 'l2' => 'готовятся для вас', 'v' => 3],
            ['l1' => 'Расширяем каталог', 'l2' => 'курсы в работе', 'v' => 4],
            ['l1' => 'Совсем скоро', 'l2' => 'откроем доступ', 'v' => 5],
            ['l1' => 'Готовим занятия', 'l2' => 'загляните позже', 'v' => 6],
            ['l1' => 'Ещё один курс', 'l2' => 'будет здесь', 'v' => 7],
        ];
    @endphp
    <style>
        .page-section + .page-section { margin-top: 24px; }
        .welcome-block { padding: 28px 32px; }
        @media (max-width: 560px) {
            .welcome-block { padding: 1.1rem 1rem; }
        }

        .portal-welcome-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .portal-welcome-main {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }
        .portal-welcome-avatar {
            flex: 0 0 auto;
            width: 3.25rem;
            height: 3.25rem;
            border-radius: 50%;
            background: #00b956;
            color: #fff;
            font-weight: 800;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 0.02em;
        }
        .portal-welcome-greet {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }
        .portal-welcome-email {
            font-size: 0.92rem;
            margin-top: 0.2rem;
        }
        .portal-welcome-hint {
            margin: 1rem 0 0;
            line-height: 1.5;
        }

        .portal-courses-heading {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem 0.75rem;
            margin: 0 0 0;
        }
        .portal-courses-heading__title {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .portal-courses-badge {
            display: inline-block;
            background: #e8f9f0;
            color: #00b956;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .portal-course-card {
            cursor: pointer;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            min-height: 220px;
            padding: 2.35rem 1.15rem 1.15rem;
            transition: box-shadow 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
        }
        .portal-course-card.course-card-active {
            border: 2px solid #00b956 !important;
            box-shadow: 0 4px 16px rgba(0, 185, 86, 0.12);
            background: #ffffff !important;
        }
        .portal-course-card-status {
            position: absolute;
            top: 0.65rem;
            right: 0.65rem;
            left: auto;
            z-index: 2;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.2rem 0.45rem;
            border-radius: 999px;
            line-height: 1.2;
        }
        .portal-course-card-status--neutral {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .portal-course-card-status--progress {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid rgba(0, 185, 86, 0.35);
        }
        .portal-course-card-status--done {
            background: #00b956;
            color: #fff;
        }
        .portal-course-card .tag {
            margin-top: 0;
        }
        .portal-course-card-status + .portal-course-title {
            margin-top: 0;
        }
        .portal-course-title { min-height: 2.7rem; }
        .portal-course-grow { flex: 1; min-height: 0; }
        .portal-course-actions {
            margin-top: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .portal-course-actions .btn-primary {
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .portal-course-actions .btn-primary:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 18px rgba(0, 185, 86, 0.28);
        }
        .portal-course-audience-row {
            margin-top: 0.65rem;
            display: flex;
            justify-content: center;
            width: 100%;
        }
        .portal-course-progress-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: 0 0 0.35rem;
        }
        .portal-course-progress-bar.learner-track-summary__bar {
            height: 6px;
            border-radius: 999px;
        }

        .portal-catalog-grid.module-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        @media (max-width: 820px) {
            .portal-catalog-grid.module-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 520px) {
            .portal-catalog-grid.module-grid { grid-template-columns: minmax(0, 1fr); }
        }

        .portal-slot-card.placeholder-card {
            position: relative;
            overflow: hidden;
            height: 100%;
            min-height: 220px;
            align-self: stretch;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: default;
            background: #f5f5f5 !important;
            border: 1.5px dashed #d1d5db !important;
            box-shadow: none !important;
            border-radius: 12px;
            transition: border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
        }
        .portal-slot-card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background-image: radial-gradient(circle at 1px 1px, #dcdcdc 0.65px, transparent 0.7px);
            background-size: 14px 14px;
            opacity: 0.35;
            pointer-events: none;
            z-index: 0;
        }
        .portal-slot-card::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(
                90deg,
                transparent 0%,
                rgba(255, 255, 255, 0.55) 50%,
                transparent 100%
            );
            background-size: 400px 100%;
            background-repeat: no-repeat;
            animation: portal-shimmer 3.5s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }
        @keyframes portal-shimmer {
            0% { background-position: -400px 0; }
            100% { background-position: 400px 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            .portal-slot-card::after { animation: none; opacity: 0; }
            .portal-slot-card:hover .portal-slot-card__icon { transform: none; }
        }
        .portal-slot-card__hover-msg {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 100%;
            padding: 0 0.5rem;
            box-sizing: border-box;
            opacity: 0;
            transform: translateY(4px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .portal-slot-card__hover-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 5.75rem;
            text-align: center;
        }
        .portal-slot-card__icon {
            color: #00b956;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
            transition: transform 0.18s ease;
        }
        .portal-slot-card__icon svg {
            width: 1.65rem;
            height: 1.65rem;
            display: block;
        }
        .portal-slot-card__lines {
            font-size: 0.88rem;
            line-height: 1.45;
            color: #5c6570;
            font-weight: 500;
        }
        .portal-slot-card:hover {
            border: 1.5px solid #00b956 !important;
            background: #f0fdf4 !important;
        }
        .portal-slot-card:hover .portal-slot-card__hover-msg {
            opacity: 1;
            transform: translateY(0);
        }
        .portal-slot-card:hover .portal-slot-card__icon {
            transform: scale(1.08);
        }

        .portal-contact-card {
            position: relative;
            height: 100%;
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1.15rem 1rem;
            cursor: default;
            background: #f5f5f5 !important;
            border: 1px solid #e0e0e0 !important;
            box-shadow: none !important;
            border-radius: 12px;
            transition: background-color 0.18s ease, box-shadow 0.18s ease;
        }
        .portal-contact-card:hover {
            background: #ececec !important;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        }
        .portal-contact-card__text {
            font-size: 0.9rem;
            line-height: 1.55;
            color: #888;
            max-width: 14rem;
        }
        .portal-contact-card__text a {
            color: #4b5563;
            font-weight: 500;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: color 0.18s ease, border-color 0.18s ease, text-decoration 0.18s ease;
        }
        .portal-contact-card__text a:hover {
            color: #1f2937;
            text-decoration: underline;
            border-bottom-color: transparent;
        }

        .portal-catalog-empty {
            grid-column: 1 / -1;
        }

        .portal-course-search-wrap {
            margin-top: 1.1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line, #e5e7eb);
        }
        .portal-course-search-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
            margin: 0 0 0.45rem;
        }
        .portal-course-search-row {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            width: 100%;
            box-sizing: border-box;
            padding: 0.45rem 0.65rem 0.45rem 0.75rem;
            border-radius: 999px;
            border: 1px solid #d5e3da;
            background: linear-gradient(180deg, #fbfffc 0%, #f4faf6 100%);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }
        .portal-course-search-row:focus-within {
            border-color: #00b956;
            box-shadow: 0 0 0 3px rgba(0, 185, 86, 0.18), 0 2px 8px rgba(0, 185, 86, 0.08);
            background: #fff;
        }
        .portal-course-search-icon {
            flex: 0 0 auto;
            width: 1.15rem;
            height: 1.15rem;
            color: #00b956;
            opacity: 0.85;
        }
        .portal-course-search-input {
            flex: 1;
            min-width: 0;
            border: none;
            background: transparent;
            font: inherit;
            font-size: 0.95rem;
            line-height: 1.35;
            color: var(--text, #0f172a);
            outline: none;
        }
        .portal-course-search-input::placeholder {
            color: #94a3b8;
        }
        .portal-course-search-status {
            margin: 0.45rem 0 0;
            font-size: 0.82rem;
            min-height: 0;
        }
        .portal-tag-filters-wrap {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.45rem;
            margin-top: 0.65rem;
            overflow-x: auto;
            padding-bottom: 0.15rem;
            -webkit-overflow-scrolling: touch;
        }
        .portal-tag-filter {
            flex: 0 0 auto;
            border: 1px solid #cbd5e1;
            color: #64748b;
            background: #fff;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            cursor: pointer;
            transition: border-color 0.18s ease, color 0.18s ease, background 0.18s ease;
        }
        .portal-tag-filter:hover {
            border-color: #00b956;
            color: #0f172a;
        }
        .portal-tag-filter.is-active {
            background: #00b956;
            color: #fff;
            border-color: #00b956;
        }

        .portal-catalog-card--hidden {
            display: none !important;
        }
        .portal-search-no-hits {
            display: none;
            padding: 1.25rem 0.5rem;
            text-align: center;
        }
        .portal-search-no-hits.is-visible {
            display: block;
        }

        .course-info-modal {
            position: fixed;
            inset: 0;
            z-index: 2200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.22s ease, visibility 0.22s ease;
            pointer-events: none;
        }
        .course-info-modal.is-open { visibility: visible; opacity: 1; pointer-events: auto; }
        .course-info-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(3px);
        }
        .course-info-modal__panel {
            position: relative;
            z-index: 1;
            max-width: min(860px, 98vw);
            width: 100%;
            max-height: 92vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 16px;
            padding: 1rem 1.1rem 0.85rem;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.2);
        }
        .course-info-modal__close {
            position: absolute;
            top: 0.55rem;
            right: 0.55rem;
            z-index: 2;
            border: none;
            background: transparent;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            color: var(--muted, #64748b);
            padding: 0.25rem 0.45rem;
            border-radius: 8px;
        }
        .course-info-modal__close:hover { background: rgba(15, 23, 42, 0.06); color: var(--text, #0f172a); }
        .course-info-modal__body { overflow: auto; padding-right: 0.25rem; }
        .course-info-modal__footer { margin-top: 0.85rem; padding-top: 0.65rem; border-top: 1px solid var(--line, #e5e7eb); display:flex; justify-content:flex-end; gap:0.5rem; flex-wrap:wrap; }
        .course-info-modal__title { margin: 0 0 0.25rem; font-size: 1.15rem; padding-right: 2rem; font-weight: 900; }
        .course-info-modal__slug { margin: 0 0 0.65rem; }
        .course-info-modal__summary { line-height: 1.6; color: #334155; white-space: pre-wrap; }
    </style>

    @if (session('learner_id') && ! empty($learnerEmail))
        <div class="card page-section welcome-block" >
            <div class="portal-welcome-head">
                <div class="portal-welcome-main">
                    <div class="portal-welcome-avatar" aria-hidden="true">{{ $portalWelcomeInitials }}</div>
                    <div>
                        <div class="portal-welcome-greet">Добро пожаловать, {{ $greetName }}!</div>
                        <div class="portal-welcome-email muted">{{ $learnerEmail }}</div>
                    </div>
                </div>
                @if (! empty($portalStaffAccess))
                    <a class="btn btn-ghost" href="{{ route('admin.panel') }}">Управление ↗</a>
                @endif
            </div>
            <p class="muted portal-welcome-hint">
                Выберите курс и начните обучение. Прогресс и попытки привязаны к вашей корпоративной почте.
            </p>
            <div class="portal-course-search-wrap" role="search" aria-label="Поиск по каталогу курсов">
                <label class="portal-course-search-label" for="portal-course-search">Поиск курса</label>
                <div class="portal-course-search-row">
                    <svg class="portal-course-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="M20 20l-3.5-3.5"/>
                    </svg>
                    <input type="search"
                           id="portal-course-search"
                           class="portal-course-search-input"
                           placeholder="Название, описание или идентификатор курса…"
                           autocomplete="off"
                           spellcheck="false"
                           enterkeyhint="search">
                </div>
                @if ($catalogFilterTags->isNotEmpty())
                    <div class="portal-tag-filters-wrap" id="portal-tag-filters" role="group" aria-label="Фильтр по темам">
                        <button type="button" class="portal-tag-filter is-active" data-tag="">Все</button>
                        @foreach ($catalogFilterTags as $tag)
                            <button type="button" class="portal-tag-filter" data-tag="{{ e($tag) }}">{{ $tag }}</button>
                        @endforeach
                    </div>
                @endif
                <p class="portal-course-search-status muted" id="portal-course-search-status" aria-live="polite"></p>
            </div>
        </div>
    @else
        <div class="card page-section welcome-block" >
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                <div>
                    <h1 style="margin:0 0 0.35rem">Образовательный портал</h1>
                    <p class="muted" style="margin:0;max-width:60rem;line-height:1.5">
                        Выберите курс и начните обучение. Прогресс и попытки привязаны к вашей корпоративной почте.
                    </p>
                </div>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                    <button type="button" class="btn btn-primary" id="portal-login-open">Войти</button>
                </div>
            </div>
            <div class="portal-course-search-wrap" role="search" aria-label="Поиск по каталогу курсов">
                <label class="portal-course-search-label" for="portal-course-search">Поиск курса</label>
                <div class="portal-course-search-row">
                    <svg class="portal-course-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="M20 20l-3.5-3.5"/>
                    </svg>
                    <input type="search"
                           id="portal-course-search"
                           class="portal-course-search-input"
                           placeholder="Название, описание или идентификатор курса…"
                           autocomplete="off"
                           spellcheck="false"
                           enterkeyhint="search">
                </div>
                @if ($catalogFilterTags->isNotEmpty())
                    <div class="portal-tag-filters-wrap" id="portal-tag-filters" role="group" aria-label="Фильтр по темам">
                        <button type="button" class="portal-tag-filter is-active" data-tag="">Все</button>
                        @foreach ($catalogFilterTags as $tag)
                            <button type="button" class="portal-tag-filter" data-tag="{{ e($tag) }}">{{ $tag }}</button>
                        @endforeach
                    </div>
                @endif
                <p class="portal-course-search-status muted" id="portal-course-search-status" aria-live="polite"></p>
            </div>
        </div>
    @endif

    @if (! empty($identityDebugRows))
        <div class="card page-section" style="max-width:1100px;margin:0 auto;border:2px dashed #94a3b8;background:#f8fafc">
            <h2 style="margin:0 0 0.5rem;font-size:1.05rem">Отладка личности (только при <code>?identity_debug=1</code>)</h2>
            <p class="muted" style="margin:0 0 0.75rem;font-size:0.9rem">
                Сообщите, какие строки заполнены и какое значение подходит под ФИО. Панель только для диагностики.
            </p>
            <div style="overflow:auto;max-height:70vh">
                <table style="width:100%;border-collapse:collapse;font-size:0.82rem">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid #e2e8f0">
                            <th style="padding:0.35rem 0.5rem;vertical-align:top;width:38%">Подпись поля</th>
                            <th style="padding:0.35rem 0.5rem;vertical-align:top">Значение</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($identityDebugRows as $row)
                            <tr style="border-bottom:1px solid #f1f5f9">
                                <td style="padding:0.35rem 0.5rem;vertical-align:top;color:#334155;font-family:ui-monospace,monospace">{{ $row['label'] }}</td>
                                <td style="padding:0.35rem 0.5rem;vertical-align:top;word-break:break-word;white-space:pre-wrap">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card page-section" >
        <div class="portal-courses-heading">
            <h2 class="portal-courses-heading__title">Доступные курсы</h2>
            @if ($courseCount > 0)
                <span class="portal-courses-badge">{{ $catalogBadgeLabel }}</span>
            @endif
        </div>
        <div class="module-grid portal-catalog-grid courses-catalog-grid" id="portal-courses-catalog-grid">
            @foreach ($courses as $c)
                @php
                    $enroll = $enrollmentsByCourseId[$c->id] ?? null;
                    $pct = (int) ($progressByCourseId[$c->id] ?? 0);
                    $started = $pct > 0 || ($enroll && ! empty($enroll->started_at));
                    $mp = $modulesProgressByCourseId[$c->id] ?? ['passed' => 0, 'total' => 0];
                    $tags = array_values(array_filter(array_map('trim', $c->tags ?? []), fn ($t) => $t !== ''));
                @endphp
                <div class="module-card portal-course-card course-card-active js-course-card"
                     role="button"
                     tabindex="0"
                     data-course-id="{{ (int) $c->id }}"
                     data-course-title="{{ e($c->title) }}"
                     data-course-slug="{{ e($c->slug) }}"
                     data-course-summary="{{ e($c->summary) }}"
                     data-course-started="{{ $started ? '1' : '0' }}"
                     data-course-pct="{{ (int) $pct }}"
                     data-course-tags="{{ e(json_encode($tags, JSON_UNESCAPED_UNICODE)) }}">
                    @if ($pct >= 100)
                        <div class="portal-course-card-status portal-course-card-status--done">
                            <svg class="portal-status-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                            Завершён
                        </div>
                    @elseif ($pct > 0)
                        <div class="portal-course-card-status portal-course-card-status--progress">
                            <svg class="portal-status-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg>
                            В процессе
                        </div>
                    @else
                        <div class="portal-course-card-status portal-course-card-status--neutral">Курс</div>
                    @endif
                    <div class="portal-course-title" style="font-weight:800;font-size:1.05rem;line-height:1.25">{{ $c->title }}</div>
                    <div class="portal-course-grow">
                        <div class="muted course-card__description" style="font-size:0.92rem;line-height:1.45;margin-top:0.35rem">{{ $c->summary }}</div>
                    </div>
                    @if ($c->slug === 'alt-os-features')
                        <div class="portal-course-audience-row">
                            <button type="button" class="btn btn-ghost" id="portal-course-audience-open">Подробнее: для кого этот курс</button>
                        </div>
                    @endif

                    @if (session('learner_id') && ($pct > 0 || ($enroll && ! empty($enroll->started_at))))
                        <div style="margin-top:0.85rem">
                            <div class="portal-course-progress-head muted small">
                                <span>Прогресс по курсу: <strong>{{ $pct }}%</strong></span>
                                @if (($mp['total'] ?? 0) > 0)
                                    <span>{{ (int) ($mp['passed'] ?? 0) }} из {{ (int) ($mp['total'] ?? 0) }} модулей</span>
                                @endif
                            </div>
                            <div class="learner-track-summary__bar portal-course-progress-bar" aria-hidden="true">
                                <div class="learner-track-summary__bar-fill" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                            </div>
                            @if ($enroll && $enroll->started_at)
                                <div class="muted small" style="margin-top:0.35rem">
                                    Начато: <strong>{{ $enroll->started_at->format('d.m.Y H:i') }}</strong>
                                </div>
                            @endif
                        </div>
                    @endif
                    <div class="portal-course-actions" style="margin-top:0.85rem">
                        @if (session('learner_id'))
                            <form method="post" action="{{ route('portal.enroll', ['course' => $c->id]) }}" style="margin:0">
                                @csrf
                                <button type="submit" class="btn btn-primary">{{ $started ? 'Продолжить' : 'Начать обучение' }}</button>
                            </form>
                        @else
                            <button type="button" class="btn btn-primary portal-start-needs-login" data-course="{{ (int) $c->id }}">Войти и начать</button>
                        @endif
                    </div>
                </div>
            @endforeach
            @if (count($courses) === 0)
                <p class="muted portal-catalog-empty" style="margin:0">Курсы пока не добавлены.</p>
            @endif
            <p class="muted portal-catalog-empty portal-search-no-hits" id="portal-search-no-hits" role="status">
                По запросу ничего не найдено — попробуйте другие слова или сбросьте поиск.
            </p>
            @foreach ($portalPlaceholderSlots as $slot)
                <div class="module-card portal-slot-card placeholder-card js-catalog-extra" aria-hidden="true">
                    <div class="portal-slot-card__hover-msg">
                        <div class="portal-slot-card__hover-inner">
                            @include('portal.partials.catalog-placeholder-icon', ['variant' => $slot['v']])
                            <span class="portal-slot-card__lines">{{ $slot['l1'] }}<br>{{ $slot['l2'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="module-card portal-contact-card js-catalog-extra">
                <div class="portal-contact-card__text">
                    Хотите разместить свой курс?<br>
                    Напишите нам:<br>
                    <a href="mailto:emednikov@croc.ru">emednikov@croc.ru</a>
                </div>
            </div>
        </div>
    </div>

</div>

    @include('portal.partials.login-modal', ['domain' => config('course.email_domain')])
    @include('partials.course-audience-modal')

    <div class="course-info-modal" id="portal-course-info-modal" aria-hidden="true">
        <div class="course-info-modal__backdrop" data-course-info-close tabindex="-1"></div>
        <div class="course-info-modal__panel" role="dialog" aria-modal="true" aria-labelledby="portal-course-info-title">
            <button type="button" class="course-info-modal__close" data-course-info-close aria-label="Закрыть">&times;</button>
            <div class="tag" style="margin-bottom:0.65rem">Курс</div>
            <div id="portal-course-info-title" class="course-info-modal__title">Курс</div>
            <div class="muted small course-info-modal__slug" id="portal-course-info-slug"></div>
            <div class="course-info-modal__body">
                <div class="course-info-modal__summary" id="portal-course-info-summary"></div>
            </div>
            <div class="course-info-modal__footer" id="portal-course-info-footer">
                <button type="button" class="btn btn-ghost" data-course-info-close>Закрыть</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var input = document.getElementById('portal-course-search');
            var status = document.getElementById('portal-course-search-status');
            var noHits = document.getElementById('portal-search-no-hits');
            var grid = document.getElementById('portal-courses-catalog-grid');
            var tagWrap = document.getElementById('portal-tag-filters');
            if (!input || !grid) return;

            var cards = grid.querySelectorAll('.js-course-card');
            var extras = grid.querySelectorAll('.js-catalog-extra');
            var total = cards.length;
            var activeTag = '';

            function norm(s) {
                return String(s || '').toLowerCase();
            }
            function cardHaystack(card) {
                return norm(
                    (card.getAttribute('data-course-title') || '') + ' ' +
                    (card.getAttribute('data-course-slug') || '') + ' ' +
                    (card.getAttribute('data-course-summary') || '')
                );
            }
            function cardTags(card) {
                var raw = card.getAttribute('data-course-tags') || '[]';
                try {
                    var arr = JSON.parse(raw);
                    return Array.isArray(arr) ? arr : [];
                } catch (e) {
                    return [];
                }
            }
            function tagMatch(card) {
                if (!activeTag) return true;
                var tags = cardTags(card);
                var i;
                for (i = 0; i < tags.length; i++) {
                    if (tags[i] === activeTag) return true;
                }
                return false;
            }
            function textMatch(hay, q) {
                if (!q) return true;
                var parts = q.split(/\s+/).filter(Boolean);
                var i;
                for (i = 0; i < parts.length; i++) {
                    if (hay.indexOf(parts[i]) === -1) return false;
                }
                return true;
            }
            function apply() {
                var q = norm(input.value).trim();
                var n = 0;
                var j;
                for (j = 0; j < cards.length; j++) {
                    var card = cards[j];
                    var ok = textMatch(cardHaystack(card), q) && tagMatch(card);
                    if (ok) n++;
                    card.classList.toggle('portal-catalog-card--hidden', !ok);
                }
                var showExtras = q === '' && !activeTag;
                extras.forEach(function (el) {
                    el.classList.toggle('portal-catalog-card--hidden', !showExtras);
                });
                if (noHits) {
                    noHits.classList.toggle('is-visible', (q !== '' || activeTag) && n === 0 && total > 0);
                }
                if (status) {
                    if (total === 0) {
                        status.textContent = '';
                    } else if (q === '' && !activeTag) {
                        status.textContent = '';
                    } else if (n === 0) {
                        status.textContent = 'Совпадений нет.';
                    } else {
                        status.textContent = n === 1 ? 'Найден 1 курс.' : ('Найдено курсов: ' + n + ' из ' + total + '.');
                    }
                }
            }
            input.addEventListener('input', apply);
            input.addEventListener('search', apply);
            if (tagWrap) {
                tagWrap.addEventListener('click', function (e) {
                    var btn = e.target.closest('.portal-tag-filter');
                    if (!btn) return;
                    activeTag = btn.getAttribute('data-tag') || '';
                    tagWrap.querySelectorAll('.portal-tag-filter').forEach(function (b) {
                        b.classList.toggle('is-active', (b.getAttribute('data-tag') || '') === activeTag);
                    });
                    apply();
                });
            }
            apply();
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && document.activeElement === input && input.value) {
                    input.value = '';
                    apply();
                }
            });
        })();

        (function () {
            var dlg = document.getElementById('portal-login-dialog');
            var openBtn = document.getElementById('portal-login-open');
            function open() {
                if (!dlg) return;
                if (typeof dlg.showModal === 'function') dlg.showModal();
                var email = document.getElementById('portal-login-email');
                if (email) setTimeout(function () { try { email.focus(); } catch (err) {} }, 0);
            }
            if (openBtn) openBtn.addEventListener('click', open);
            var needs = document.querySelectorAll('.portal-start-needs-login');
            needs.forEach(function (b) { b.addEventListener('click', open); });
            @if (!empty($showLogin))
            open();
            @endif
        })();

        (function () {
            var modal = document.getElementById('course-audience-modal-root');
            var openBtn = document.getElementById('portal-course-audience-open');
            if (!modal || !openBtn) return;

            var lastFocus = null;
            function closeEls() {
                return modal.querySelectorAll('[data-modal-close]');
            }
            function openModal() {
                lastFocus = document.activeElement;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('course-modal-open');
                var closeBtn = modal.querySelector('.course-modal__close');
                if (closeBtn) closeBtn.focus();
            }
            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('course-modal-open');
                if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
            }
            openBtn.addEventListener('click', openModal);
            closeEls().forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('course-modal__backdrop')) {
                        e.preventDefault();
                    }
                    closeModal();
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
        })();

        (function () {
            var modal = document.getElementById('portal-course-info-modal');
            var titleEl = document.getElementById('portal-course-info-title');
            var slugEl = document.getElementById('portal-course-info-slug');
            var summaryEl = document.getElementById('portal-course-info-summary');
            var footerEl = document.getElementById('portal-course-info-footer');
            if (!modal || !titleEl || !slugEl || !summaryEl || !footerEl) return;

            var lastFocus = null;
            function openModal(card) {
                lastFocus = document.activeElement;
                var title = card.getAttribute('data-course-title') || 'Курс';
                var slug = card.getAttribute('data-course-slug') || '';
                var summary = card.getAttribute('data-course-summary') || '';
                var courseId = card.getAttribute('data-course-id') || '';
                var started = card.getAttribute('data-course-started') === '1';

                titleEl.textContent = title;
                slugEl.innerHTML = slug ? ('slug: <code>' + slug + '</code>') : '';
                summaryEl.textContent = summary;

                footerEl.querySelectorAll('[data-dynamic]').forEach(function (n) { n.remove(); });

                @if (session('learner_id'))
                if (courseId) {
                    var form = document.createElement('form');
                    form.method = 'post';
                    form.action = '{{ route('portal.enroll', ['course' => 0]) }}'.replace(/\/0$/, '/' + courseId);
                    form.setAttribute('data-dynamic', '1');
                    form.style.margin = '0';
                    form.innerHTML = '{!! csrf_field() !!}';
                    var btn = document.createElement('button');
                    btn.type = 'submit';
                    btn.className = 'btn btn-primary';
                    btn.textContent = started ? 'Продолжить' : 'Начать обучение';
                    form.appendChild(btn);
                    footerEl.insertBefore(form, footerEl.firstChild);
                }
                @endif

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('course-modal-open');
                var closeBtn = modal.querySelector('.course-info-modal__close');
                if (closeBtn) closeBtn.focus();
            }
            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('course-modal-open');
                if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
            }
            function shouldIgnore(e) {
                if (!e || !e.target) return false;
                return !!e.target.closest('button, a, form, input, select, textarea, .portal-start-needs-login, .portal-tag-filter');
            }
            document.querySelectorAll('.js-course-card').forEach(function (card) {
                card.addEventListener('click', function (e) {
                    if (shouldIgnore(e)) return;
                    openModal(card);
                });
                card.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openModal(card);
                    }
                });
            });
            modal.querySelectorAll('[data-course-info-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('course-info-modal__backdrop')) e.preventDefault();
                    closeModal();
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
            });
        })();
    </script>
@endsection
