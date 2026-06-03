@extends($layout ?? 'layouts.course')

@php
    use App\Support\DurationFormat;
@endphp

@section('title', 'Сводка по обучающимся (преподаватель)')

@section('content')
    <div class="teacher-course-summary">
    <div class="tr-hero">
        <h1>Сводка прохождения курса</h1>
        @if (! empty($courseTitle))
            <p class="muted" style="margin:0.25rem 0 0">
                Курс: <strong>{{ $courseTitle }}</strong>
            </p>
        @endif
        @if (($layout ?? '') === 'layouts.admin')
            <p class="muted" style="margin:0">
                Просмотр из панели администратора. Карточки обучающихся открываются в режиме отчёта преподавателя для авторизованных сотрудников портала.
            </p>
        @else
            <p class="muted" style="margin:0">
                Сначала выберите обучающегося, затем модуль — внутри модуля отдельно тест по теории, практика и экзамен с попытками и возможностью сброса.
            </p>
        @endif
        @if (! empty($courseCounters))
            <p class="muted small" style="margin:0.6rem 0 0;line-height:1.45">
                Зачислено: <strong>{{ (int) $courseCounters['enrolled'] }}</strong> ·
                Начали: <strong>{{ (int) $courseCounters['started'] }}</strong> ·
                Завершили: <strong>{{ (int) $courseCounters['completed'] }}</strong>
            </p>
        @endif
        @php
            $reportModuleCount = (int) ($courseModuleCount ?? \App\Services\CourseScoringService::moduleCount());
            $reportMaxCoursePoints = (int) ($maxCoursePoints ?? ($reportModuleCount * \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE + \App\Services\CourseScoringService::MAX_FINAL_LAB_POINTS));
            $reportMaxModulePoints = $reportModuleCount * \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE;
        @endphp
        <p class="muted small" style="margin:0.5rem 0 0">
            <strong>Баллы за модуль:</strong> взвешенное среднее процентов теста по теории, практики и итогового теста (веса {{ (int) (\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_THEORY_QUIZ * 100) }}/{{ (int) (\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_PRACTICE * 100) }}/{{ (int) (\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_EXAM * 100) }}), максимум {{ \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE }} за модуль; сумма по курсу — до {{ $reportMaxModulePoints }}; финальная лаба — до {{ \App\Services\CourseScoringService::MAX_FINAL_LAB_POINTS }}. «Итого курс» — модули + финал (макс. {{ $reportMaxCoursePoints }}).
        </p>
    </div>

    <div class="card teacher-report-summary" style="margin-top:0">
        <div class="teacher-report-summary__head">
            <h2 style="margin:0">Все обучающиеся</h2>
            @if (($layout ?? '') === 'layouts.admin')
                <div class="ap-toggle-row teacher-report-summary__sort">
                    <label class="ap-toggle">
                        <input type="checkbox" id="ap-course-learners-sort-active" class="ap-toggle__input" value="1">
                        <span class="ap-toggle__track" aria-hidden="true"></span>
                        <span class="ap-toggle__label">Сначала недавно активные</span>
                    </label>
                </div>
            @endif
        </div>
        <div class="tr-table-wrap">
            <table class="teacher-report-table" id="ap-course-learners-table">
                <thead>
                <tr>
                    <th>Обучающийся</th>
                    <th>Модули сданы</th>
                    <th>Баллы (модули)</th>
                    <th>Итого курс</th>
                    <th>Финал (баллы)</th>
                    <th>Период (дн.)</th>
                    <th>Время в курсе (учёт)</th>
                    <th>Ориент. мин. тесты</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($learnerRows as $row)
                    <tr
                        data-user-email="{{ e($row['email']) }}"
                        data-last-active-ts="{{ (int) ($row['last_active_ts'] ?? 0) }}"
                        data-sort-name="{{ e(mb_strtolower(trim((string) (($row['full_name'] ?? '') !== '' ? $row['full_name'] : $row['email'])), 'UTF-8')) }}"
                    >
                        <td>
                            @if (! empty($row['full_name']))
                                <div class="learner-cell-name">{{ $row['full_name'] }}</div>
                                <div class="learner-cell-email">{{ $row['email'] }}</div>
                            @else
                                <div class="learner-cell-name">{{ $row['email'] }}</div>
                            @endif
                            @php
                                $learnerCardUrl = ! empty($adminCourseSlug)
                                    ? route('admin.learners.course.learner', ['adminCourse' => $adminCourseSlug, 'learner' => $row['id']])
                                    : route('teacher.course-report.learner', $row['id']);
                            @endphp
                            <a class="learner-cell-link" href="{{ $learnerCardUrl }}">Карточка →</a>
                        </td>
                        <td>{{ $row['modules_passed_count'] }} / {{ (int) ($row['module_count'] ?? $reportModuleCount) }}</td>
                        <td>
                            {{ $row['total_module_points'] }} / {{ $row['max_module_points'] }}
                            <span class="muted small">({{ $row['module_points_percent'] }}%)</span>
                        </td>
                        <td>
                            <strong>{{ $row['grand_total'] }} / {{ $row['max_grand_total'] }}</strong>
                            <span class="muted small">({{ $row['grand_total_percent'] }}%)</span>
                        </td>
                        <td>
                            {{ $row['final_lab_points'] }} / {{ $row['max_final_lab_points'] }}
                            @if ($row['final_lab'])
                                <span class="muted small"><br>{{ $row['final_lab']['passed'] ? 'зачтено' : 'нет' }}, лучший {{ $row['final_lab']['best_score'] }}%</span>
                            @endif
                        </td>
                        <td>{{ $row['time']['span_days'] !== null ? $row['time']['span_days'] : '—' }}</td>
                        <td class="teacher-report-nowrap">{{ DurationFormat::fromSeconds($row['time_tracked']['total'] ?? 0) }}</td>
                        <td>{{ $row['time']['estimated_test_minutes'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="muted small" style="margin:0.75rem 0 0">
            «Время в курсе» — сумма сохранённых интервалов по теории, тесту по теории, практике и итоговому тесту по всем модулям. «Ориент. мин. тесты» — лимит × число попыток, без фактического времени ответа.
        </p>
    </div>

    @if (($layout ?? '') === 'layouts.admin')
        <script>
            (function () {
                var sortEl = document.getElementById('ap-course-learners-sort-active');
                var table = document.getElementById('ap-course-learners-table');
                var tbody = table ? table.querySelector('tbody') : null;
                var sortKey = 'ap-course-learners-sort-active';

                function applyTableOrder() {
                    if (!tbody) return;
                    var trs = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-user-email]'));
                    var byActive = !!(sortEl && sortEl.checked);
                    trs.sort(function (a, b) {
                        if (byActive) {
                            var diff = (parseInt(b.getAttribute('data-last-active-ts') || '0', 10) || 0)
                                - (parseInt(a.getAttribute('data-last-active-ts') || '0', 10) || 0);
                            if (diff !== 0) return diff;
                        }
                        var na = a.getAttribute('data-sort-name') || '';
                        var nb = b.getAttribute('data-sort-name') || '';
                        return na < nb ? -1 : na > nb ? 1 : 0;
                    });
                    trs.forEach(function (tr) { tbody.appendChild(tr); });
                }

                if (sortEl) {
                    try { sortEl.checked = localStorage.getItem(sortKey) === '1'; } catch (e) {}
                    sortEl.addEventListener('change', function () {
                        try { localStorage.setItem(sortKey, sortEl.checked ? '1' : '0'); } catch (e) {}
                        applyTableOrder();
                    });
                    applyTableOrder();
                }

                var q = new URLSearchParams(window.location.search).get('user');
                if (!q) return;
                var decoded = '';
                try { decoded = decodeURIComponent(q); } catch (e) { decoded = q; }
                document.querySelectorAll('tr[data-user-email]').forEach(function (tr) {
                    if (tr.getAttribute('data-user-email') === decoded) {
                        tr.style.background = '#f0fdf4';
                        tr.style.boxShadow = 'inset 3px 0 0 #00b956';
                        tr.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    }
                });
            })();
        </script>
    @endif
    </div>
@endsection
