@extends('layouts.admin')

@section('title', 'Обучающиеся — Трек знаний')

@section('content')
    @php
        $detailBase = url('/adm/lyudi');
    @endphp
    <div class="ap-people" id="ap-people-root" data-detail-base="{{ e($detailBase) }}">
        <aside class="ap-people__sidebar" aria-label="Список обучающихся">
            <div class="ap-people__sidebar-inner">
                <label class="ap-people__label" for="ap-people-search">Поиск по email или ФИО</label>
                <input type="search" id="ap-people-search" class="ap-people__search" placeholder="Email или ФИО…" autocomplete="off">

                <label class="ap-people__label" for="ap-people-course">Курс</label>
                <select id="ap-people-course" class="ap-people__select">
                    <option value="">Все курсы</option>
                    @foreach ($courseFilterOptions as $opt)
                        <option value="{{ (int) $opt['id'] }}">{{ $opt['title'] }}</option>
                    @endforeach
                </select>

                <div class="ap-people__sort-toggle ap-toggle-row">
                    <label class="ap-toggle">
                        <input type="checkbox" id="ap-people-sort-active" class="ap-toggle__input" value="1">
                        <span class="ap-toggle__track" aria-hidden="true"></span>
                        <span class="ap-toggle__label">Сначала недавно активные</span>
                    </label>
                </div>

                <div class="ap-people__list" role="listbox" aria-label="Обучающиеся" id="ap-people-list">
                    @forelse ($leftList as $row)
                        <button
                            type="button"
                            class="ap-people-row"
                            role="option"
                            id="ap-people-row-{{ (int) $row['id'] }}"
                            data-learner-id="{{ (int) $row['id'] }}"
                            data-email="{{ e(mb_strtolower($row['email'], 'UTF-8')) }}"
                            data-full-name="{{ e(mb_strtolower((string) ($row['full_name'] ?? ''), 'UTF-8')) }}"
                            data-course-ids="{{ e(implode(',', $row['course_ids'])) }}"
                            data-last-active-ts="{{ (int) ($row['last_active_ts'] ?? 0) }}"
                            data-last-active-by-course="{{ e(json_encode($row['last_active_by_course'] ?? [], JSON_UNESCAPED_UNICODE)) }}"
                            aria-selected="false"
                        >
                            <span class="ap-people-row__avatar" aria-hidden="true">{{ $row['initials'] }}</span>
                            <span class="ap-people-row__text">
                                @if (! empty($row['full_name']))
                                    <span class="ap-people-row__title">{{ $row['full_name'] }}</span>
                                    <span class="ap-people-row__subline">
                                        <span class="ap-people-row__email-muted">{{ $row['email'] }}</span>
                                        <span class="ap-people-row__dot"> · </span>
                                        <span class="ap-people-row__meta">{{ (int) $row['course_count'] }} @choice('курс|курса|курсов', (int) $row['course_count'])</span>
                                    </span>
                                @else
                                    <span class="ap-people-row__email">{{ $row['email'] }}</span>
                                    <span class="ap-people-row__meta">{{ (int) $row['course_count'] }} @choice('курс|курса|курсов', (int) $row['course_count'])</span>
                                @endif
                            </span>
                        </button>
                    @empty
                        <p class="ap-muted ap-small" style="padding:0.5rem 0.25rem">Нет обучающихся в доступном вам списке курсов.</p>
                    @endforelse
                </div>
            </div>
        </aside>

        <section class="ap-people__detail" aria-live="polite" aria-atomic="true" id="ap-people-detail">
            <div class="ap-people-detail-empty" id="ap-people-detail-empty">
                <div class="ap-people-detail-empty__icon" aria-hidden="true">
                    @include('partials.ap-icon', ['name' => 'users', 'size' => 'lg'])
                </div>
                <p class="ap-people-detail-empty__text">Выберите обучающегося из списка</p>
            </div>
            <div class="ap-people-detail-body is-hidden" id="ap-people-detail-body"></div>
        </section>
    </div>

    <script>
        (function () {
            var root = document.getElementById('ap-people-root');
            var detailBase = root ? root.getAttribute('data-detail-base') : '';
            var search = document.getElementById('ap-people-search');
            var courseSel = document.getElementById('ap-people-course');
            var sortActiveEl = document.getElementById('ap-people-sort-active');
            var list = document.getElementById('ap-people-list');
            var emptyEl = document.getElementById('ap-people-detail-empty');
            var bodyEl = document.getElementById('ap-people-detail-body');
            var rows = list ? Array.prototype.slice.call(list.querySelectorAll('.ap-people-row')) : [];
            var activeId = null;
            var sortStorageKey = 'ap-people-sort-active';

            function sortActiveEnabled() {
                return !!(sortActiveEl && sortActiveEl.checked);
            }

            function activeTsForRow(btn) {
                var cid = courseSel && courseSel.value ? parseInt(courseSel.value, 10) : 0;
                if (cid) {
                    try {
                        var map = JSON.parse(btn.getAttribute('data-last-active-by-course') || '{}');
                        return parseInt(map[String(cid)] || map[cid] || 0, 10) || 0;
                    } catch (e) {
                        return 0;
                    }
                }
                return parseInt(btn.getAttribute('data-last-active-ts') || '0', 10) || 0;
            }

            function nameSortKey(btn) {
                var fn = (btn.getAttribute('data-full-name') || '').trim();
                return (fn || btn.getAttribute('data-email') || '').toLowerCase();
            }

            function applyListOrder() {
                if (!list) {
                    return;
                }
                var sorted = rows.slice().sort(function (a, b) {
                    if (sortActiveEnabled()) {
                        var diff = activeTsForRow(b) - activeTsForRow(a);
                        if (diff !== 0) {
                            return diff;
                        }
                    }
                    var ea = nameSortKey(a);
                    var eb = nameSortKey(b);
                    return ea < eb ? -1 : ea > eb ? 1 : 0;
                });
                sorted.forEach(function (btn) {
                    list.appendChild(btn);
                });
            }

            function applyFilters() {
                var q = (search && search.value ? search.value : '').toLowerCase().trim();
                var cid = courseSel && courseSel.value ? parseInt(courseSel.value, 10) : 0;
                rows.forEach(function (btn) {
                    var email = btn.getAttribute('data-email') || '';
                    var fn = btn.getAttribute('data-full-name') || '';
                    var okQ = !q || email.indexOf(q) !== -1 || fn.indexOf(q) !== -1;
                    var ids = (btn.getAttribute('data-course-ids') || '').split(',').filter(Boolean).map(function (x) { return parseInt(x, 10); });
                    var okC = !cid || ids.indexOf(cid) !== -1;
                    btn.style.display = okQ && okC ? '' : 'none';
                });
                applyListOrder();
            }

            function setActive(btn) {
                rows.forEach(function (b) {
                    var on = btn && b === btn;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
            }

            function escapeHtml(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function renderDetail(data) {
                var initials = escapeHtml(data.initials || '—');
                var email = escapeHtml(data.email || '');
                var fnRaw = (data.full_name || '').trim();
                var fn = escapeHtml(fnRaw);
                var hasName = fnRaw.length > 0;
                var cards = (data.courses || []).map(function (c) {
                    var pct = parseInt(c.track_avg_percent, 10) || 0;
                    return (
                        '<article class="ap-people-course-card">' +
                        '<h3 class="ap-people-course-card__title">' + escapeHtml(c.title) + '</h3>' +
                        '<div class="ap-people-course-card__stats">' +
                        (parseInt(c.modules_passed, 10) || 0) + ' / ' + (parseInt(c.module_total, 10) || 0) +
                        ' модулей · ' + pct + '%' +
                        '</div>' +
                        '<div class="ap-mini-progress ap-people-course-card__bar" role="progressbar" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100">' +
                        '<div class="ap-mini-progress__bar" style="width:' + pct + '%"></div></div>' +
                        '<p class="ap-people-course-card__stopped">Остановился на: <strong>' + escapeHtml(c.stopped_label) + '</strong></p>' +
                        '<a class="btn btn-primary ap-people-course-card__link" href="' + String(c.open_url).replace(/"/g, '&quot;') + '">Открыть в курсе' +
                        '<svg xmlns="http://www.w3.org/2000/svg" class="ap-icon ap-icon--sm" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" style="width:1rem;height:1rem;margin-left:0.25rem">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg></a>' +
                        '</article>'
                    );
                }).join('');

                var headText = hasName
                    ? '<div class="ap-people-detail-head__title">' + fn + '</div>' +
                      '<div class="ap-people-detail-head__subtitle">' + email + '</div>'
                    : '<div class="ap-people-detail-head__title">' + email + '</div>';

                return (
                    '<div class="ap-people-detail-inner ap-people-detail-inner--enter">' +
                    '<div class="ap-people-detail-head">' +
                    '<span class="ap-people-detail-head__avatar" aria-hidden="true">' + initials + '</span>' +
                    '<div class="ap-people-detail-head__text">' + headText + '</div></div>' +
                    '<div class="ap-people-course-grid">' + cards + '</div>' +
                    '</div>'
                );
            }

            function showEmpty() {
                activeId = null;
                if (emptyEl) emptyEl.classList.remove('is-hidden');
                if (bodyEl) {
                    bodyEl.classList.add('is-hidden');
                    bodyEl.innerHTML = '';
                }
                setActive(null);
            }

            function loadLearner(id, btn) {
                if (!detailBase || !bodyEl || !emptyEl) return;
                fetch(detailBase + '/' + id, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function (data) {
                        activeId = id;
                        emptyEl.classList.add('is-hidden');
                        bodyEl.classList.remove('is-hidden');
                        bodyEl.innerHTML = renderDetail(data);
                        setActive(btn);
                        requestAnimationFrame(function () {
                            var inner = bodyEl.querySelector('.ap-people-detail-inner--enter');
                            if (inner) inner.classList.add('is-visible');
                        });
                    })
                    .catch(function () {
                        showEmpty();
                    });
            }

            rows.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-learner-id');
                    if (!id) return;
                    loadLearner(id, btn);
                });
            });

            if (search) search.addEventListener('input', applyFilters);
            if (courseSel) courseSel.addEventListener('change', applyFilters);
            if (sortActiveEl) {
                try {
                    sortActiveEl.checked = localStorage.getItem(sortStorageKey) === '1';
                } catch (e) {}
                sortActiveEl.addEventListener('change', function () {
                    try {
                        localStorage.setItem(sortStorageKey, sortActiveEl.checked ? '1' : '0');
                    } catch (e) {}
                    applyFilters();
                });
            }

            applyFilters();
        })();
    </script>
@endsection
