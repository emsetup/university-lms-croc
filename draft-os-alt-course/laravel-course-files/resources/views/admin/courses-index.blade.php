@extends('layouts.course')

@section('title', 'Админ: курсы')

@section('content')
    <style>
        .admin-course-card {
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }
        .admin-course-title { min-height: 2.7rem; }
        .admin-course-grow { flex: 1; min-height: 0; }
        .admin-course-actions { margin-top: auto; }

        .course-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 0.85rem;
        }
        .icon-strip {
            display: inline-flex;
            gap: 0.45rem;
            padding: 0.4rem;
            border-radius: 12px;
            border: 1px solid var(--line, #dfe8e4);
            background: linear-gradient(180deg, rgba(244, 250, 247, 0.9), #fff);
            box-shadow: 0 2px 12px rgba(15, 42, 30, 0.05);
        }
        .icon-btn {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid rgba(10, 119, 85, 0.18);
            background: #fff;
            color: var(--accent, #0a7);
            cursor: pointer;
            text-decoration: none;
            position: relative;
            transition: transform 0.12s ease, box-shadow 0.12s ease, background 0.12s ease, border-color 0.12s ease, color 0.12s ease;
        }
        .icon-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(2, 6, 23, 0.10);
            border-color: rgba(10, 119, 85, 0.35);
            background: rgba(10, 119, 85, 0.06);
        }
        .icon-btn:active { transform: translateY(0); box-shadow: 0 2px 8px rgba(2, 6, 23, 0.08); }
        .icon-btn svg { width: 20px; height: 20px; }
        .icon-btn[data-tip]::after {
            content: attr(data-tip);
            position: absolute;
            left: 50%;
            bottom: calc(100% + 10px);
            transform: translateX(-50%) translateY(4px);
            opacity: 0;
            pointer-events: none;
            white-space: nowrap;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.35rem 0.55rem;
            border-radius: 10px;
            border: 1px solid rgba(15, 23, 42, 0.10);
            background: rgba(255, 255, 255, 0.96);
            color: #0f172a;
            box-shadow: 0 16px 40px rgba(2, 6, 23, 0.14);
            transition: opacity 0.12s ease, transform 0.12s ease;
        }
        .icon-btn[data-tip]::before {
            content: '';
            position: absolute;
            left: 50%;
            bottom: calc(100% + 4px);
            transform: translateX(-50%);
            opacity: 0;
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.96);
            border-left: 1px solid rgba(15, 23, 42, 0.10);
            border-bottom: 1px solid rgba(15, 23, 42, 0.10);
            rotate: 45deg;
            transition: opacity 0.12s ease;
        }
        .icon-btn:hover::after,
        .icon-btn:hover::before,
        .icon-btn:focus-visible::after,
        .icon-btn:focus-visible::before {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        .icon-btn:focus-visible { outline: 2px solid rgba(10, 119, 85, 0.45); outline-offset: 2px; }

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
            max-width: min(980px, 98vw);
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
        .course-info-modal__footer { margin-top: 0.85rem; padding-top: 0.65rem; border-top: 1px solid var(--line, #e5e7eb); display:flex; justify-content:space-between; gap:0.6rem; flex-wrap:wrap; align-items:center; }
        .course-info-modal__title { margin: 0 0 0.25rem; font-size: 1.15rem; padding-right: 2rem; font-weight: 900; }
        .course-info-modal__slug { margin: 0 0 0.65rem; }
        .course-info-modal__summary { line-height: 1.6; color: #334155; white-space: pre-wrap; }
    </style>

    <div class="card" style="max-width:1200px;margin:0 auto 1rem">
        @include('partials.admin-instructor-nav', ['active' => 'courses'])
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <h1 style="margin:0 0 0.35rem">Курсы портала</h1>
                <p class="muted" style="margin:0;max-width:62rem;line-height:1.5">
                    Здесь видны курсы, доступные в портале. Сейчас содержимое модулей, тестов и практики относится к курсу
                    <strong>«Особенности ОС «Альт»»</strong>.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                @if (!empty($showArchived))
                    <a class="btn btn-ghost" href="{{ route('admin.courses.index') }}">Скрыть архив</a>
                @else
                    <a class="btn btn-ghost" href="{{ route('admin.courses.index', ['archived' => 1]) }}">Показать архив</a>
                @endif
                @if (! empty($canCreateCourse))
                    <a class="btn btn-primary" href="{{ route('admin.courses.create') }}">Создать курс</a>
                @endif
                <a class="btn btn-ghost" href="{{ route('admin.panel') }}">К панели</a>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:1200px;margin:0 auto">
        <h2 style="margin-top:0">Список</h2>
        <div class="module-grid courses-catalog-grid" style="margin-top:0.85rem">
            @foreach ($courses as $c)
                <div class="module-card admin-course-card js-admin-course-card"
                     role="button"
                     tabindex="0"
                     data-course-id="{{ (int) $c['id'] }}"
                     data-course-title="{{ e($c['title']) }}"
                     data-course-slug="{{ e($c['slug']) }}"
                     data-course-summary="{{ e($c['summary']) }}"
                     data-course-enrolled="{{ (int) $c['enrolled'] }}"
                     data-course-completed="{{ (int) ($c['completed'] ?? 0) }}"
                     data-course-published="{{ !empty($c['is_published']) ? '1' : '0' }}"
                     data-course-archived="{{ !empty($c['is_archived']) ? '1' : '0' }}">
                    <div class="tag">Курс</div>
                    <div class="admin-course-title" style="font-weight:800;font-size:1.05rem;line-height:1.25">{{ $c['title'] }}</div>
                    <div class="muted small" style="margin-top:0.25rem">slug: <code>{{ $c['slug'] }}</code></div>
                    <div class="admin-course-grow">
                        <div class="muted course-card__description" style="font-size:0.92rem;line-height:1.45;margin-top:0.35rem">{{ $c['summary'] }}</div>
                    </div>
                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:0.85rem;align-items:center">
                        <span class="muted small">Участников: <strong>{{ (int) $c['enrolled'] }}</strong></span>
                        <span class="muted small">Завершили: <strong>{{ (int) ($c['completed'] ?? 0) }}</strong></span>
                        @if (!empty($c['is_published']))
                            <span class="muted small">статус: <strong>опубликован</strong></span>
                        @else
                            <span class="muted small">статус: <strong>черновик</strong></span>
                        @endif
                        @if (!empty($c['is_archived']))
                            <span class="muted small">архив: <strong>да</strong></span>
                        @endif
                    </div>
                    <div class="course-actions admin-course-actions">
                        @php
                            $cid = (int) $c['id'];
                            $canEditThis = $editableCourseIds === null || isset($editableCourseIds[$cid]);
                            $canTools = $portalStaffAccess && $portalStaffAccess->canUseCourseAdminTools();
                        @endphp
                        <div class="icon-strip" role="group" aria-label="Действия с курсом">
                            @if ($canEditThis && $portalStaffAccess && ! $portalStaffAccess->isCourseTester())
                                <a class="icon-btn" href="{{ route('admin.courses.edit', ['course' => $cid]) }}" data-tip="Редактировать" aria-label="Редактировать">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2 2 0 0 0 0-3L16.5 4.5a2 2 0 0 0-3 0L3 15v5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13.5 6.5l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                </a>
                            @endif

                            <form method="post" action="{{ route('admin.courses.select', ['course' => $cid]) }}" style="margin:0">
                                @csrf
                                <input type="hidden" name="next" value="content">
                                <button type="submit" class="icon-btn" data-tip="Содержимое" aria-label="Содержимое">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/><path d="M8 7h8M8 11h8M8 15h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                </button>
                            </form>

                            <form method="post" action="{{ route('admin.courses.select', ['course' => $cid]) }}" style="margin:0">
                                @csrf
                                <input type="hidden" name="next" value="quiz">
                                <button type="submit" class="icon-btn" data-tip="Вопросы" aria-label="Вопросы">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7.5 3h9A2.5 2.5 0 0 1 19 5.5v9A2.5 2.5 0 0 1 16.5 17H12l-4 4v-4H7.5A2.5 2.5 0 0 1 5 14.5v-9A2.5 2.5 0 0 1 7.5 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8.3 8.2c.4-1 1.4-1.7 2.7-1.7 1.6 0 2.7.9 2.7 2.2 0 1-.6 1.6-1.4 2-.8.4-1.2.7-1.2 1.6v.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 14.9h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                                </button>
                            </form>

                            @if ($canTools)
                                <form method="post" action="{{ route('admin.courses.select', ['course' => $cid]) }}" style="margin:0">
                                    @csrf
                                    <input type="hidden" name="next" value="certificates">
                                    <button type="submit" class="icon-btn" data-tip="Сертификаты" aria-label="Сертификаты">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/><path d="M9 7h6M9 10h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 15v6l2-1 2 1v-6" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                                    </button>
                                </form>

                                <form method="post" action="{{ route('admin.courses.select', ['course' => $cid]) }}" style="margin:0">
                                    @csrf
                                    <input type="hidden" name="next" value="learners">
                                    <button type="submit" class="icon-btn" data-tip="Обучающиеся" aria-label="Обучающиеся">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 19a4 4 0 0 0-8 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="1.8"/><path d="M20 19c0-2.1-1.1-3.6-3-4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="course-info-modal" id="admin-course-info-modal" aria-hidden="true">
        <div class="course-info-modal__backdrop" data-course-info-close tabindex="-1"></div>
        <div class="course-info-modal__panel" role="dialog" aria-modal="true" aria-labelledby="admin-course-info-title">
            <button type="button" class="course-info-modal__close" data-course-info-close aria-label="Закрыть">&times;</button>
            <div class="tag" style="margin-bottom:0.65rem">Курс (админ)</div>
            <div id="admin-course-info-title" class="course-info-modal__title">Курс</div>
            <div class="muted small course-info-modal__slug" id="admin-course-info-slug"></div>
            <div class="course-info-modal__body">
                <div class="muted small" id="admin-course-info-stats" style="margin:0 0 0.65rem"></div>
                <div class="course-info-modal__summary" id="admin-course-info-summary"></div>
            </div>
            <div class="course-info-modal__footer">
                <div id="admin-course-info-actions"></div>
                <button type="button" class="btn btn-ghost" data-course-info-close>Закрыть</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('admin-course-info-modal');
            var titleEl = document.getElementById('admin-course-info-title');
            var slugEl = document.getElementById('admin-course-info-slug');
            var statsEl = document.getElementById('admin-course-info-stats');
            var summaryEl = document.getElementById('admin-course-info-summary');
            var actionsEl = document.getElementById('admin-course-info-actions');
            if (!modal || !titleEl || !slugEl || !statsEl || !summaryEl || !actionsEl) return;

            var lastFocus = null;
            function openModal(card) {
                lastFocus = document.activeElement;
                titleEl.textContent = card.getAttribute('data-course-title') || 'Курс';
                var slug = card.getAttribute('data-course-slug') || '';
                slugEl.innerHTML = slug ? ('slug: <code>' + slug + '</code>') : '';

                var enrolled = parseInt(card.getAttribute('data-course-enrolled') || '0', 10) || 0;
                var completed = parseInt(card.getAttribute('data-course-completed') || '0', 10) || 0;
                var pub = card.getAttribute('data-course-published') === '1';
                var arch = card.getAttribute('data-course-archived') === '1';
                statsEl.innerHTML =
                    'Участников: <strong>' + enrolled + '</strong> · Завершили: <strong>' + completed + '</strong> · ' +
                    'статус: <strong>' + (pub ? 'опубликован' : 'черновик') + '</strong>' +
                    (arch ? ' · архив: <strong>да</strong>' : '');

                summaryEl.textContent = card.getAttribute('data-course-summary') || '';
                actionsEl.innerHTML = '';
                var strip = card.querySelector('.icon-strip');
                if (strip) {
                    actionsEl.appendChild(strip.cloneNode(true));
                }

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
                return !!e.target.closest('button, a, form, input, select, textarea, .icon-strip');
            }
            document.querySelectorAll('.js-admin-course-card').forEach(function (card) {
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

