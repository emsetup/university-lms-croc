@extends('layouts.admin')

@section('title', 'Участники — '.$section->title)

@section('content')
    @php
        $tp = ['adminCourse' => $course->slug];
        $rp = array_merge($tp, ['courseModule' => $module->id, 'section' => $section->id]);
        $audience = $payload['audience'] ?? 'all';
        $anonymous = !empty($payload['anonymous']);
        $counters = $payload['counters'] ?? [];
        $rows = $payload['rows'] ?? [];
        $typeLabels = [
            'survey' => 'Опрос',
            'quiz' => 'Тест',
            'exam' => 'Экзамен',
            'text' => 'Теория',
            'practice' => 'Практика',
        ];
        $typeLabel = $typeLabels[$section->type] ?? $section->type;
    @endphp
    <div class="admin-content ap-section-participants">
        <p><a href="{{ route('admin.course.settings', $tp) }}#ap-mod-{{ $module->id }}">← К модулю</a></p>
        <h1 class="ap-page-title">Участники: {{ $section->title }}</h1>
        <p class="ap-muted">
            {{ $typeLabel }} · модуль {{ $module->title }}
            · {{ $audience === 'restricted' ? 'ограниченный доступ' : 'открыт для всех' }}
            @if ($anonymous)
                · анонимный режим
            @endif
        </p>

        <div class="ap-section-participants__counters">
            @if ($audience === 'restricted')
                <div class="ap-section-participants__counter">
                    <span class="ap-section-participants__counter-n">{{ (int) ($counters['eligible'] ?? 0) }}</span>
                    <span class="ap-section-participants__counter-l">Доступно</span>
                </div>
                <div class="ap-section-participants__counter">
                    <span class="ap-section-participants__counter-n">{{ (int) ($counters['completed'] ?? 0) }}</span>
                    <span class="ap-section-participants__counter-l">Прошли</span>
                </div>
                <div class="ap-section-participants__counter">
                    <span class="ap-section-participants__counter-n">{{ (int) ($counters['pending'] ?? 0) }}</span>
                    <span class="ap-section-participants__counter-l">Не прошли</span>
                </div>
                @if (($counters['attempted'] ?? null) !== null)
                    <div class="ap-section-participants__counter">
                        <span class="ap-section-participants__counter-n">{{ (int) $counters['attempted'] }}</span>
                        <span class="ap-section-participants__counter-l">С попытками</span>
                    </div>
                @endif
            @else
                <div class="ap-section-participants__counter">
                    <span class="ap-section-participants__counter-n">{{ (int) ($counters['completed'] ?? 0) }}</span>
                    <span class="ap-section-participants__counter-l">Прошли</span>
                </div>
            @endif
        </div>

        <p class="ap-section-participants__actions">
            @if ($section->type === 'survey')
                <a class="btn btn-ghost" href="{{ route('admin.course.module.section.survey-responses', $rp) }}">Сводная таблица / CSV</a>
            @endif
            <a class="btn btn-ghost" href="{{ route('admin.learners.course', $tp) }}">Обучающиеся курса</a>
        </p>

        <div class="ap-section-participants__layout">
            <div class="ap-section-participants__list-wrap">
                @if (count($rows) === 0)
                    <p class="ap-muted">
                        @if ($audience === 'restricted')
                            Нет допущенных участников. Назначьте доступ во вкладке «Доступ» раздела.
                        @else
                            Пока никто не прошёл этот раздел.
                        @endif
                    </p>
                @else
                    <div class="ap-table-wrap" style="overflow:auto">
                        <table class="ap-table ap-section-participants__table">
                            <thead>
                                <tr>
                                    <th>Участник</th>
                                    <th>Статус</th>
                                    <th>Дата</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php
                                        $lid = $row['learner_id'] ?? null;
                                        $isSelected = $lid && (int) $lid === (int) ($selectedLearnerId ?? 0);
                                        $statusClass = match ($row['status'] ?? '') {
                                            'completed' => 'is-done',
                                            'attempted' => 'is-attempted',
                                            default => 'is-pending',
                                        };
                                    @endphp
                                    <tr class="{{ $isSelected ? 'is-selected' : '' }} {{ !empty($row['can_open_detail']) && $lid ? 'is-clickable' : '' }}"
                                        @if (!empty($row['can_open_detail']) && $lid)
                                            data-learner-id="{{ $lid }}"
                                        @endif
                                    >
                                        <td>
                                            <div class="ap-section-participants__name">{{ $row['display_name'] ?? '—' }}</div>
                                            @if (!empty($row['email']))
                                                <div class="ap-muted small">{{ $row['email'] }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="ap-section-participants__status {{ $statusClass }}">{{ $row['status_label'] ?? '' }}</span>
                                            @if (!empty($row['meta']))
                                                <div class="ap-muted small">{{ $row['meta'] }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $row['completed_at'] ?? '—' }}</td>
                                        <td>
                                            @if (!empty($row['can_open_detail']) && $lid)
                                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.course.module.section.participants', array_merge($rp, ['learner' => $lid])) }}">Открыть</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <aside class="ap-section-participants__detail" id="ap-sp-detail" @if (!$detail) hidden @endif>
                @if ($detail)
                    @include('admin.partials.section-participant-detail', ['detail' => $detail])
                @endif
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var detailUrlTpl = @json(route('admin.course.module.section.participants.detail', array_merge($rp, ['learner' => 0])));
    var detailBox = document.getElementById('ap-sp-detail');
    if (!detailBox) return;

    function detailUrl(id) {
        return detailUrlTpl.replace(/\/0(\/?$)/, '/' + id);
    }

    document.querySelectorAll('.ap-section-participants__table tr[data-learner-id]').forEach(function (tr) {
        tr.addEventListener('click', function (e) {
            if (e.target.closest('a')) return;
            var id = tr.getAttribute('data-learner-id');
            if (!id) return;
            document.querySelectorAll('.ap-section-participants__table tr.is-selected').forEach(function (r) {
                r.classList.remove('is-selected');
            });
            tr.classList.add('is-selected');
            detailBox.hidden = false;
            detailBox.innerHTML = '<p class="ap-muted">Загрузка…</p>';
            fetch(detailUrl(id), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || !d.ok) {
                        detailBox.innerHTML = '<p class="ap-muted">' + (d && d.message ? d.message : 'Не удалось загрузить') + '</p>';
                        return;
                    }
                    detailBox.innerHTML = renderDetail(d);
                })
                .catch(function () {
                    detailBox.innerHTML = '<p class="ap-muted">Ошибка загрузки</p>';
                });
        });
    });

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderDetail(d) {
        var html = '<div class="ap-sp-detail-card">';
        if (d.learner) {
            html += '<h2 class="ap-sp-detail-card__title">' + esc(d.learner.display_name) + '</h2>';
            if (d.learner.email) html += '<p class="ap-muted small">' + esc(d.learner.email) + '</p>';
        } else {
            html += '<h2 class="ap-sp-detail-card__title">Результат</h2>';
        }
        html += '<p><span class="ap-section-participants__status">' + esc(d.status_label || '') + '</span>';
        if (d.completed_at) html += ' · ' + esc(d.completed_at);
        html += '</p>';

        if (d.survey) {
            if (d.survey.anonymous) {
                html += '<p class="ap-muted">Анонимный опрос — текст ответов скрыт.</p>';
            } else if (!d.survey.submitted) {
                html += '<p class="ap-muted">Ещё не отправил ответы.</p>';
            } else if (!d.survey.items || !d.survey.items.length) {
                html += '<p class="ap-muted">Ответов нет.</p>';
            } else {
                html += '<ul class="ap-report-survey-answers">';
                d.survey.items.forEach(function (it) {
                    html += '<li class="ap-report-survey-answers__item">';
                    html += '<p class="ap-report-survey-answers__q">' + esc(it.question || '') + '</p>';
                    html += '<p class="ap-report-survey-answers__a">' + esc(it.answer || '—') + '</p>';
                    html += '</li>';
                });
                html += '</ul>';
            }
        } else if (d.quiz) {
            html += '<p class="ap-muted small">Лучший результат: ' + esc(d.quiz.best_score) + '% · попыток: ' + esc(d.quiz.attempts) + '</p>';
            if (d.quiz.items && d.quiz.items.length) {
                html += '<ul class="ap-report-survey-answers">';
                d.quiz.items.forEach(function (it, i) {
                    var q = it.question_text || it.question || ('Вопрос ' + (i + 1));
                    var a = it.display || it.answer || it.chosen_label || '—';
                    html += '<li class="ap-report-survey-answers__item">';
                    html += '<p class="ap-report-survey-answers__q">' + esc(q) + '</p>';
                    html += '<p class="ap-report-survey-answers__a">' + esc(a) + '</p>';
                    html += '</li>';
                });
                html += '</ul>';
            }
        } else if (d.simple) {
            html += '<p>' + esc(d.simple.message || '') + '</p>';
        }
        html += '</div>';
        return html;
    }
})();
</script>
@endpush
