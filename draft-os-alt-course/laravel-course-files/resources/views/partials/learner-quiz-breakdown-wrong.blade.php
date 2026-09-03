@php
    use App\Support\TeacherQuizLabels;
@endphp
@if (!empty($showBreakdown) && !empty($wrongItems))
    <div class="card" style="margin-top:1rem" data-breakdown-until="{{ $breakdownUntilTs ?? '' }}">
        <h2 style="margin-top:0">{{ $breakdownTitle ?? 'Разбор ответов' }}</h2>
        @if (!empty($breakdownUntilTs))
            <p class="muted small" id="quiz-breakdown-timer" data-until-ts="{{ (int) $breakdownUntilTs }}">
                Разбор доступен ограниченное время после попытки. Осталось: <strong class="quiz-breakdown-timer__left">—</strong>
            </p>
        @endif
        <p class="muted small">
            @if (($breakdownMode ?? 'all') === 'wrongs')
                Показаны только вопросы с ошибкой или без ответа. Отмечены ваш выбор и верный вариант.
            @else
                Все вопросы этой попытки. Отмечены ваш выбор и верный вариант.
            @endif
        </p>
        <ul class="muted learner-quiz-bd" style="padding-left:1.1rem;list-style:none;margin:0">
            @foreach ($wrongItems as $it)
                @php
                    $matchDrag = ! empty($it['match_drag']);
                    $opts = is_array($it['options'] ?? null) ? $it['options'] : [];
                    $exp = $it['expected'] ?? null;
                    $multi = ! empty($it['multi']);
                    $hasChosenKey = array_key_exists('chosen', $it);
                    $chosen = $hasChosenKey ? $it['chosen'] : null;
                    $skipped = ! empty($it['skipped']);
                    $isCorrect = ! $skipped && ! empty($it['correct']);
                    if ($skipped) {
                        $statusLabel = 'Без ответа';
                        $statusClass = 'learner-quiz-bd__status--skip';
                    } elseif ($isCorrect) {
                        $statusLabel = 'Верно';
                        $statusClass = 'learner-quiz-bd__status--ok';
                    } else {
                        $statusLabel = 'Ошибка';
                        $statusClass = 'learner-quiz-bd__status--bad';
                    }
                @endphp
                <li class="learner-quiz-bd__item">
                    <strong>Вопрос {{ $it['n'] ?? '' }}.</strong>
                    <div class="module-exam-q--md" style="font-weight:600;margin-top:0.2rem">{!! \Illuminate\Support\Str::markdown($it['question'] ?? '') !!}</div>
                    <p class="learner-quiz-bd__status {{ $statusClass }}">{{ $statusLabel }}</p>

                    @if ($matchDrag)
                        @php
                            $mLeft = is_array($it['left'] ?? null) ? $it['left'] : [];
                            $chOrd = is_array($it['chosen_order'] ?? null) ? $it['chosen_order'] : [];
                        @endphp
                        <div class="learner-quiz-bd__match muted small">
                            <div>Сопоставление — ваш порядок описаний (индексы сверху вниз):
                                <strong>{{ $chOrd !== [] ? implode(', ', array_map('intval', $chOrd)) : '—' }}</strong>
                            </div>
                            @if ($mLeft !== [])
                                <ol style="margin:0.35rem 0 0;padding-left:1.1rem">
                                    @foreach ($mLeft as $lbl)
                                        <li style="margin-bottom:0.15rem">{{ $lbl }}</li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    @elseif ($opts !== [])
                        <ul class="learner-quiz-bd__opts">
                            @foreach ($opts as $oi => $label)
                                @php
                                    $isExp = $multi
                                        ? (is_array($exp) && in_array((int) $oi, array_map('intval', $exp), true))
                                        : ((int) $oi === (int) $exp);
                                    $isCh = false;
                                    if ($hasChosenKey && $chosen !== null && $chosen !== '') {
                                        $isCh = $multi
                                            ? (is_array($chosen) && in_array((int) $oi, array_map('intval', $chosen), true))
                                            : ((int) $oi === (int) $chosen);
                                    }
                                    $liClass = 'learner-quiz-bd__opt';
                                    if ($isCh && ! $isExp) {
                                        $liClass .= ' learner-quiz-bd__opt--wrong';
                                    } elseif ($isExp) {
                                        $liClass .= ' learner-quiz-bd__opt--ok';
                                    }
                                @endphp
                                <li class="{{ $liClass }}">
                                    <strong>{{ TeacherQuizLabels::letter((int) $oi) }})</strong>
                                    @if ($isCh)<span class="learner-bd-tag learner-bd-tag--ch">ваш выбор</span>@endif
                                    @if ($isExp)<span class="learner-bd-tag learner-bd-tag--ok">верно</span>@endif
                                    <span class="learner-quiz-bd__opt-text">{!! \Illuminate\Support\Str::markdown((string) $label) !!}</span>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($hasChosenKey && ! $skipped)
                        <p class="muted small" style="margin:0.35rem 0 0">
                            Ваш выбор:
                            @if ($multi)
                                {{ is_array($chosen) && $chosen !== [] ? TeacherQuizLabels::lettersList($chosen) : '— (пусто)' }}
                            @else
                                {{ $chosen !== null && $chosen !== '' ? TeacherQuizLabels::lettersList((int) $chosen) : '—' }}
                            @endif
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
    <style>
        .learner-quiz-bd__item { margin-bottom: 1.1rem; padding-bottom: 0.85rem; border-bottom: 1px solid #e8edf2; }
        .learner-quiz-bd__item:last-child { border-bottom: 0; margin-bottom: 0; padding-bottom: 0; }
        .learner-quiz-bd__status { margin: 0.35rem 0 0.45rem; font-size: 0.9rem; font-weight: 600; }
        .learner-quiz-bd__status--ok { color: #0d5c2f; }
        .learner-quiz-bd__status--bad { color: #9b1c1c; }
        .learner-quiz-bd__status--skip { color: #5c6b76; }
        .learner-quiz-bd__opts { margin: 0.25rem 0 0; padding-left: 0; list-style: none; }
        .learner-quiz-bd__opt { margin-bottom: 0.28rem; padding: 0.28rem 0.45rem; border-radius: 6px; line-height: 1.35; }
        .learner-quiz-bd__opt--wrong { background: #fde8e6; }
        .learner-quiz-bd__opt--ok { background: #e8fdf6; }
        .learner-quiz-bd__opt-text p { margin: 0; display: inline; }
        .learner-bd-tag {
            font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
            margin-left: 0.2rem; margin-right: 0.25rem; padding: 0.06rem 0.28rem;
            border-radius: 4px; vertical-align: middle; white-space: nowrap;
        }
        .learner-bd-tag--ch { background: #eef2ff; color: #3b5bdb; }
        .learner-bd-tag--ok { background: #d9f5e4; color: #0d5c2f; }
    </style>
    @if (!empty($breakdownUntilTs))
        <script>
            (function () {
                var el = document.getElementById('quiz-breakdown-timer');
                if (!el) return;
                var until = parseInt(el.getAttribute('data-until-ts'), 10);
                var leftEl = el.querySelector('.quiz-breakdown-timer__left');
                if (!until || !leftEl) return;
                function tick() {
                    var sec = until - Math.floor(Date.now() / 1000);
                    if (sec <= 0) {
                        leftEl.textContent = '0:00';
                        window.location.reload();
                        return;
                    }
                    var m = Math.floor(sec / 60);
                    var s = sec % 60;
                    leftEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                }
                tick();
                setInterval(tick, 1000);
            })();
        </script>
    @endif
@endif
