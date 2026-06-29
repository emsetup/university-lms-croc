@php
    /** @var \Illuminate\Support\Collection|\Illuminate\Support\LazyCollection|array $entries */
    /** @var \App\Services\CourseChangeLogService $changeLogService */
    $entries = $entries ?? collect();
    $changeLogService = $changeLogService ?? app(\App\Services\CourseChangeLogService::class);
    $feedId = $feedId ?? ('ap-chlog-'.substr(md5((string) ($scope ?? 'course')), 0, 8));
    $showCourseLink = ! empty($showCourseLink);
    $canViewStaffProfiles = ! empty($canViewStaffProfiles);
    $emptyMessage = $emptyMessage ?? 'Записей пока нет. Изменения настроек, модулей и разделов будут появляться здесь автоматически.';
    $hasEntries = $entries instanceof \Illuminate\Support\Collection
        ? $entries->isNotEmpty()
        : (is_array($entries) ? $entries !== [] : ! $entries->isEmpty());
@endphp

<div class="ap-chlog-feed" id="{{ $feedId }}" data-ap-chlog-feed>
    <div class="ap-chlog-feed__toolbar">
        <div class="ap-chlog-feed__search-wrap">
            <label class="ap-chlog-feed__search-label" for="{{ $feedId }}-search">Поиск</label>
            <input
                type="search"
                id="{{ $feedId }}-search"
                class="ap-chlog-feed__search"
                placeholder="Действие, автор, поле…"
                autocomplete="off"
                data-ap-chlog-search
            >
        </div>
        <div class="ap-chlog-feed__filters" role="tablist" aria-label="Фильтр по типу изменений" data-ap-chlog-filters>
            <button type="button" class="ap-pill ap-pill--active" data-ap-chlog-filter="all" role="tab" aria-selected="true">Все</button>
            <button type="button" class="ap-pill" data-ap-chlog-filter="course" role="tab" aria-selected="false">Курс</button>
            <button type="button" class="ap-pill" data-ap-chlog-filter="module" role="tab" aria-selected="false">Модуль</button>
            <button type="button" class="ap-pill" data-ap-chlog-filter="section" role="tab" aria-selected="false">Раздел</button>
            <button type="button" class="ap-pill" data-ap-chlog-filter="certificate" role="tab" aria-selected="false">Сертификат</button>
        </div>
        <p class="ap-chlog-feed__count ap-muted" data-ap-chlog-count aria-live="polite"></p>
    </div>

    @if (! $hasEntries)
        <p class="ap-muted ap-chlog-feed__empty">{{ $emptyMessage }}</p>
    @else
        <div class="ap-chlog-feed__scroll" tabindex="0" role="region" aria-label="Журнал изменений">
            <div class="ap-chlog-feed__list" data-ap-chlog-list>
                @foreach ($entries as $entry)
                    @php
                        $author = $changeLogService->staffDisplay($entry->portalStaff);
                        $authorEmail = $changeLogService->staffEmail($entry->portalStaff);
                        $staffId = $entry->portalStaff ? (int) $entry->portalStaff->id : null;
                        $when = $entry->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i');
                        $whenShort = $entry->created_at?->timezone(config('app.timezone'))->format('d.m H:i');
                        $changes = is_array($entry->details['changes'] ?? null) ? $entry->details['changes'] : [];
                        $area = (string) $entry->area;
                        $areaLabel = $changeLogService->areaLabel($area);
                        $courseTitle = (string) ($entry->course?->title ?? '');
                        $courseSlug = (string) ($entry->course?->slug ?? '');
                        $searchBlob = mb_strtolower(implode(' ', array_filter([
                            $when,
                            $areaLabel,
                            (string) $entry->summary,
                            $author,
                            $authorEmail,
                            $courseTitle,
                            $courseSlug,
                        ])), 'UTF-8');
                        $authorInitials = \App\Support\LearnerDisplay::initials($authorEmail, $author !== 'Система' ? $author : '');
                    @endphp
                    <details
                        class="ap-chlog-row ap-chlog-row--area-{{ $area }}"
                        data-ap-chlog-row
                        data-ap-area="{{ e($area) }}"
                        data-ap-search="{{ e($searchBlob) }}"
                    >
                        <summary class="ap-chlog-row__summary">
                            <time class="ap-chlog-row__time" datetime="{{ $entry->created_at?->toIso8601String() }}" title="{{ $when }}">{{ $whenShort }}</time>
                            <span class="ap-chlog-row__area">{{ $areaLabel }}</span>
                            <span class="ap-chlog-row__text">{{ $entry->summary }}</span>
                            @if ($showCourseLink && $courseSlug !== '')
                                <span class="ap-chlog-row__course" aria-hidden="true">· {{ $courseTitle }}</span>
                            @endif
                            <span class="ap-chlog-row__author">
                                <span class="ap-chlog-row__author-avatar" aria-hidden="true">{{ $authorInitials }}</span>
                                <span class="ap-chlog-row__author-name">{{ $author }}</span>
                            </span>
                            @if ($changes !== [])
                                <span class="ap-chlog-row__badge">{{ count($changes) }}</span>
                            @endif
                            <span class="ap-chlog-row__chevron" aria-hidden="true">
                                @include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])
                            </span>
                        </summary>
                        <div class="ap-chlog-row__body">
                            <dl class="ap-chlog-row__meta-grid">
                                <div>
                                    <dt>Дата и время</dt>
                                    <dd><time datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $when }}</time></dd>
                                </div>
                                <div>
                                    <dt>Автор</dt>
                                    <dd>
                                        @if ($staffId && $canViewStaffProfiles)
                                            <a href="{{ route('admin.staff.show', ['staff' => $staffId]) }}">{{ $author }}</a>
                                        @else
                                            {{ $author }}
                                        @endif
                                        @if ($authorEmail !== '' && $authorEmail !== $author)
                                            <span class="ap-muted"> · {{ $authorEmail }}</span>
                                        @endif
                                    </dd>
                                </div>
                                @if ($showCourseLink && $courseSlug !== '')
                                    <div>
                                        <dt>Курс</dt>
                                        <dd>
                                            <a href="{{ route('admin.course.settings', ['adminCourse' => $courseSlug, 'tab' => 'istoriya']) }}">{{ $courseTitle }}</a>
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                            @if ($changes !== [])
                                <table class="ap-chlog-row__changes">
                                    <thead>
                                    <tr>
                                        <th scope="col">Поле</th>
                                        <th scope="col">Было</th>
                                        <th scope="col">Стало</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($changes as $ch)
                                        <tr>
                                            <td>{{ $ch['label'] ?? $ch['field'] ?? '—' }}</td>
                                            <td><code>{{ $ch['old'] ?? '—' }}</code></td>
                                            <td><code>{{ $ch['new'] ?? '—' }}</code></td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="ap-muted ap-chlog-row__no-details">Дополнительных полей для этой записи нет.</p>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>
            <p class="ap-muted ap-chlog-feed__no-match" data-ap-chlog-no-match hidden>Ничего не найдено. Измените запрос или сбросьте фильтр.</p>
        </div>
    @endif
</div>

@if ($hasEntries)
    <script>
        (function () {
            var root = document.getElementById(@json($feedId));
            if (!root) return;

            var search = root.querySelector('[data-ap-chlog-search]');
            var pills = root.querySelectorAll('[data-ap-chlog-filter]');
            var rows = root.querySelectorAll('[data-ap-chlog-row]');
            var countEl = root.querySelector('[data-ap-chlog-count]');
            var noMatch = root.querySelector('[data-ap-chlog-no-match]');
            var currentFilter = 'all';

            function normalize(s) {
                return (s || '').toLowerCase().trim();
            }

            function updateCount(visible, total) {
                if (!countEl) return;
                if (visible === total) {
                    countEl.textContent = total + ' ' + (total === 1 ? 'запись' : (total >= 2 && total <= 4 ? 'записи' : 'записей'));
                } else {
                    countEl.textContent = 'Показано ' + visible + ' из ' + total;
                }
            }

            function applyFilters() {
                var q = normalize(search ? search.value : '');
                var total = rows.length;
                var visible = 0;

                rows.forEach(function (row) {
                    var area = row.getAttribute('data-ap-area') || '';
                    var blob = row.getAttribute('data-ap-search') || '';
                    var okArea = currentFilter === 'all' || area === currentFilter;
                    var okSearch = !q || blob.indexOf(q) !== -1;
                    var show = okArea && okSearch;
                    row.style.display = show ? '' : 'none';
                    row.setAttribute('data-ap-visible', show ? '1' : '0');
                    if (show) visible++;
                });

                if (noMatch) {
                    noMatch.hidden = visible > 0 || total === 0;
                }
                updateCount(visible, total);
            }

            pills.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    currentFilter = btn.getAttribute('data-ap-chlog-filter') || 'all';
                    pills.forEach(function (p) {
                        var on = p === btn;
                        p.classList.toggle('ap-pill--active', on);
                        p.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    applyFilters();
                });
            });

            if (search) {
                search.addEventListener('input', applyFilters);
            }

            applyFilters();
        })();
    </script>
@endif
