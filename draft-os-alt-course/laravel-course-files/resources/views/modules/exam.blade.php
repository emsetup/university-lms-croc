@extends('layouts.course')

@php
    $total = count($questions);
    $modNum = (int) ($moduleSequence ?? $module);
    $lr = \App\Support\LearnerRoute::hub((int) ($courseId ?? session('course_id')), $modNum);
    $sr = isset($sectionSequence)
        ? \App\Support\LearnerRoute::section((int) ($courseId ?? session('course_id')), $modNum, (int) $sectionSequence)
        : $lr;
    $sectionTitle = isset($section) && (string) $section->title !== ''
        ? (string) $section->title
        : config('course.step_titles.module_exam');
    $quizSt = $quizState ?? [];
    $examAttempts = (int) ($quizSt['attempts'] ?? ($progress->module_exam_attempts ?? 0));
    $showScorePercents = $showScorePercents ?? true;
    $showScorePoints = $showScorePoints ?? true;
@endphp

@section('title', 'Модуль '.$modNum.': '.$sectionTitle)

@section('content')
    <div class="page-container">
    <a class="back-link" href="{{ route('course.module.hub', $lr) }}">
        @include('partials.ap-icon', ['name' => 'arrow-left'])
        <span>К шагам модуля</span>
    </a>

    @if (! $examActive && empty($previewWalkthrough))
        @if ($needsRetakeAck)
            <dialog class="quiz-modal" id="module-exam-retake-warn" open aria-labelledby="module-exam-retake-title">
                <div class="quiz-modal-inner">
                    <p class="quiz-modal-badge">Модуль {{ $modNum }}@if (! empty($meta['letter'])) · {{ $meta['letter'] }}@endif</p>
                    <h2 id="module-exam-retake-title" class="quiz-modal-heading">Пересдача / вторая попытка</h2>
                    <ul class="quiz-modal-list">
                        <li class="quiz-modal-warn"><strong>Сохранённый результат будет заменён.</strong> После отправки новой попытки на странице «Результат» и в кратком разборе останутся <strong>только данные этой попытки</strong>. То, что вы видели после прошлой отправки (проценты, разбор по вопросам), <strong>больше не отображается</strong> — учтите это, если нужно что-то переписать из разбора заранее.</li>
                        <li>В зачёт модуля и в блок «итог» на странице модуля идёт <strong>результат последней завершённой попытки</strong> (пересдача заменяет предыдущий процент, без сохранения «лучшего из двух»).</li>
                        <li><strong>Штраф −{{ $retakePenalty }} п.п.</strong> применяется к <strong>сырому</strong> проценту правильных ответов этой попытки@if ($showScorePercents) (до сравнения с порогом {{ $passThreshold }}%). Например, сырой 80% даёт итоговый для зачёта <strong>70%</strong> с учётом штрафа@endif.</li>
                        <li><strong>Зачёт по модулю</strong>, если вы его уже получили, система <strong>не снимает</strong>. Но если новая попытка хуже порога, на странице вы увидите «ниже порога» для этой отправки — при этом ранее полученный зачёт модуля сохраняется.</li>
                    </ul>
                    <label class="choice exam-retake-ack-label" style="margin:0 0 1rem;display:flex;align-items:flex-start;gap:0.5rem;cursor:pointer">
                        <input type="checkbox" id="module-exam-retake-ack" value="1" autocomplete="off" style="margin-top:0.2rem">
                        <span>Я прочитал(а) и понимаю: предыдущий сохранённый результат на странице будет <strong>заменён</strong>, к сырому проценту этой попытки применится штраф <strong>−{{ $retakePenalty }} п.п.</strong>, я осознанно продолжаю.</span>
                    </label>
                    <div class="quiz-modal-actions">
                        <button type="button" class="btn btn-primary" id="module-exam-retake-next" disabled>Далее — условия теста и запуск таймера</button>
                        <a class="quiz-modal-cancel" href="{{ route('course.module.hub', $lr) }}">Отмена, вернуться к модулю</a>
                    </div>
                </div>
            </dialog>
        @endif

        <dialog class="quiz-modal" id="module-exam-intro" aria-labelledby="module-exam-intro-title" @if (! $needsRetakeAck) open @endif>
            <div class="quiz-modal-inner">
                <p class="quiz-modal-badge">Модуль {{ $meta['letter'] }}</p>
                <h2 id="module-exam-intro-title" class="quiz-modal-heading">{{ $sectionTitle }}</h2>
                <ul class="quiz-modal-list">
                    @if ($timeLimitMinutes)
                        <li>На прохождение отводится <strong>{{ $timeLimitMinutes }} минут</strong> — отсчёт начнётся только после нажатия «Запустить отсчёт».</li>
                        <li>Таймер показывается над вопросами; по истечении времени ответы отправятся автоматически (незаполненные вопросы засчитываются как ошибки).</li>
                    @else
                        <li>Ограничения по времени <strong>нет</strong> — после старта можете проходить тест в своём темпе.</li>
                    @endif
                    <li>@if ($showScorePercents)Порог зачёта: <strong>{{ $passThreshold }}%</strong>. @endifПопытка <strong>{{ $attemptNumber }}</strong> из {{ $maxAttempts }}.</li>
                    <li>Вопросы идут по шагам; у части из них несколько верных ответов — на кнопке с номером значок <strong>+</strong> (отметьте все подходящие). У вопросов на сопоставление — значок <strong>↕</strong>: перетащите блоки справа мышью в нужный порядок.</li>
                    @if ($timeLimitMinutes)
                        <li>Время фиксируется на сервере: после старта обновление страницы или краткий обрыв связи <strong>не обнуляют</strong> дедлайн (ответы в форме при полном обновлении страницы сбросятся — лучше не закрывайте вкладку зря).</li>
                    @endif
                    @if ($attemptNumber >= 2 && $showScorePercents)
                        <li class="quiz-modal-warn">Напоминание: к <strong>сырому</strong> проценту этой попытки уже применяется штраф <strong>−{{ $retakePenalty }} п.п.</strong> (см. предыдущее окно).</li>
                    @elseif ($attemptNumber >= 2)
                        <li class="quiz-modal-warn">Напоминание: к этой попытке применяется штраф за пересдачу (см. предыдущее окно).</li>
                    @endif
                </ul>
                <div class="quiz-modal-actions">
                    <form method="post" action="{{ route('course.module.section.exam.start', $sr) }}" class="quiz-modal-form">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ $timeLimitMinutes ? 'Запустить отсчёт' : 'Начать тестирование' }}</button>
                    </form>
                    <a class="quiz-modal-cancel" href="{{ route('course.module.hub', $lr) }}">Вернуться к модулю без старта</a>
                </div>
            </div>
        </dialog>

        <div class="card theory-quiz-idle-card">
            <h1 class="theory-quiz-page-title">Модуль {{ $modNum }}: {{ config('course.step_titles.module_exam') }}</h1>
            <p class="muted" style="margin:0">
                @if ($needsRetakeAck)
                    Сначала подтвердите условия пересдачи в первом окне, затем — общие условия теста и запуск таймера.
                @else
                    Ознакомьтесь с условиями в окне выше и нажмите «Запустить отсчёт», когда будете готовы.
                @endif
            </p>
        </div>

        @if ($needsRetakeAck)
            <script>
                (function () {
                    var retakeDlg = document.getElementById('module-exam-retake-warn');
                    var introDlg = document.getElementById('module-exam-intro');
                    var ack = document.getElementById('module-exam-retake-ack');
                    var nextBtn = document.getElementById('module-exam-retake-next');
                    if (!retakeDlg || !introDlg || !ack || !nextBtn) return;
                    ack.addEventListener('change', function () {
                        nextBtn.disabled = !ack.checked;
                    });
                    nextBtn.addEventListener('click', function () {
                        if (!ack.checked) return;
                        retakeDlg.close();
                        if (typeof introDlg.showModal === 'function') {
                            introDlg.showModal();
                        }
                    });
                })();
            </script>
        @endif
    @else
        @if (! empty($previewWalkthrough))
            <div class="impersonation-banner impersonation-banner--course-preview" role="status" style="margin-bottom:1rem;border-radius:8px">
                <span class="impersonation-banner__text">
                    <strong>Режим просмотра экзамена.</strong>
                    <span class="muted">Можно пройти по всем вопросам и посмотреть типы заданий; ответы и попытки не сохраняются.</span>
                </span>
            </div>
        @endif
        <div class="card module-exam-card content-protect" data-integrity-protect>
            <h1 style="margin-top:0">Модуль {{ $modNum }}: {{ config('course.step_titles.module_exam') }}</h1>
            @if ($modNum === 3 && ($showScorePoints || $showScorePercents))
                <div class="module-exam-m3-rubric muted" style="margin:0 0 1rem;line-height:1.5">
                    <p style="margin:0 0 0.35rem"><strong>Экзамен: Модуль 3 — ЦУС, Alterator и модули администрирования</strong></p>
                    @if ($showScorePoints)
                    <p style="margin:0">20 вопросов · 4 типа · максимум <strong>100</strong> баллов (в зачёт идёт процент от суммы баллов).</p>
                    <ul style="margin:0.5rem 0 0;padding-left:1.2rem;font-size:0.95rem">
                        <li>Часть 1 — один верный ответ (8 вопросов по 3 балла = 24)</li>
                        <li>Часть 2 — несколько верных, зачёт только при полном совпадении (5×4 = 20)</li>
                        <li>Часть 3 — сопоставление и порядок шагов в форме вопросов с выбором (4×4 = 16)</li>
                        <li>Часть 4 — практические ситуации (12 + 14 + 14 = 40 баллов)</li>
                    </ul>
                    @endif
                    @if ($showScorePercents)
                    <p style="margin:0.5rem 0 0">Порог зачёта: <strong>{{ $passThreshold }}%</strong>@if ($showScorePoints) от максимума баллов этой попытки@endif.</p>
                    @endif
                </div>
            @elseif ($modNum === 4 && ($showScorePoints || $showScorePercents))
                <div class="module-exam-m4-rubric muted" style="margin:0 0 1rem;line-height:1.5">
                    <p style="margin:0 0 0.35rem"><strong>Экзамен: Модуль 4 — Установка ОС «Альт», инсталлятор и профили</strong></p>
                    @if ($showScorePoints)
                    <p style="margin:0">20 вопросов · 4 типа · <strong>100</strong> баллов@if ($showScorePercents) · порог сдачи: <strong>{{ $passThreshold }}</strong> баллов (процент от суммы набранных баллов)@endif.</p>
                    <ul style="margin:0.5rem 0 0;padding-left:1.2rem;font-size:0.95rem">
                        <li>Часть 1 — один правильный ответ (7 вопросов по 4 балла = 28)</li>
                        <li>Часть 2 — несколько правильных ответов (6 вопросов по 5 баллов = 30)</li>
                        <li>Часть 3 — сопоставление (3 вопроса по 4 балла = 12)</li>
                        <li>Часть 4 — сценарии (4 вопроса: 8 + 7 + 8 + 7 = 30 баллов)</li>
                    </ul>
                    @elseif ($showScorePercents)
                    <p style="margin:0">Порог сдачи: <strong>{{ $passThreshold }}%</strong>.</p>
                    @endif
                    <p style="margin:0.5rem 0 0">@if ($timeLimitMinutes)На прохождение отводится <strong>{{ $timeLimitMinutes }} минут</strong> (таймер после «Запустить отсчёт»).@else Без ограничения по времени.@endif</p>
                </div>
            @elseif ($modNum === 5 && ($showScorePoints || $showScorePercents))
                <div class="module-exam-m5-rubric muted" style="margin:0 0 1rem;line-height:1.5">
                    <p style="margin:0 0 0.35rem"><strong>Экзамен: Модуль 5 — Сеть: три менеджера и контексты</strong></p>
                    @if ($showScorePoints)
                    <p style="margin:0">20 вопросов · 4 типа · <strong>100</strong> баллов. Сопоставление (вопросы 14–16) оформлено как «отметьте все верные пары».</p>
                    <ul style="margin:0.5rem 0 0;padding-left:1.2rem;font-size:0.95rem">
                        <li>Один верный ответ — 7×4 = 28</li>
                        <li>Несколько верных — 6×5 = 30 (зачёт только при полном совпадении)</li>
                        <li>Сопоставление — 3×4 = 12</li>
                        <li>Сценарии — 8 + 7 + 8 + 7 = 30</li>
                    </ul>
                    @endif
                    <p style="margin:0.5rem 0 0">
                        @if ($showScorePercents)Порог зачёта: <strong>{{ $passThreshold }}%</strong>@if ($showScorePoints) от суммы баллов@endif. @endif
                        @if ($timeLimitMinutes)Таймер: <strong>{{ $timeLimitMinutes }} мин.</strong>@else Без ограничения по времени.@endif
                    </p>
                </div>
            @endif
            <p class="muted small content-protect-hint">Текст вопросов нельзя копировать; при уходе с вкладки он скрывается. Снимок экрана ОС не блокируется браузером.</p>
            @if (empty($previewWalkthrough))
            <p class="muted">
                @if ($showScorePercents)Порог зачёта: <strong>{{ $passThreshold }}%</strong>. @endif
                Попытка <strong>{{ $attemptNumber }}</strong> из {{ $maxAttempts }}.
                @if ($timeLimitMinutes)
                    Осталось времени на попытку — на полосе ниже; по истечении ответы уйдут автоматически.
                @else
                    Ограничения по времени нет — отправьте ответы, когда будете готовы.
                @endif
                @if ($attemptNumber >= 2 && $showScorePercents)
                    <span class="module-exam-warn">К результату этой попытки применяется штраф <strong>−{{ $retakePenalty }} п.п.</strong> от сырого процента.</span>
                @elseif ($attemptNumber >= 2)
                    <span class="module-exam-warn">К результату этой попытки применяется штраф за пересдачу.</span>
                @endif
            </p>
            @else
            <p class="muted">
                @if ($showScorePercents)Порог зачёта у обучающихся: <strong>{{ $passThreshold }}%</strong>. @endif
                @if ($timeLimitMinutes)Лимит времени: <strong>{{ $timeLimitMinutes }} мин.</strong>@else Без ограничения по времени.@endif
            </p>
            @endif

            @if ($expiresAtMs)
                <div class="quiz-timer-bar" id="module-exam-timer-bar" role="status" aria-live="polite">
                    <span class="quiz-timer-label">Осталось времени</span>
                    <span class="quiz-timer-value" id="module-exam-timer-display">--:--</span>
                </div>
            @endif

            <div class="module-exam-progress" role="tablist" aria-label="Навигация по вопросам">
                @foreach ($questions as $i => $q)
                    @php
                        $matchDrag = ! empty($q['match_drag']);
                        $isMulti = is_array($q['c'] ?? null) && ! $matchDrag;
                    @endphp
                    <button type="button" class="module-exam-progress__btn" data-go="{{ $i }}" id="exam-step-tab-{{ $i }}" aria-label="Вопрос {{ $i + 1 }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
                        <span class="module-exam-progress__num">{{ $i + 1 }}</span>
                        @if ($isMulti)
                            <span class="module-exam-progress__badge" title="Несколько верных ответов">+</span>
                        @elseif ($matchDrag)
                            <span class="module-exam-progress__badge" title="Сопоставление перетаскиванием">↕</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <div class="module-exam-progress-bar" aria-hidden="true">
                <div class="module-exam-progress-bar__fill" id="module-exam-progress-fill" style="width: {{ $total > 0 ? round(100 / $total) : 0 }}%"></div>
            </div>

            @if (! empty($previewWalkthrough))
            <div id="module-exam-form">
            @else
            <form method="post" action="{{ route('course.module.section.exam.submit', $sr) }}" id="module-exam-form" novalidate>
                @csrf
            @endif
                @foreach ($questions as $i => $q)
                    @php
                        $matchDrag = ! empty($q['match_drag']);
                        $isMulti = is_array($q['c'] ?? null) && ! $matchDrag;
                    @endphp
                    <div class="module-exam-step" data-step="{{ $i }}" @if ($i !== 0) hidden @endif role="tabpanel" aria-labelledby="exam-step-tab-{{ $i }}">
                        <div class="module-exam-step__meta muted">Вопрос {{ $i + 1 }} из {{ $total }}@if ($showScorePoints && !empty($questions[$i]['points'])) · {{ (int) $questions[$i]['points'] }} б.@endif</div>
                        @php
                            $examQHtml = \App\Support\CourseContentMarkdown::toHtml(trim((string) ($q['q'] ?? '')));
                            $examQHtml = (string) preg_replace('/<p>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/iu', '', $examQHtml);
                        @endphp
                        <div class="module-exam-q module-exam-q--md">{!! $examQHtml !!}</div>
                        @if ($matchDrag)
                            @php
                                $mLeft = $q['left'] ?? [];
                                $mRight = $q['right'] ?? [];
                                $mN = count($mLeft);
                                $mPerm = range(0, max(0, $mN - 1));
                                shuffle($mPerm);
                            @endphp
                            <p class="muted module-exam-hint">Расставьте блоки <strong>справа</strong> напротив строк слева (сверху вниз) — перетаскиванием или кнопками ↑ / ↓.</p>
                            <div class="module-exam-match">
                                <div class="module-exam-match__left" aria-label="Фиксированные подписи">
                                    @foreach ($mLeft as $li => $label)
                                        <div class="module-exam-match__row">
                                            <span class="module-exam-match__ln">{{ $li + 1 }}</span>
                                            <div class="module-exam-match__cell">{!! \App\Support\CourseContentMarkdown::inlineHtml($label) !!}</div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="module-exam-match__right">
                                    <div class="muted small" style="margin:0 0 0.35rem">Варианты (переставьте в нужный порядок):</div>
                                    <ul class="module-exam-match__list js-match-drag-list" id="match-drag-{{ $i }}" data-q="{{ $i }}">
                                        @foreach ($mPerm as $descIdx)
                                            <li draggable="true" class="module-exam-match__card" data-desc-idx="{{ (int) $descIdx }}">
                                                <span class="module-exam-match__card-text">{!! \App\Support\CourseContentMarkdown::inlineHtml($mRight[$descIdx] ?? '') !!}</span>
                                                <span class="module-exam-match__card-ops">
                                                    <button type="button" class="module-exam-match__move" data-match-move="up" title="Выше" aria-label="Переместить выше">↑</button>
                                                    <button type="button" class="module-exam-match__move" data-match-move="down" title="Ниже" aria-label="Переместить ниже">↓</button>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <input type="hidden" name="e{{ $i }}_order" class="js-match-order js-exam-input" data-q="{{ $i }}" value="{{ implode(',', $mPerm) }}" autocomplete="off">
                                </div>
                            </div>
                        @elseif ($isMulti)
                            @include('modules.partials.quiz-multi-hint')
                            @foreach ($q['a'] as $j => $opt)
                                <label class="choice module-exam-choice">
                                    <input type="checkbox" name="e{{ $i }}[]" value="{{ $j }}" class="js-exam-input" data-q="{{ $i }}">
                                    <span>{!! \App\Support\CourseContentMarkdown::inlineHtml($opt) !!}</span>
                                </label>
                            @endforeach
                        @else
                            @foreach ($q['a'] as $j => $opt)
                                <label class="choice module-exam-choice">
                                    <input type="radio" name="e{{ $i }}" value="{{ $j }}" class="js-exam-input" data-q="{{ $i }}">
                                    <span>{!! \App\Support\CourseContentMarkdown::inlineHtml($opt) !!}</span>
                                </label>
                            @endforeach
                        @endif
                    </div>
                @endforeach

                <div class="module-exam-nav">
                    <button type="button" class="btn btn-ghost" id="module-exam-prev" disabled>Назад</button>
                    <button type="button" class="btn btn-primary" id="module-exam-next">Далее</button>
                    @if (! empty($previewWalkthrough))
                        <a class="btn btn-primary" id="module-exam-finish" href="{{ route('course.module.hub', $lr) }}" hidden>К шагам модуля</a>
                    @else
                        <button type="submit" class="btn btn-primary" id="module-exam-finish" hidden>Завершить и отправить</button>
                    @endif
                </div>
            @if (! empty($previewWalkthrough))
            </div>
            @else
            </form>
            @endif
        </div>
        @include('partials.assessment-integrity')

        <script>
            (function () {
                var total = {{ (int) $total }};
                var form = document.getElementById('module-exam-form');
                if (!form || total < 1) return;

                var steps = form.querySelectorAll('.module-exam-step');
                var tabs = document.querySelectorAll('.module-exam-progress__btn');
                var btnPrev = document.getElementById('module-exam-prev');
                var btnNext = document.getElementById('module-exam-next');
                var btnFinish = document.getElementById('module-exam-finish');
                var fill = document.getElementById('module-exam-progress-fill');
                var cur = 0;

                function answered(idx) {
                    var step = steps[idx];
                    if (!step) return false;
                    var ord = step.querySelector('.js-match-order');
                    if (ord && ord.value && ord.value.length) return true;
                    var cbs = step.querySelectorAll('input[type="checkbox"]:checked');
                    if (cbs.length) return true;
                    var r = step.querySelector('input[type="radio"]:checked');
                    return !!r;
                }

                function syncTabStates() {
                    tabs.forEach(function (t, i) {
                        var ok = answered(i);
                        t.classList.toggle('is-done', ok);
                        t.classList.toggle('is-current', i === cur);
                        t.setAttribute('aria-current', i === cur ? 'step' : 'false');
                        t.setAttribute('aria-selected', i === cur ? 'true' : 'false');
                    });
                    if (fill) {
                        fill.style.width = (100 * (cur + 1) / total) + '%';
                    }
                }

                function showStep(idx) {
                    if (idx < 0 || idx >= total) return;
                    cur = idx;
                    steps.forEach(function (el, i) {
                        el.hidden = i !== cur;
                    });
                    btnPrev.disabled = cur === 0;
                    if (cur === total - 1) {
                        btnNext.hidden = true;
                        btnFinish.hidden = false;
                    } else {
                        btnNext.hidden = false;
                        btnFinish.hidden = true;
                    }
                    syncTabStates();
                }

                tabs.forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        var i = parseInt(tab.getAttribute('data-go'), 10);
                        if (!isNaN(i)) showStep(i);
                    });
                });

                btnPrev.addEventListener('click', function () { showStep(cur - 1); });
                btnNext.addEventListener('click', function () { showStep(cur + 1); });

                form.querySelectorAll('.js-exam-input').forEach(function (inp) {
                    inp.addEventListener('change', syncTabStates);
                });

                @if (empty($previewWalkthrough))
                form.addEventListener('submit', function (e) {
                    var miss = [];
                    for (var i = 0; i < total; i++) {
                        if (!answered(i)) miss.push(i + 1);
                    }
                    if (miss.length) {
                        e.preventDefault();
                        alert('Ответьте на вопросы: ' + miss.join(', ') + '. Можно перейти к ним по номерам сверху.');
                    }
                });
                @endif

                showStep(0);

                @if ($expiresAtMs)
                (function () {
                    var end = {{ (int) $expiresAtMs }};
                    var el = document.getElementById('module-exam-timer-display');
                    var bar = document.getElementById('module-exam-timer-bar');
                    var sent = false;
                    function pad(n) { return n < 10 ? '0' + n : '' + n; }
                    function tick() {
                        var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
                        var m = Math.floor(left / 60), s = left % 60;
                        if (el) el.textContent = pad(m) + ':' + pad(s);
                        if (left <= 60 && bar) bar.classList.add('quiz-timer-urgent');
                        if (left <= 0 && form && !sent) {
                            sent = true;
                            form.submit();
                        }
                    }
                    tick();
                    setInterval(tick, 250);
                })();
                @endif
            })();
        </script>
        @include('partials.match-reorder-script')
    @endif
    </div>
@endsection
