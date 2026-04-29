@extends('layouts.course')

@php
    use App\Support\DurationFormat;
    $keyQ = request()->filled('key') ? '?key=' . urlencode((string) request('key')) : '';
    $p = $panel['progress'];
    $rep = $panel['report'];
    $mid = (int) $panel['module_id'];
    $ps = $panel['practice_session'];
    $secTheory = $p ? (int) ($p->seconds_theory ?? 0) : 0;
    $secTq = $p ? (int) ($p->seconds_theory_quiz ?? 0) : 0;
    $secPr = $p ? (int) ($p->seconds_practice ?? 0) : 0;
    $secEx = $p ? (int) ($p->seconds_module_exam ?? 0) : 0;
    $secMod = $secTheory + $secTq + $secPr + $secEx;
    $skipPractice = ! empty(config('course.modules.'.$mid.'.skip_practice'));
@endphp

@section('title', 'Модуль '.$mid.' — '.$panel['title'])

@section('content')
    <nav class="ta-breadcrumb" aria-label="Навигация">
        <a href="{{ route('teacher.course-report').$keyQ }}">Все обучающиеся</a>
        <span class="ta-bc-sep">/</span>
        <a href="{{ route('teacher.course-report.learner', $learner->id).$keyQ }}">{{ $learner->email }}</a>
        <span class="ta-bc-sep">/</span>
        <span class="ta-bc-current">Модуль {{ $mid }}</span>
        @if ($keyQ !== '')
            <span class="ta-bc-sep" aria-hidden="true">·</span>
            <a href="{{ route('admin.theory.index', ['key' => request('key')]) }}">Теория модулей</a>
        @endif
    </nav>

    <header class="ta-mod-head card">
        <p class="ta-kicker">Модуль {{ $mid }} @if($panel['letter'] !== '')<span class="muted">· {{ $panel['letter'] }}</span>@endif</p>
        <h1 class="ta-mod-title">{{ $panel['title'] }}</h1>
        @if (is_array($rep))
            <div class="ta-scoreboard" aria-label="Сводные результаты по модулю">
                <div class="ta-ring ta-ring--accent" title="Итоговый балл за модуль">
                    <span class="ta-ring__value">{{ (int) ($rep['points'] ?? 0) }}</span>
                    <span class="ta-ring__label">балл</span>
                </div>
                <div class="ta-ring" title="Тест по теории">
                    <span class="ta-ring__value">{{ (int) ($rep['theory_quiz_pct'] ?? 0) }}%</span>
                    <span class="ta-ring__label">теория</span>
                </div>
                <div class="ta-ring{{ $skipPractice ? ' ta-ring--mute' : '' }}" title="{{ $skipPractice ? 'Практика не предусмотрена' : 'Практика' }}">
                    <span class="ta-ring__value">@if ($skipPractice){{ '—' }}@else{{ (int) ($rep['practice_pct'] ?? 0) }}%@endif</span>
                    <span class="ta-ring__label">практика</span>
                </div>
                <div class="ta-ring" title="Экзамен">
                    <span class="ta-ring__value">{{ (int) ($rep['exam_pct'] ?? 0) }}%</span>
                    <span class="ta-ring__label">экзамен</span>
                </div>
            </div>
            <div class="ta-stat-row ta-stat-row--time" aria-label="Время по разделам модуля">
                <span class="ta-chip"><span class="ta-chip__k">Теория</span><span class="ta-chip__v">{{ DurationFormat::fromSeconds($secTheory) }}</span></span>
                <span class="ta-chip"><span class="ta-chip__k">Тест</span><span class="ta-chip__v">{{ DurationFormat::fromSeconds($secTq) }}</span></span>
                <span class="ta-chip"><span class="ta-chip__k">Практика</span><span class="ta-chip__v">{{ DurationFormat::fromSeconds($secPr) }}</span></span>
                <span class="ta-chip"><span class="ta-chip__k">Экзамен</span><span class="ta-chip__v">{{ DurationFormat::fromSeconds($secEx) }}</span></span>
                <span class="ta-chip ta-chip--sum"><span class="ta-chip__k">Σ модуля</span><span class="ta-chip__v">{{ DurationFormat::fromSeconds($secMod) }}</span></span>
            </div>
        @endif
    </header>

    <nav class="ta-quicknav" id="ta-jump" aria-label="Быстрый переход по шагам модуля">
        <span class="ta-quicknav__title">К шагу</span>
        <a class="ta-quicknav__a" href="#ta-theory">Теория</a>
        <a class="ta-quicknav__a" href="#ta-tq">Тест по теории</a>
        @if (! $skipPractice)
            <a class="ta-quicknav__a" href="#ta-pr">Практика</a>
        @endif
        <a class="ta-quicknav__a" href="#ta-ex">Экзамен</a>
        @if ($p && is_array($panel['instructor_resets']) && count($panel['instructor_resets']) > 0)
            <a class="ta-quicknav__a ta-quicknav__a--muted" href="#ta-audit">Журнал сбросов</a>
        @endif
    </nav>

    <style>
        .ta-breadcrumb{font-size:0.9rem;margin:0 0 1rem;color:var(--muted,#5c6b76);display:flex;flex-wrap:wrap;gap:0.35rem;align-items:center}
        .ta-breadcrumb a{color:var(--accent,#0a7);text-decoration:none;font-weight:600}
        .ta-breadcrumb a:hover{text-decoration:underline}
        .ta-bc-sep{opacity:0.45}
        .ta-bc-current{font-weight:600;color:var(--text,#0f172a)}
        .ta-kicker{font-size:0.78rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--muted,#5c6b76);margin:0}
        .ta-mod-head{margin-bottom:0.75rem;padding-bottom:1rem}
        .ta-mod-title{margin:0.2rem 0 0.85rem;font-size:1.35rem;line-height:1.25}
        .ta-scoreboard{display:flex;flex-wrap:wrap;gap:0.65rem 1rem;align-items:flex-end;margin:0.25rem 0 0.85rem}
        .ta-ring{
            width:4.35rem;height:4.35rem;border-radius:50%;
            display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;
            border:2px solid #d4e4df;background:linear-gradient(160deg,#fff,#f4faf7);
            box-shadow:0 2px 8px rgba(15,42,35,.06);
        }
        .ta-ring--accent{border-color:#1a9d6b;background:linear-gradient(165deg,#e8f7f1,#fff)}
        .ta-ring__value{font-weight:800;font-size:0.95rem;line-height:1.1;color:#0f172a}
        .ta-ring__label{font-size:0.62rem;text-transform:uppercase;letter-spacing:0.04em;color:#5c6b76;margin-top:0.12rem;max-width:3.6rem;line-height:1.05}
        .ta-stat-row{display:flex;flex-wrap:wrap;gap:0.45rem;align-items:center}
        .ta-stat-row--time{margin-top:0.15rem}
        .ta-chip{display:inline-flex;align-items:center;gap:0.4rem;padding:0.28rem 0.65rem;border-radius:999px;background:#eef4f1;border:1px solid #d8e6df;font-size:0.82rem}
        .ta-chip--sum{background:#e8f4ef;border-color:#b9d9c9;font-weight:600}
        .ta-chip__k{color:#5c6b76;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.04em}
        .ta-chip__v{font-weight:700;color:#0f172a}
        .ta-quicknav{
            position:sticky;top:0;z-index:20;scroll-margin-top:0.5rem;
            display:flex;flex-wrap:wrap;align-items:center;gap:0.35rem 0.5rem;
            padding:0.55rem 0.75rem;margin:0 0 1rem;
            background:rgba(255,255,255,.92);backdrop-filter:blur(8px);
            border:1px solid var(--line,#dfe8e4);border-radius:12px;
            box-shadow:0 4px 18px rgba(15,23,42,.06);
        }
        .ta-quicknav__title{font-size:0.72rem;text-transform:uppercase;letter-spacing:0.08em;color:#6b7c76;margin-right:0.35rem;font-weight:700}
        .ta-quicknav__a{
            padding:0.32rem 0.75rem;border-radius:999px;font-size:0.84rem;font-weight:600;text-decoration:none;
            color:#0f5c3e;background:#e6f3ec;border:1px solid #b5dcc8;
        }
        .ta-quicknav__a:hover{background:#d4ebdf}
        .ta-quicknav__a--muted{color:#4a5560;background:#eef1f5;border-color:#d5dbe4}
        .ta-stack{display:flex;flex-direction:column;gap:1.15rem}
        .ta-step{
            scroll-margin-top:5.5rem;
            border:1px solid var(--line,#e1e8ea);border-radius:16px;padding:1.05rem 1.15rem 1.15rem;
            background:var(--card,#fff);box-shadow:0 2px 12px rgba(15,23,42,.04)
        }
        .ta-step--muted{background:#f4f6f8;box-shadow:none;border-color:#e2e8f0}
        .ta-ring--mute{opacity:0.55;border-color:#dbe0e6!important}
        .ta-step__head{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:0.5rem;margin-bottom:0.5rem}
        .ta-step h2{margin:0;font-size:1.08rem}
        .ta-step-meta{font-size:0.86rem;color:var(--muted,#5c6b76);margin:0 0 0.75rem;line-height:1.45}
        .ta-attempt-stack{display:flex;flex-direction:column;gap:0.75rem}
        .ta-attempt-card{
            border:1px solid #e0ebe6;border-radius:14px;padding:0.85rem 1rem;
            background:linear-gradient(180deg,#fbfcfc,#f5f9f7);
        }
        .ta-attempt-card__top{display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem 0.75rem;margin-bottom:0.45rem}
        .ta-attempt-card h3{margin:0;font-size:0.98rem}
        .ta-mini-ring{
            min-width:3.25rem;height:3.25rem;border-radius:50%;
            display:inline-flex;flex-direction:column;align-items:center;justify-content:center;
            border:2px solid #c5ddd2;background:#fff;font-size:0.72rem;font-weight:800;color:#0f172a
        }
        .ta-mini-ring span{font-size:0.58rem;font-weight:600;color:#5c6b76;text-transform:uppercase;margin-top:0.08rem}
        .ta-meta-inline{font-size:0.82rem;color:#5c6b76}
        .ta-tag-ok{display:inline-block;padding:0.12rem 0.45rem;border-radius:6px;background:#d9f5e4;color:#0d5c2f;font-size:0.72rem;font-weight:700}
        .ta-pr-metrics{display:flex;flex-wrap:wrap;gap:0.5rem;margin:0.5rem 0 0.65rem}
        .ta-pr-metric{
            min-width:5.5rem;padding:0.5rem 0.65rem;border-radius:12px;border:1px solid #dde8e3;background:#fff;
            display:flex;flex-direction:column;gap:0.15rem
        }
        .ta-pr-metric__k{font-size:0.68rem;text-transform:uppercase;letter-spacing:0.05em;color:#6b7c76}
        .ta-pr-metric__v{font-weight:700;font-size:0.95rem;color:#0f172a}
        .ta-reset{border-top:1px dashed var(--line,#dde5ea);margin-top:0.9rem;padding-top:0.9rem}
        .ta-reset label{display:flex;gap:0.45rem;align-items:flex-start;font-size:0.88rem;margin:0.35rem 0}
        .ta-reset input[type="text"]{width:100%;max-width:28rem;padding:0.45rem 0.55rem;border-radius:8px;border:1px solid var(--line,#dde5ea);font:inherit}
        .ta-reset .btn-danger{margin-top:0.5rem;background:#c0392b;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;cursor:pointer;font-weight:600}
        .ta-archives{margin-top:0.25rem}
        .ta-archives h2{margin:0 0 0.5rem}
        .ta-arch{border:1px solid var(--line,#e1e8ea);border-radius:12px;padding:0.65rem 0.85rem;margin-bottom:0.5rem;background:#fcfdfe}
        .ta-arch summary{cursor:pointer;font-weight:600}
        .ta-pre{font-size:0.75rem;max-height:16rem;overflow:auto;padding:0.55rem 0.65rem;background:#f1f4f7;border-radius:8px;border:1px solid var(--line,#e1e8ea);white-space:pre-wrap;word-break:break-word;margin:0.5rem 0 0}
    </style>

    <div class="ta-stack">
        {{-- Теория --}}
        <section class="ta-step" id="ta-theory" aria-labelledby="ta-theory-h">
            <div class="ta-step__head">
                <h2 id="ta-theory-h">Теория</h2>
                <a class="ta-quicknav__a" style="padding:0.22rem 0.55rem;font-size:0.78rem" href="#ta-jump">меню</a>
            </div>
            <p class="ta-step-meta">Просмотр материала и учёт времени. Сброс не предусмотрен — обучающийся может открыть раздел снова сам.</p>
            @if ($p)
                <div class="ta-stat-row" style="margin-bottom:0.65rem">
                    <span class="ta-chip" title="Когда отмечена прочтённой">
                        <span class="ta-chip__k">Прочтено</span>
                        <span class="ta-chip__v">{{ $p->theory_read_at ? $p->theory_read_at->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—' }}</span>
                    </span>
                    <span class="ta-chip ta-chip--sum" title="Время на шаге «теория»">
                        <span class="ta-chip__k">Время</span>
                        <span class="ta-chip__v">{{ DurationFormat::fromSeconds($secTheory) }}</span>
                    </span>
                </div>
            @else
                <p class="muted">Нет записи прогресса.</p>
            @endif
        </section>

        {{-- Тест по теории --}}
        <section class="ta-step" id="ta-tq" aria-labelledby="ta-tq-h">
            <div class="ta-step__head">
                <h2 id="ta-tq-h">Тест по теории</h2>
                <a class="ta-quicknav__a" style="padding:0.22rem 0.55rem;font-size:0.78rem" href="#ta-jump">меню</a>
            </div>
            <p class="ta-step-meta">Каждая попытка — отдельная карточка; проценты выделены для наглядности.</p>
            @php
                $thHist = $panel['theory_quiz_history'] ?? [];
                if ($p && (! is_array($thHist) || count($thHist) === 0) && is_array($p->theory_quiz_last_result) && count($p->theory_quiz_last_result) > 0) {
                    $thHist = [$p->theory_quiz_last_result];
                }
            @endphp
            @if ($p && is_array($thHist) && count($thHist) > 0)
                <div class="ta-attempt-stack">
                    @foreach ($thHist as $ti => $tattempt)
                        <article class="ta-attempt-card">
                            <div class="ta-attempt-card__top">
                                <h3>Попытка {{ $tattempt['attempt_no'] ?? ($ti + 1) }}</h3>
                                <div class="ta-mini-ring" title="Итоговый процент">{{ (int) ($tattempt['final_percent'] ?? 0) }}%<span>итог</span></div>
                                @if (!empty($tattempt['raw_percent']))
                                    <div class="ta-mini-ring" style="border-color:#cfd8e6" title="Сырой процент">{{ (int) $tattempt['raw_percent'] }}%<span>сырой</span></div>
                                @endif
                                @if (!empty($tattempt['passed']))
                                    <span class="ta-tag-ok">порог</span>
                                @endif
                            </div>
                            <p class="ta-meta-inline" style="margin:0 0 0.5rem">
                                @if (!empty($tattempt['recorded_at']))Запись: {{ $tattempt['recorded_at'] }}@endif
                                @if(!empty($tattempt['penalty_points']))<span> · штраф −{{ $tattempt['penalty_points'] }} п.п.</span>@endif
                            </p>
                            @include('partials.teacher-quiz-breakdown-items', [
                                'items' => $tattempt['items'] ?? [],
                                'questionBank' => $panel['theory_questions'],
                            ])
                        </article>
                    @endforeach
                </div>
            @else
                <p class="muted">Пока нет завершённых попыток.</p>
            @endif
            @php
                $tqHist = $panel['theory_quiz_history'] ?? [];
                $canResetTq = $p && (
                    (int) $p->theory_quiz_attempts >= 1
                    || is_array($p->theory_quiz_last_result)
                    || (is_array($tqHist) && count($tqHist) > 0)
                );
            @endphp
            @if ($canResetTq)
                <div class="ta-reset">
                    <form method="post" action="{{ route('teacher.course-report.learner.module.reset', ['learner' => $learner->id, 'module' => $mid]).$keyQ }}" class="js-ta-reset-form">
                        @csrf
                        <input type="hidden" name="step" value="theory_quiz">
                        <label><input type="checkbox" name="confirm" value="1" required> Сбросить последнюю «занятую» попытку: у обучающегося счётчик попыток уменьшится на 1, текущие результаты и история на его стороне очистятся; здесь останется запись в журнале сбросов.</label>
                        <input type="text" name="note" maxlength="500" placeholder="Комментарий к сбросу (необязательно)">
                        <button type="submit" class="btn-danger">Сбросить тест по теории</button>
                    </form>
                </div>
            @endif
        </section>

        {{-- Практика --}}
        <section class="ta-step{{ $skipPractice ? ' ta-step--muted' : '' }}" id="ta-pr" aria-labelledby="ta-pr-h">
            <div class="ta-step__head">
                <h2 id="ta-pr-h">Практика</h2>
                @if (! $skipPractice)
                    <a class="ta-quicknav__a" style="padding:0.22rem 0.55rem;font-size:0.78rem" href="#ta-jump">меню</a>
                @endif
            </div>
            @if ($skipPractice)
                <p class="muted" style="margin:0">В учебном плане этого модуля практическое занятие <strong>не предусмотрено</strong>. Итоговый балл за модуль считается только из теста по теории и итогового экзамена (веса пересчитаны).</p>
            @else
                @if ($mid === 1 && $p && \Illuminate\Support\Facades\Schema::hasColumn('module_progress', 'practice_m1_quest') && is_array($p->practice_m1_quest) && count($p->practice_m1_quest) > 0)
                    <p class="ta-step-meta">Архив старого веб-квеста (JSON в БД, до перехода на Docker).</p>
                    <pre class="ta-pre" style="max-height:16rem">{{ json_encode($p->practice_m1_quest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
                <p class="ta-step-meta">@if ($mid === 1)Практика: Docker-стенд. @endif Лабораторный стенд и зачёт.</p>
                <div class="ta-stat-row" style="margin-bottom:0.65rem">
                    <span class="ta-chip ta-chip--sum"><span class="ta-chip__k">Время на практике</span><span class="ta-chip__v">{{ DurationFormat::fromSeconds($secPr) }}</span></span>
                </div>
                @if ($ps)
                    <div class="ta-attempt-stack">
                        <article class="ta-attempt-card">
                            <div class="ta-attempt-card__top">
                                <h3>Текущая сессия</h3>
                                <span class="ta-chip"><span class="ta-chip__k">Статус</span><span class="ta-chip__v">{{ $ps->status }}</span></span>
                            </div>
                            <div class="ta-pr-metrics">
                                <div class="ta-pr-metric">
                                    <span class="ta-pr-metric__k">Баллы проверки</span>
                                    <span class="ta-pr-metric__v">{{ $ps->last_check_score ?? '—' }} / {{ $ps->last_check_max_score ?? '—' }}</span>
                                </div>
                                <div class="ta-pr-metric">
                                    <span class="ta-pr-metric__k">Проверка</span>
                                    <span class="ta-pr-metric__v">{{ $ps->last_check_at ? $ps->last_check_at->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—' }}</span>
                                </div>
                                <div class="ta-pr-metric">
                                    <span class="ta-pr-metric__k">Принято</span>
                                    <span class="ta-pr-metric__v">{{ $ps->accepted_at ? $ps->accepted_at->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—' }}</span>
                                </div>
                                <div class="ta-pr-metric">
                                    <span class="ta-pr-metric__k">Зачёт проверки</span>
                                    <span class="ta-pr-metric__v">{{ $ps->last_check_passed ? 'да' : 'нет' }}</span>
                                </div>
                            </div>
                            @if ($ps->last_check_log)
                                <p class="muted small" style="margin:0.5rem 0 0.15rem">Журнал проверки:</p>
                                <pre class="ta-pre">{{ \Illuminate\Support\Str::limit((string) $ps->last_check_log, 6000) }}</pre>
                            @endif
                        </article>
                    </div>
                @else
                    <p class="muted">Сессии практики нет.</p>
                @endif
                @php
                    $canResetPr = $p && ($p->practice_done_at || $ps);
                @endphp
                @if ($canResetPr)
                    <div class="ta-reset">
                        <form method="post" action="{{ route('teacher.course-report.learner.module.reset', ['learner' => $learner->id, 'module' => $mid]).$keyQ }}" class="js-ta-reset-form">
                            @csrf
                            <input type="hidden" name="step" value="practice">
                            <label><input type="checkbox" name="confirm" value="1" required> Сбросить практику: снимок сессии уйдёт в журнал, отметка «сдано» и проценты у обучающегося сбросятся; контейнер нужно будет запустить заново.</label>
                            <input type="text" name="note" maxlength="500" placeholder="Комментарий к сбросу (необязательно)">
                            <button type="submit" class="btn-danger">Сбросить практику</button>
                        </form>
                    </div>
                @endif
            @endif
        </section>

        {{-- Экзамен --}}
        <section class="ta-step" id="ta-ex" aria-labelledby="ta-ex-h">
            <div class="ta-step__head">
                <h2 id="ta-ex-h">Итоговый тест модуля</h2>
                <a class="ta-quicknav__a" style="padding:0.22rem 0.55rem;font-size:0.78rem" href="#ta-jump">меню</a>
            </div>
            <p class="ta-step-meta">Все зафиксированные попытки — списком сверху вниз.</p>
            <div class="ta-stat-row" style="margin-bottom:0.65rem">
                <span class="ta-chip ta-chip--sum"><span class="ta-chip__k">Время на экзамене</span><span class="ta-chip__v">{{ DurationFormat::fromSeconds($secEx) }}</span></span>
            </div>
            @php
                $exHist = $panel['module_exam_history'] ?? [];
                if ($p && (! is_array($exHist) || count($exHist) === 0) && is_array($p->module_exam_last_result) && count($p->module_exam_last_result) > 0) {
                    $exHist = [$p->module_exam_last_result];
                }
            @endphp
            @if ($p && is_array($exHist) && count($exHist) > 0)
                <div class="ta-attempt-stack">
                    @foreach ($exHist as $ei => $eattempt)
                        <article class="ta-attempt-card">
                            <div class="ta-attempt-card__top">
                                <h3>Попытка {{ $eattempt['attempt'] ?? ($ei + 1) }}</h3>
                                <div class="ta-mini-ring" title="Итог">{{ (int) ($eattempt['final_percent'] ?? 0) }}%<span>итог</span></div>
                                @if (isset($eattempt['raw_percent']))
                                    <div class="ta-mini-ring" style="border-color:#cfd8e6" title="Сырой">{{ (int) $eattempt['raw_percent'] }}%<span>сырой</span></div>
                                @endif
                                @if (!empty($eattempt['passed_this_attempt']))
                                    <span class="ta-tag-ok">порог</span>
                                @endif
                            </div>
                            <p class="ta-meta-inline" style="margin:0 0 0.5rem">
                                @if (!empty($eattempt['recorded_at']))Запись: {{ $eattempt['recorded_at'] }}@endif
                                @if(!empty($eattempt['penalty_applied']))<span> · штраф −{{ $eattempt['penalty_points'] ?? 10 }} п.п.</span>@endif
                            </p>
                            @include('partials.teacher-quiz-breakdown-items', [
                                'items' => $eattempt['items'] ?? [],
                                'questionBank' => $panel['exam_questions'],
                            ])
                        </article>
                    @endforeach
                </div>
            @else
                <p class="muted">Пока нет завершённых попыток.</p>
            @endif
            @php
                $exHistR = $panel['module_exam_history'] ?? [];
                $canResetEx = $p && (
                    (int) $p->module_exam_attempts >= 1
                    || is_array($p->module_exam_last_result)
                    || (is_array($exHistR) && count($exHistR) > 0)
                );
            @endphp
            @if ($canResetEx)
                <div class="ta-reset">
                    <form method="post" action="{{ route('teacher.course-report.learner.module.reset', ['learner' => $learner->id, 'module' => $mid]).$keyQ }}" class="js-ta-reset-form">
                        @csrf
                        <input type="hidden" name="step" value="module_exam">
                        <label><input type="checkbox" name="confirm" value="1" required> Сбросить последнюю попытку экзамена: счётчик попыток −1, видимые результаты очищаются, снимок — в журнале.</label>
                        <input type="text" name="note" maxlength="500" placeholder="Комментарий к сбросу (необязательно)">
                        <button type="submit" class="btn-danger">Сбросить экзамен</button>
                    </form>
                </div>
            @endif
        </section>

        @if ($p && is_array($panel['instructor_resets']) && count($panel['instructor_resets']) > 0)
            <section class="ta-step ta-archives" id="ta-audit" aria-labelledby="ta-audit-h">
                <div class="ta-step__head">
                    <h2 id="ta-audit-h">Журнал сбросов (аудит)</h2>
                    <a class="ta-quicknav__a ta-quicknav__a--muted" style="padding:0.22rem 0.55rem;font-size:0.78rem" href="#ta-jump">меню</a>
                </div>
                <p class="ta-step-meta" style="margin-top:0">Каждая запись — состояние <strong>до</strong> сброса. Данные не удаляются при последующих действиях обучающегося.</p>
                @foreach (array_reverse($panel['instructor_resets'], true) as $idx => $r)
                    <details class="ta-arch">
                        <summary>
                            {{ $r['at'] ?? '—' }}
                            · <strong>{{ $r['step'] ?? '?' }}</strong>
                            @if (!empty($r['note']))<span class="muted"> — {{ $r['note'] }}</span>@endif
                        </summary>
                        <pre class="ta-pre">{{ json_encode($r['snapshot'] ?? $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @endforeach
            </section>
        @endif
    </div>

    <p class="muted" style="margin-top:1.25rem">
        <a href="{{ route('teacher.course-report.learner', $learner->id).$keyQ }}">← Ко всем модулям обучающегося</a>
    </p>

    <script>
        document.querySelectorAll('.js-ta-reset-form').forEach(function (f) {
            f.addEventListener('submit', function (e) {
                if (!confirm('Выполнить сброс для этого шага?')) e.preventDefault();
            });
        });
    </script>
@endsection
