@extends('layouts.course')

@section('title', 'Личный кабинет')

@section('content')
    @php
        $brand = '#00b956';
        $stats = $stats ?? ['in_progress' => 0, 'completed' => 0, 'total_time_label' => '0 мин', 'certificates_count' => 0];
        $courseRows = $courseRows ?? [];
        $certificates = $certificates ?? [];
        $portalWelcomeName = $portalWelcomeName ?? null;
        $learnerInitials = $learnerInitials ?? '??';
        $displayName = $portalWelcomeName ?: (string) $learner->email;
    @endphp
    <style>
        .acc-page { padding: 0 0 2rem; --acc-brand: {{ $brand }}; }
        .page-section + .page-section { margin-top: 24px; }
        .welcome-block { padding: 28px 32px; }
        @media (max-width: 560px) {
            .welcome-block { padding: 1.1rem 1rem; }
        }

        .acc-welcome-eyebrow {
            margin: 0 0 0.65rem;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
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
            color: #64748b;
            word-break: break-word;
        }

        .portal-welcome-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem 1.1rem;
            margin-top: 1.35rem;
        }
        .acc-welcome-metrics--4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        @media (max-width: 900px) {
            .acc-welcome-metrics--4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 520px) {
            .portal-welcome-metrics { grid-template-columns: 1fr; }
        }
        .acc-tabs-card { padding: var(--portal-pad, 28px); }
        .acc-tabs-card > .acc-panel { margin-top: 1.1rem; }
        .acc-card__footer-actions .btn-primary {
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .acc-card__footer-actions .btn-primary:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 18px rgba(0, 185, 86, 0.28);
        }
        .portal-tag-filters-wrap {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.45rem;
            margin-top: 0;
            overflow-x: auto;
            padding-bottom: 0.15rem;
            -webkit-overflow-scrolling: touch;
        }
        .portal-tag-filter.acc-tab {
            flex: 0 0 auto;
            border: 1px solid #cbd5e1;
            color: #64748b;
            background: #fff;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            font-family: inherit;
            transition: border-color 0.18s ease, color 0.18s ease, background 0.18s ease;
        }
        .portal-tag-filter.acc-tab:hover {
            border-color: #00b956;
            color: #0f172a;
        }
        .portal-tag-filter.acc-tab.is-active {
            background: #00b956;
            color: #fff;
            border-color: #00b956;
        }
        .acc-panel { display: none; }
        .acc-panel.is-active { display: block; }

        .my-courses-grid.module-grid.portal-catalog-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 0;
        }
        @media (max-width: 820px) {
            .my-courses-grid.module-grid.portal-catalog-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 520px) {
            .my-courses-grid.module-grid.portal-catalog-grid { grid-template-columns: minmax(0, 1fr); }
        }

        .portal-course-card {
            cursor: default;
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
        .portal-course-card-status--progress {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid rgba(0, 185, 86, 0.35);
        }
        .portal-course-card-status--done {
            background: #00b956;
            color: #fff;
        }
        .portal-course-title { min-height: 2.7rem; }
        .portal-course-grow { flex: 1; min-height: 0; }
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

        .acc-page article.acc-card.portal-course-card {
            opacity: 0;
            animation: accCardIn 0.42s ease forwards;
        }
        @keyframes accCardIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .acc-page article.acc-card.portal-course-card.acc-card--done:not(.course-card-active) {
            border: 1px solid rgba(0, 185, 86, 0.22) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
            background: linear-gradient(180deg, #f4fdf8 0%, #fff 52%) !important;
        }
        .acc-card__desc {
            margin: 0.45rem 0 0;
            font-size: 0.9rem;
            line-height: 1.45;
            color: #64748b;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
        }
        .acc-inline {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            vertical-align: middle;
        }
        .acc-svg-ico {
            flex-shrink: 0;
        }
        .acc-pin {
            margin-top: 0.65rem;
            font-size: 0.86rem;
            line-height: 1.45;
            color: #334155;
            display: flex;
            align-items: flex-start;
            gap: 0.35rem;
        }
        .acc-pin strong { color: #0f172a; }
        .acc-time {
            margin-top: 0.45rem;
            font-size: 0.86rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .acc-card__footer {
            margin-top: auto;
            padding-top: 1rem;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.75rem 1rem;
            width: 100%;
        }
        .acc-card__footer-meta { flex: 1; min-width: 12rem; }
        .acc-card__footer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: flex-end;
            margin-left: auto;
        }
        .acc-done-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
        .acc-done-txt {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 800;
            color: #047857;
            font-size: 0.92rem;
        }
        .acc-empty {
            text-align: center;
            padding: 2.5rem 1.25rem;
            background: #fff;
            border-radius: 14px;
            border: 1px solid rgba(0, 185, 86, 0.14);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.8) inset,
                0 2px 16px rgba(15, 23, 42, 0.05);
        }
        .acc-empty__icon-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 0.75rem;
        }
        .acc-empty__title { margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a; }
        .acc-empty__text { margin: 0.45rem auto 0; max-width: 22rem; color: #64748b; font-size: 0.95rem; line-height: 1.5; }
        .acc-empty__actions { margin-top: 1.1rem; }
        .empty-state {
            text-align: center;
            padding: 60px 24px;
            color: #6b7280;
            background: #fff;
            border-radius: 14px;
            border: 1px solid rgba(0, 185, 86, 0.14);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.8) inset,
                0 2px 16px rgba(15, 23, 42, 0.05);
        }
        .empty-state .empty-icon-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            margin: 0 0 8px;
        }
        .empty-state p {
            font-size: 14px;
            margin: 0 0 20px;
        }
        .empty-state .btn-primary {
            display: inline-flex;
        }
        .acc-cert-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }
        @media (max-width: 900px) {
            .acc-cert-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 520px) {
            .acc-cert-grid { grid-template-columns: 1fr; }
        }
        .acc-cert-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid rgba(0, 185, 86, 0.1);
            padding: 1rem 1.05rem;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            opacity: 0;
            animation: accCardIn 0.42s ease forwards;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .acc-cert-card:hover {
            border-color: rgba(0, 185, 86, 0.22);
            box-shadow: 0 8px 24px rgba(0, 185, 86, 0.1), 0 2px 8px rgba(0,0,0,0.06);
        }
        .acc-cert-thumb {
            border-radius: 10px;
            background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
            border: 1px dashed rgba(0,185,86,0.35);
            min-height: 6.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .acc-cert-actions {
            margin-top: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .acc-toast {
            position: fixed;
            bottom: 1.25rem;
            left: 50%;
            transform: translateX(-50%) translateY(120%);
            background: #0f172a;
            color: #fff;
            padding: 0.55rem 1rem;
            border-radius: 999px;
            font-size: 0.88rem;
            font-weight: 600;
            z-index: 3000;
            opacity: 0;
            transition: opacity 0.2s ease, transform 0.28s ease;
            pointer-events: none;
        }
        .acc-toast.is-on { opacity: 1; transform: translateX(-50%) translateY(0); }
    </style>

    <div class="portal-home acc-page">
        <div class="card page-section welcome-block">
            <p class="acc-welcome-eyebrow">Личный кабинет</p>
            <div class="portal-welcome-head">
                <div class="portal-welcome-main">
                    <div class="portal-welcome-avatar" aria-hidden="true">{{ $learnerInitials }}</div>
                    <div>
                        <div class="portal-welcome-greet">{{ $displayName }}</div>
                        @if (! empty($portalWelcomeName))
                            <div class="portal-welcome-email">{{ $learner->email }}</div>
                        @endif
                    </div>
                </div>
                <a class="btn btn-ghost" href="{{ route('portal') }}">Каталог курсов</a>
            </div>

            <div class="portal-welcome-metrics acc-welcome-metrics--4" role="group" aria-label="Сводная статистика">
                <div class="portal-metric">
                    <div class="portal-metric__iconWrap" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            <path d="M8 7h8M8 11h6"/>
                        </svg>
                    </div>
                    <div class="portal-metric__text">
                        <span class="portal-metric__accent" aria-hidden="true"></span>
                        <div class="portal-metric__value">{{ (int) $stats['in_progress'] }}</div>
                        <div class="portal-metric__label">Курсов в процессе</div>
                    </div>
                </div>
                <div class="portal-metric">
                    <div class="portal-metric__iconWrap" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>
                    </div>
                    <div class="portal-metric__text">
                        <span class="portal-metric__accent" aria-hidden="true"></span>
                        <div class="portal-metric__value">{{ (int) $stats['completed'] }}</div>
                        <div class="portal-metric__label">Курсов завершено</div>
                    </div>
                </div>
                <div class="portal-metric portal-metric--time">
                    <div class="portal-metric__iconWrap" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 7v5l3 2"/>
                        </svg>
                    </div>
                    <div class="portal-metric__text">
                        <span class="portal-metric__accent" aria-hidden="true"></span>
                        <div class="portal-metric__value">{{ $stats['total_time_label'] }}</div>
                        <div class="portal-metric__label">Общее время обучения</div>
                    </div>
                </div>
                <div class="portal-metric">
                    <div class="portal-metric__iconWrap" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 3h12a2 2 0 0 1 2 2v14l-4-2-4 2-4-2-4 2V5a2 2 0 0 1 2-2z"/>
                            <path d="M8 8h8M8 12h8M8 16h5"/>
                        </svg>
                    </div>
                    <div class="portal-metric__text">
                        <span class="portal-metric__accent" aria-hidden="true"></span>
                        <div class="portal-metric__value">{{ (int) $stats['certificates_count'] }}</div>
                        <div class="portal-metric__label">Сертификатов получено</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card page-section acc-tabs-card">
            <div class="portal-tag-filters-wrap" role="tablist" aria-label="Разделы личного кабинета">
                <button type="button" class="portal-tag-filter acc-tab is-active" role="tab" id="acc-tab-courses" aria-controls="acc-panel-courses" aria-selected="true">Мои курсы</button>
                <button type="button" class="portal-tag-filter acc-tab" role="tab" id="acc-tab-certs" aria-controls="acc-panel-certs" aria-selected="false">Мои сертификаты</button>
            </div>

        <div id="acc-panel-courses" class="acc-panel is-active" role="tabpanel" aria-labelledby="acc-tab-courses">
            @if (count($courseRows) === 0)
                <div class="acc-empty">
                    <div class="acc-empty__icon-wrap" aria-hidden="true">
                        <svg class="acc-svg-ico" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00b956" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" opacity="0.55"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    </div>
                    <h2 class="acc-empty__title">Вы ещё не начали ни одного курса</h2>
                    <p class="acc-empty__text">Перейдите в каталог и выберите подходящий курс — после старта обучения он появится здесь.</p>
                    <div class="acc-empty__actions">
                        <a class="btn btn-primary" href="{{ route('portal') }}">Смотреть курсы</a>
                    </div>
                </div>
            @else
                <div class="my-courses-grid module-grid portal-catalog-grid">
                    @foreach ($courseRows as $idx => $r)
                        @php
                            /** @var \App\Models\Course $c */
                            $c = $r['course'];
                            $done = ! empty($r['course_completed']);
                            $pct = (int) ($r['progress_bar_percent'] ?? 0);
                            $fromDb = ! empty($r['modules_from_db']);
                            $mp = (int) ($r['modules_passed'] ?? 0);
                            $mt = (int) ($r['modules_total'] ?? 0);
                            $next = (string) ($r['continue_next'] ?? 'default');
                            $mid = (int) ($r['continue_module_id'] ?? 0);
                            $curTitle = $r['current_module_title'] ?? null;
                            $archived = ! empty($r['is_archived']);
                        @endphp
                        <article class="acc-card module-card portal-course-card {{ $done ? 'acc-card--done' : 'course-card-active' }}" style="animation-delay: {{ min(20, (int) $idx) * 50 }}ms" data-acc-card>
                            @if ($archived)
                                <div class="portal-course-card-status portal-course-card-status--archive">Архив</div>
                            @elseif ($done)
                                <div class="portal-course-card-status portal-course-card-status--done">Завершён</div>
                            @else
                                <div class="portal-course-card-status portal-course-card-status--progress">В процессе</div>
                            @endif
                            <div class="portal-course-title" style="font-weight:800;font-size:1.05rem;line-height:1.25">{{ $c->title }}</div>
                            <div class="portal-course-grow">
                                <p class="acc-card__desc muted">{{ $c->summary }}</p>
                                <div style="margin-top:0.85rem">
                                    <div class="portal-course-progress-head muted small">
                                        @if ($fromDb && $mt > 0)
                                            <span>{{ $mp }} из {{ $mt }} модулей</span>
                                            <span><strong>{{ min(100, max(0, $pct)) }}%</strong></span>
                                        @else
                                            <span>Прогресс по курсу: <strong>{{ min(100, max(0, $pct)) }}%</strong></span>
                                        @endif
                                    </div>
                                    <div class="learner-track-summary__bar portal-course-progress-bar" aria-hidden="true">
                                        <div class="learner-track-summary__bar-fill" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="acc-card__footer">
                                <div class="acc-card__footer-meta">
                                    @if ($done)
                                        {{-- completed: optional current line omitted --}}
                                    @elseif ($curTitle)
                                        <div class="acc-pin">
                                            <svg class="acc-svg-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00b956" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                            <span>Остановились на: <strong>{{ $curTitle }}</strong></span>
                                        </div>
                                    @endif
                                    <div class="acc-time">
                                        <svg class="acc-svg-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                        <span>На курсе: {{ $r['time_label'] ?? '0 мин' }}</span>
                                    </div>
                                </div>
                                <div class="acc-card__footer-actions">
                                    @if ($archived)
                                        @if (! empty($r['certificate_available']))
                                            <form method="post" action="{{ route('portal.enroll', ['course' => $c->id]) }}" style="margin:0">
                                                @csrf
                                                <input type="hidden" name="next" value="certificate">
                                                <button type="submit" class="btn btn-primary">Смотреть сертификат</button>
                                            </form>
                                        @else
                                            <p class="muted small acc-card__archived-note" style="margin:0">Курс снят с обучения. Прогресс сохранён.</p>
                                        @endif
                                    @elseif ($done)
                                        <div class="acc-done-row">
                                            <span class="acc-done-txt">
                                                <svg class="acc-svg-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00b956" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 12 2 2 4-4"/></svg>
                                                Пройдено
                                            </span>
                                            @if (! empty($r['certificate_available']))
                                                <form method="post" action="{{ route('portal.enroll', ['course' => $c->id]) }}" style="margin:0">
                                                    @csrf
                                                    <input type="hidden" name="next" value="certificate">
                                                    <button type="submit" class="btn btn-primary">Смотреть сертификат</button>
                                                </form>
                                            @endif
                                        </div>
                                    @else
                                        <form method="post" action="{{ route('portal.enroll', ['course' => $c->id]) }}" style="margin:0">
                                            @csrf
                                            @if ($next === 'module' && $mid > 0)
                                                <input type="hidden" name="next" value="module">
                                                <input type="hidden" name="module" value="{{ $mid }}">
                                            @elseif ($next === 'final_lab')
                                                <input type="hidden" name="next" value="final_lab">
                                            @endif
                                            <button type="submit" class="btn btn-primary">Продолжить</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div id="acc-panel-certs" class="acc-panel" role="tabpanel" aria-labelledby="acc-tab-certs" hidden>
            @if (count($certificates) === 0)
                <div class="empty-state">
                    <div class="empty-icon-wrap" aria-hidden="true">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00b956" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5"><circle cx="12" cy="8" r="6"/><path d="M8 14h8l-1 8H9l-1-8z"/><path d="M9 10h6"/></svg>
                    </div>
                    <h3>Сертификатов пока нет</h3>
                    <p>Завершите курс, чтобы получить первый сертификат</p>
                    <a href="{{ route('portal') }}" class="btn btn-primary">Перейти к курсам</a>
                </div>
            @else
                <div class="acc-cert-grid">
                    @foreach ($certificates as $idx => $cert)
                        <article class="acc-cert-card" style="animation-delay: {{ min(20, (int) $idx) * 50 }}ms" id="{{ $cert['share_anchor'] }}">
                            <div>
                                <div style="font-weight:800;font-size:1rem;line-height:1.25;color:#0f172a">{{ $cert['course_title'] }}</div>
                                <div class="muted" style="margin-top:0.35rem;font-size:0.88rem">Выдан: <strong>{{ $cert['issued_label'] }}</strong></div>
                            </div>
                            <div class="acc-cert-thumb" aria-hidden="true">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#00b956" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" opacity="0.85"><circle cx="12" cy="8" r="6"/><path d="M8 14h8l-1 8H9l-1-8z"/><path d="M9 10h6"/></svg>
                            </div>
                            <div class="acc-cert-actions">
                                <form method="post" action="{{ route('portal.enroll', ['course' => $cert['course_id']]) }}" style="margin:0">
                                    @csrf
                                    <input type="hidden" name="next" value="certificate">
                                    <button type="submit" class="btn btn-primary" title="Откроется итоговая страница: скачивание только в PNG">Сертификат (PNG)</button>
                                </form>
                                <button
                                    type="button"
                                    class="btn btn-ghost acc-js-share"
                                    data-share-url="{{ url(route('account', [], false)) }}?tab=certificates#{{ $cert['share_anchor'] }}"
                                >Поделиться</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
        </div>
    </div>

    <div class="acc-toast" id="acc-toast" role="status" aria-live="polite"></div>

    <script>
        (function () {
            var tabs = document.querySelectorAll('.acc-tab');
            var pCourses = document.getElementById('acc-panel-courses');
            var pCerts = document.getElementById('acc-panel-certs');
            var toast = document.getElementById('acc-toast');
            function showToast(msg) {
                if (!toast) return;
                toast.textContent = msg;
                toast.classList.add('is-on');
                clearTimeout(showToast._t);
                showToast._t = setTimeout(function () { toast.classList.remove('is-on'); }, 2200);
            }
            function setTab(which) {
                var isCourses = which === 'courses';
                tabs.forEach(function (t) {
                    var sel = (isCourses && t.id === 'acc-tab-courses') || (!isCourses && t.id === 'acc-tab-certs');
                    t.setAttribute('aria-selected', sel ? 'true' : 'false');
                    t.classList.toggle('is-active', !!sel);
                });
                if (pCourses) {
                    pCourses.classList.toggle('is-active', isCourses);
                    pCourses.toggleAttribute('hidden', !isCourses);
                }
                if (pCerts) {
                    pCerts.classList.toggle('is-active', !isCourses);
                    pCerts.toggleAttribute('hidden', isCourses);
                }
                try {
                    var u = new URL(window.location.href);
                    if (!isCourses) u.searchParams.set('tab', 'certificates'); else u.searchParams.delete('tab');
                    u.hash = '';
                    history.replaceState({}, '', u.toString());
                } catch (e) {}
            }
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    setTab(tab.id === 'acc-tab-courses' ? 'courses' : 'certs');
                });
            });
            document.querySelectorAll('.acc-js-share').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var url = btn.getAttribute('data-share-url') || '';
                    if (!url) return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(function () {
                            showToast('Ссылка скопирована');
                        }).catch(function () {
                            window.prompt('Скопируйте ссылку', url);
                        });
                    } else {
                        window.prompt('Скопируйте ссылку', url);
                    }
                });
            });
            try {
                var q = new URLSearchParams(window.location.search).get('tab');
                if (q === 'certificates') setTab('certs');
                var h = window.location.hash.replace(/^#/, '');
                if (h && document.getElementById(h)) {
                    setTab('certs');
                    setTimeout(function () {
                        var el = document.getElementById(h);
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 80);
                }
            } catch (e) {}
        })();
    </script>
@endsection
