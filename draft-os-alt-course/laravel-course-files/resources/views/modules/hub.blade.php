@extends('layouts.course')

@php
    $st = config('course.step_titles', []);
    $tTheory = $st['theory'] ?? 'Теория';
    $tTq = $st['theory_quiz'] ?? 'Тест по теории';
    $tPr = $st['practice'] ?? 'Практика';
    $tEx = $st['module_exam'] ?? 'Итоговый тест';
    $skipPractice = \App\Support\CourseModuleMeta::shouldSkipPractice((int) $module);
    $p = $progress;
    $tqLast = is_array($p->theory_quiz_last_result ?? null) ? $p->theory_quiz_last_result : [];
    $exLast = is_array($p->module_exam_last_result ?? null) ? $p->module_exam_last_result : [];
    $wTq = (int) round(\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_THEORY_QUIZ * 100);
    $wPr = (int) round(\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_PRACTICE * 100);
    $wEx = (int) round(\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_EXAM * 100);
    $th = (int) ($passThreshold ?? 70);
    $tqAtt = (int) ($p->theory_quiz_attempts ?? 0);
    $tqBest = (int) ($p->theory_quiz_best_score ?? 0);
    $exAtt = (int) ($p->module_exam_attempts ?? 0);
    $exBest = (int) ($p->module_exam_best_score ?? 0);
    $exMax = (int) ($examMaxAttemptsDisplay ?? \App\Services\CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS);
    $thEx = (int) ($passThresholdExam ?? $passThreshold ?? $th);

    $theoryBar = $p->theory_read_at ? 100 : 0;
    $tqBar = $tqAtt > 0 ? min(100, $tqBest) : 0;
    $prPct = $p->practice_lab_percent !== null ? (int) $p->practice_lab_percent : ($p->practice_done_at ? 100 : 0);
    $exBar = $exAtt > 0 ? min(100, $exBest) : 0;

    $theoryLine2 = $p->theory_read_at && $p->theory_read_at instanceof \DateTimeInterface
        ? $p->theory_read_at->format('d.m.Y H:i')
        : 'Откройте материал и отметьте просмотр';

    $tqParts = [];
    if ($tqAtt > 0 && isset($tqLast['correct_count'], $tqLast['total'])) {
        $tqParts[] = (int) $tqLast['correct_count'].'/'.(int) $tqLast['total'].' верных';
    }
    if ($tqAtt > 0) {
        $tqParts[] = 'попыток: '.$tqAtt;
    }
    if (! empty($tqLast['penalty_points'])) {
        $tqParts[] = 'штраф −'.(int) $tqLast['penalty_points'].' п.п.';
    }
    $tqLine2 = $tqAtt > 0 ? implode(' · ', $tqParts) : 'Порог зачёта '.$th.'% — после попытки здесь появится результат';

    $prLine2 = $p->practice_done_at
        ? (($p->practice_done_at instanceof \DateTimeInterface) ? 'Зачтено '.$p->practice_done_at->format('d.m.Y H:i') : 'Зачтено')
        : 'После автопроверки стенда — процент по чек-листу';

    $exParts = [];
    if ($exAtt > 0) {
        $exParts[] = 'попытка '.$exAtt.'/'.$exMax;
    }
    if ($exAtt > 0 && isset($exLast['correct_count'], $exLast['total'])) {
        $exParts[] = (int) $exLast['correct_count'].'/'.(int) $exLast['total'].' верных';
    }
    if ($exAtt > 0 && ! empty($exLast['earned_points']) && ! empty($exLast['max_points'])) {
        $exParts[] = (int) $exLast['earned_points'].'/'.(int) $exLast['max_points'].' баллов';
    }
    if ($exAtt > 0 && ! empty($exLast['penalty_applied'])) {
        $exParts[] = 'пересдача −'.(int) ($exLast['penalty_points'] ?? 10).' п.п.';
    }
    if ($exAtt > 0 && isset($exLast['raw_percent']) && (int) $exLast['raw_percent'] !== $exBest) {
        $exParts[] = 'сырой '.(int) $exLast['raw_percent'].'%';
    }
    $exLine2 = $exAtt > 0 ? implode(' · ', $exParts) : 'Порог '.$thEx.'% · до '.$exMax.' попыток';
@endphp

@section('title', 'Модуль '.$module.': '.$meta['title'])

@section('content')
    <div class="module-hub card" style="max-width: 920px; margin: 0 auto">
        <p class="muted module-hub__back"><a href="{{ route('dashboard') }}">← К модулям курса</a></p>
        <header class="module-hub__head">
            <p class="quiz-modal-badge module-hub__badge">Модуль {{ $meta['letter'] ?? $module }}</p>
            <h1 class="module-hub__h1">Модуль {{ $module }}: {{ $meta['title'] ?? 'Без названия' }}</h1>
            @if (! empty($meta['summary']))
                <p class="muted module-hub__lead">{{ $meta['summary'] }}</p>
            @endif
        </header>

        <section class="module-hub__summary" aria-label="Сводка по модулю">
            <div class="module-hub__stat-grid">
                <div class="module-hub__stat">
                    <span class="module-hub__stat-k">Этапы</span>
                    <span class="module-hub__stat-v">{{ (int) $percent }}<span class="module-hub__stat-u">%</span></span>
                </div>
                <div class="module-hub__stat module-hub__stat--accent">
                    <span class="module-hub__stat-k">Баллы</span>
                    <span class="module-hub__stat-v">{{ (int) ($modulePoints ?? 0) }}<span class="module-hub__stat-fr">/100</span></span>
                </div>
            </div>
            <div class="module-hub__sumbar" title="Доля завершённых этапов модуля">
                <span class="module-hub__sumbar-fill" style="width: {{ min(100, max(0, (int) $percent)) }}%"></span>
            </div>
            <p class="module-hub__legend muted small">
                @if ($skipPractice)
                    Баллы: без практики веса пересчитываются. Тесты — порог <strong>{{ $th }}%</strong>.
                @else
                    Веса в баллах: {{ $tTq }} {{ $wTq }}% · {{ $tPr }} {{ $wPr }}% · {{ $tEx }} {{ $wEx }}%. Порог тестов <strong>{{ $th }}%</strong>.
                @endif
            </p>
        </section>

        @if (! empty($showHubBriefing))
            <dialog class="quiz-modal" id="hub-briefing" open aria-labelledby="hub-briefing-title">
                <div class="quiz-modal-inner">
                    <h2 id="hub-briefing-title" class="quiz-modal-heading">Перед началом</h2>
                    <p class="muted small" style="margin-top: 0">Пройдите этапы по порядку: теория → тест → практика (если есть) → итоговый тест.</p>
                    <form method="post" action="{{ route('modules.hub.ack', $module) }}" class="quiz-modal-form">
                        @csrf
                        <button type="submit" class="btn btn-primary">Понятно, продолжить</button>
                    </form>
                </div>
            </dialog>
        @endif

        <style>
            .module-hub {
                --hub-line: #dfe8e4;
                --hub-soft: #eef4f1;
                --hub-ok: #1a9d6b;
                --hub-ok-bg: #e8f7f1;
                --hub-warn: #b45309;
                --hub-warn-bg: #fffbeb;
                --hub-muted: #5c6b76;
                --hub-text: #0f172a;
                --hub-fill: var(--accent, #0a7);
                --hub-fill-dim: #b8dcc8;
            }
            .module-hub__back { margin: 0 0 0.65rem; }
            .module-hub__h1 { margin: 0.35rem 0 0; font-size: clamp(1.2rem, 2.4vw, 1.5rem); line-height: 1.25; color: var(--hub-text); }
            .module-hub__badge { margin: 0; }
            .module-hub__lead { margin: 0.45rem 0 0; max-width: 50rem; line-height: 1.45; }
            .module-hub__summary {
                padding: 0.85rem 1rem;
                border-radius: 12px;
                background: linear-gradient(160deg, #f8fafc, #fff);
                border: 1px solid var(--hub-line);
                margin-bottom: 1rem;
            }
            .module-hub__stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 0.75rem; margin-bottom: 0.5rem; }
            .module-hub__stat { padding: 0.5rem 0.65rem; border-radius: 8px; background: #fff; border: 1px solid var(--hub-line); }
            .module-hub__stat--accent { border-color: #b8dcc8; background: linear-gradient(135deg, #ecf8f2, #fff); }
            .module-hub__stat-k { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--hub-muted); }
            .module-hub__stat-v { font-size: 1.45rem; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--hub-text); line-height: 1.1; margin-top: 0.1rem; }
            .module-hub__stat-u { font-size: 1rem; font-weight: 700; color: var(--hub-muted); }
            .module-hub__stat-fr { font-size: 0.85rem; font-weight: 600; color: var(--hub-muted); margin-left: 0.1rem; }
            .module-hub__sumbar { position: relative; height: 10px; border-radius: 999px; background: var(--hub-soft); border: 1px solid var(--hub-line); overflow: hidden; }
            .module-hub__sumbar-fill { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 999px; background: linear-gradient(90deg, var(--hub-fill), #3ecf8e); transition: width 0.25s ease; }
            .module-hub__legend { margin: 0.45rem 0 0; line-height: 1.4; }

            .module-hub__steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
            .hub-row {
                display: grid;
                /* Слева — вся ширина под полный заголовок; справа — узкая колонка только под шкалы и цифры */
                grid-template-columns: minmax(0, 1fr) minmax(200px, 17.5rem) 1.5rem;
                gap: 0.65rem 0.85rem;
                align-items: start;
                padding: 0.55rem 0.65rem 0.55rem 0.7rem;
                border-radius: 12px;
                border: 1px solid var(--hub-line);
                background: #fff;
                text-decoration: none;
                color: inherit;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
                transition: box-shadow 0.15s ease, border-color 0.15s ease;
            }
            .hub-row:hover { border-color: #cbd5e1; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06); }
            .hub-row:focus-visible { outline: 2px solid var(--hub-fill); outline-offset: 2px; }
            .hub-row--disabled {
                cursor: not-allowed;
                opacity: 0.58;
                background: #f8fafc;
                box-shadow: none;
                pointer-events: none;
            }
            .hub-row--disabled .hub-idx { color: #94a3b8; background: #f3f6f8; border-color: #dbe4ea; }
            .hub-row--disabled .hub-title { color: #64748b; }
            .hub-badge--na { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
            .hub-row__left {
                display: flex;
                align-items: flex-start;
                gap: 0.55rem;
                min-width: 0;
            }
            .hub-idx {
                flex-shrink: 0; width: 1.75rem; height: 1.75rem;
                display: flex; align-items: center; justify-content: center;
                border-radius: 8px; font-weight: 800; font-size: 0.82rem;
                color: var(--hub-ok); background: var(--hub-ok-bg); border: 1px solid #b8dcc8;
            }
            .hub-title-wrap { min-width: 0; flex: 1; }
            .hub-title {
                margin: 0;
                font-size: 0.95rem;
                font-weight: 700;
                line-height: 1.35;
                color: var(--hub-text);
                word-wrap: break-word;
                overflow-wrap: anywhere;
            }

            .hub-meta {
                display: flex;
                flex-direction: column;
                gap: 0.28rem;
                align-items: stretch;
                text-align: right;
                min-width: 0;
            }
            .hub-line1 {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 0.4rem;
                flex-wrap: wrap;
            }
            .hub-track {
                position: relative;
                width: 100%;
                max-width: 11rem;
                margin-left: auto;
                height: 8px;
                border-radius: 999px;
                background: var(--hub-soft);
                border: 1px solid var(--hub-line);
                overflow: hidden;
            }
            .hub-track__fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--hub-fill), #3ecf8e); transition: width 0.2s ease; }
            .hub-track__fill--muted { background: linear-gradient(90deg, #94a3b8, #cbd5e1); }
            .hub-track__tick { position: absolute; top: 0; bottom: 0; width: 2px; margin-left: -1px; background: #475569; opacity: 0.45; z-index: 1; pointer-events: none; }
            .hub-pct { font-size: 1rem; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--hub-text); flex-shrink: 0; min-width: 2.6rem; text-align: right; }
            .hub-pct--muted { font-size: 0.88rem; font-weight: 700; color: var(--hub-muted); min-width: auto; }
            .hub-line2 {
                font-size: 0.76rem;
                line-height: 1.35;
                color: var(--hub-muted);
                text-align: right;
                overflow: hidden;
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
            }
            .hub-badge {
                flex-shrink: 0; padding: 0.12rem 0.45rem; border-radius: 999px;
                font-size: 0.65rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase;
            }
            .hub-badge--ok { background: var(--hub-ok-bg); color: var(--hub-ok); border: 1px solid #b8dcc8; }
            .hub-badge--wait { background: var(--hub-soft); color: #475569; border: 1px solid #cbd5e1; }
            .hub-badge--no { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
            .hub-badge--warn { background: var(--hub-warn-bg); color: var(--hub-warn); border: 1px solid #fde68a; }
            .hub-row__go { font-size: 1.2rem; font-weight: 300; color: var(--hub-fill); text-align: center; line-height: 1; align-self: center; padding-top: 0.15rem; }

            @media (max-width: 640px) {
                .hub-row {
                    grid-template-columns: 1fr 1.5rem;
                    grid-template-rows: auto auto;
                }
                .hub-meta {
                    grid-column: 1 / -1;
                    max-width: none;
                    text-align: left;
                }
                .hub-line1 { justify-content: flex-start; }
                .hub-track { margin-left: 0; max-width: none; }
                .hub-line2 { text-align: left; }
                .hub-row__go { grid-row: 1; grid-column: 2; align-self: start; padding-top: 0.15rem; }
            }

            .module-hub__diff { margin-top: 1.1rem; padding-top: 0.9rem; border-top: 1px solid var(--hub-line); }
            .module-hub__diff-h { margin: 0 0 0.4rem; font-size: 0.95rem; font-weight: 700; }
            .module-hub__diff-grid { display: flex; flex-wrap: wrap; gap: 0.35rem; }
            .module-hub__diff-grid label {
                display: inline-flex; align-items: center; gap: 0.4rem;
                padding: 0.28rem 0.5rem; border-radius: 8px; border: 1px solid var(--hub-line);
                background: var(--hub-soft); font-size: 0.8rem; cursor: pointer;
            }
        </style>

        <nav aria-label="Этапы модуля" class="card-inner" style="padding: 0">
            <ul class="module-hub__steps">
                @if (! empty($hubPresent))
                    @foreach ($hubPresent as $hp)
                        @include('modules.partials.hub-step-row', [
                            'module' => $module,
                            'section' => $hp['section'],
                            'waived' => $hp['waived'],
                            'accessible' => $hp['accessible'],
                            'idx' => $loop->iteration,
                            'title' => $hp['section']->title,
                            'p' => $p,
                            'th' => $th,
                            'thEx' => $thEx,
                            'exMax' => $exMax,
                        ])
                    @endforeach
                @else
                <li>
                    <a class="hub-row" href="{{ route('modules.theory', $module) }}">
                        <div class="hub-row__left">
                            <span class="hub-idx" aria-hidden="true">1</span>
                            <span class="hub-title-wrap"><span class="hub-title">{{ $tTheory }}</span></span>
                        </div>
                        <div class="hub-meta">
                            <div class="hub-line1">
                                <div class="hub-track" title="Этап: просмотр теории">
                                    <div class="hub-track__fill{{ $theoryBar >= 100 ? '' : ' hub-track__fill--muted' }}" style="width: {{ (int) $theoryBar }}%"></div>
                                </div>
                                <span class="hub-pct hub-pct--muted">{{ $p->theory_read_at ? '100' : '0' }}%</span>
                                @if ($p->theory_read_at)
                                    <span class="hub-badge hub-badge--ok">Готово</span>
                                @else
                                    <span class="hub-badge hub-badge--wait">Дальше</span>
                                @endif
                            </div>
                            <p class="hub-line2">{{ $theoryLine2 }}</p>
                        </div>
                        <span class="hub-row__go" aria-hidden="true">›</span>
                    </a>
                </li>

                <li>
                    <a class="hub-row" href="{{ route('modules.theory-quiz', $module) }}">
                        <div class="hub-row__left">
                            <span class="hub-idx" aria-hidden="true">2</span>
                            <span class="hub-title-wrap"><span class="hub-title">{{ $tTq }}</span></span>
                        </div>
                        <div class="hub-meta">
                            <div class="hub-line1">
                                <div class="hub-track" title="Лучший результат, порог {{ $th }}%">
                                    <span class="hub-track__tick" style="left: {{ $th }}%"></span>
                                    <div class="hub-track__fill{{ $tqBar >= $th ? '' : ' hub-track__fill--muted' }}" style="width: {{ (int) $tqBar }}%"></div>
                                </div>
                                <span class="hub-pct">{{ $tqAtt > 0 ? $tqBest : '—' }}@if($tqAtt > 0)%@endif</span>
                                @if ($p->theory_quiz_passed)
                                    <span class="hub-badge hub-badge--ok">Зачтён</span>
                                @elseif ($tqAtt > 0)
                                    <span class="hub-badge hub-badge--no">Ниже {{ $th }}%</span>
                                @else
                                    <span class="hub-badge hub-badge--wait">Тест</span>
                                @endif
                            </div>
                            <p class="hub-line2">{{ $tqLine2 }}</p>
                        </div>
                        <span class="hub-row__go" aria-hidden="true">›</span>
                    </a>
                </li>

                @if ($skipPractice)
                    <li>
                        <div class="hub-row hub-row--disabled" role="group" aria-label="{{ $tPr }}: не предусмотрена">
                            <div class="hub-row__left">
                                <span class="hub-idx" aria-hidden="true">3</span>
                                <span class="hub-title-wrap"><span class="hub-title">{{ $tPr }}</span></span>
                            </div>
                            <div class="hub-meta">
                                <div class="hub-line1">
                                    <div class="hub-track" title="Нет этапа">
                                        <div class="hub-track__fill hub-track__fill--muted" style="width: 0%"></div>
                                    </div>
                                    <span class="hub-pct hub-pct--muted">—</span>
                                    <span class="hub-badge hub-badge--na">Нет</span>
                                </div>
                                <p class="hub-line2 muted" style="margin:0">Практика в этом модуле не входит в курс. К итоговому тесту можно перейти после теста по теории. Баллы модуля — только теория-тест и экзамен.</p>
                            </div>
                            <span class="hub-row__go hub-pct--muted" aria-hidden="true">·</span>
                        </div>
                    </li>
                @else
                    <li>
                        <a class="hub-row" href="{{ route('modules.practice', $module) }}">
                            <div class="hub-row__left">
                                <span class="hub-idx" aria-hidden="true">3</span>
                                <span class="hub-title-wrap"><span class="hub-title">{{ $tPr }}</span></span>
                            </div>
                            <div class="hub-meta">
                                <div class="hub-line1">
                                    <div class="hub-track" title="Автопроверка стенда">
                                        <div class="hub-track__fill{{ $prPct >= 100 ? '' : ($prPct > 0 ? '' : ' hub-track__fill--muted') }}" style="width: {{ (int) min(100, $prPct) }}%"></div>
                                    </div>
                                    <span class="hub-pct">{{ $p->practice_lab_percent !== null ? (int) $p->practice_lab_percent.'%' : ($p->practice_done_at ? '100%' : '—') }}</span>
                                    @if ($p->practice_done_at)
                                        <span class="hub-badge hub-badge--ok">Готово</span>
                                    @else
                                        <span class="hub-badge hub-badge--wait">Стенд</span>
                                    @endif
                                </div>
                                <p class="hub-line2">{{ $prLine2 }}</p>
                            </div>
                            <span class="hub-row__go" aria-hidden="true">›</span>
                        </a>
                    </li>
                @endif

                <li>
                    <a class="hub-row" href="{{ route('modules.exam', $module) }}">
                        <div class="hub-row__left">
                            <span class="hub-idx" aria-hidden="true">{{ $skipPractice ? 3 : 4 }}</span>
                            <span class="hub-title-wrap"><span class="hub-title">{{ $tEx }}</span></span>
                        </div>
                        <div class="hub-meta">
                            <div class="hub-line1">
                                <div class="hub-track" title="Итог последней попытки, порог {{ $thEx }}%">
                                    <span class="hub-track__tick" style="left: {{ $thEx }}%"></span>
                                    <div class="hub-track__fill{{ $exBar >= $thEx ? '' : ' hub-track__fill--muted' }}" style="width: {{ (int) $exBar }}%"></div>
                                </div>
                                <span class="hub-pct">{{ $exAtt > 0 ? $exBest : '—' }}@if($exAtt > 0)%@endif</span>
                                @if ($p->module_exam_passed)
                                    <span class="hub-badge hub-badge--ok">Зачтён</span>
                                @elseif ($exAtt > 0)
                                    <span class="hub-badge hub-badge--warn">Ещё раз</span>
                                @else
                                    <span class="hub-badge hub-badge--wait">Экзамен</span>
                                @endif
                            </div>
                            <p class="hub-line2">{{ $exLine2 }}</p>
                        </div>
                        <span class="hub-row__go" aria-hidden="true">›</span>
                    </a>
                </li>
                @endif
            </ul>
        </nav>

        <section class="module-hub__diff card-inner" style="padding-left: 0; padding-right: 0">
            <h2 class="module-hub__diff-h">Сложности по этапам</h2>
            <form method="post" action="{{ route('modules.difficulties', $module) }}">
                @csrf
                <div class="module-hub__diff-grid">
                    <label><input type="checkbox" name="d_theory" value="1" @checked(old('d_theory', (bool) data_get($p->difficulty_flags, 'theory')))> {{ $tTheory }}</label>
                    <label><input type="checkbox" name="d_theory_quiz" value="1" @checked(old('d_theory_quiz', (bool) data_get($p->difficulty_flags, 'theory_quiz')))> {{ $tTq }}</label>
                    @if (! $skipPractice)
                        <label><input type="checkbox" name="d_practice" value="1" @checked(old('d_practice', (bool) data_get($p->difficulty_flags, 'practice')))> {{ $tPr }}</label>
                    @endif
                    <label><input type="checkbox" name="d_module_exam" value="1" @checked(old('d_module_exam', (bool) data_get($p->difficulty_flags, 'module_exam')))> {{ $tEx }}</label>
                </div>
                <div style="margin-top: 0.65rem">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </section>
    </div>
@endsection
