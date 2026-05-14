@extends($layout ?? 'layouts.course')

@php
    use App\Support\DurationFormat;
@endphp

@section('title', 'Сводка по обучающимся (преподаватель)')

@section('content')
    <style>
        .tr-hero{border:1px solid var(--line,#e1e8ea);border-radius:16px;padding:1.1rem 1.2rem;background:linear-gradient(160deg,#f6faf8,#fff);margin-bottom:1rem;box-shadow:0 2px 10px rgba(15,23,42,.04)}
        .tr-hero h1{margin:0 0 0.35rem;font-size:1.35rem}
        .tr-table-wrap{overflow:auto;border-radius:12px;border:1px solid var(--line,#e1e8ea)}
        .tr-table-wrap table{margin:0}
        .tr-email{font-weight:600}
        .tr-go{font-size:0.82rem;font-weight:600;color:var(--accent,#0a7);white-space:nowrap}
        .learner-cell-name {
            font-size: 14px;
            font-weight: 500;
            color: #111827;
            margin-bottom: 2px;
        }
        .learner-cell-email {
            font-size: 12px;
            color: #6b7280;
        }
        .learner-cell-link {
            font-size: 12px;
            color: #00b956;
            text-decoration: none;
            display: block;
            margin-top: 2px;
        }
        .learner-cell-link:hover { text-decoration: underline; }
    </style>

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
        <p class="muted small" style="margin:0.5rem 0 0">
            <strong>Баллы за модуль:</strong> взвешенное среднее процентов теста по теории, практики и итогового теста (веса {{ (int) (\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_THEORY_QUIZ * 100) }}/{{ (int) (\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_PRACTICE * 100) }}/{{ (int) (\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_EXAM * 100) }}), максимум {{ \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE }} за модуль; сумма по курсу — до {{ \App\Services\CourseScoringService::moduleCount() * \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE }}; финальная лаба — до {{ \App\Services\CourseScoringService::MAX_FINAL_LAB_POINTS }}. «Итого курс» — модули + финал (макс. {{ \App\Services\CourseScoringService::moduleCount() * \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE + \App\Services\CourseScoringService::MAX_FINAL_LAB_POINTS }}).
        </p>
    </div>

    <div class="card teacher-report-summary" style="margin-top:0">
        <h2 style="margin-top:0">Все обучающиеся</h2>
        <div class="tr-table-wrap">
            <table class="teacher-report-table">
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
                    <tr data-user-email="{{ e($row['email']) }}">
                        <td>
                            @if (! empty($row['full_name']))
                                <div class="learner-cell-name">{{ $row['full_name'] }}</div>
                                <div class="learner-cell-email">{{ $row['email'] }}</div>
                            @else
                                <div class="learner-cell-name">{{ $row['email'] }}</div>
                            @endif
                            <a class="learner-cell-link" href="{{ route('teacher.course-report.learner', $row['id']) }}">Карточка →</a>
                        </td>
                        <td>{{ $row['modules_passed_count'] }} / {{ \App\Services\CourseScoringService::moduleCount() }}</td>
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
@endsection
