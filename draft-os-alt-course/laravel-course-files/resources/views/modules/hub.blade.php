@extends('layouts.course')

@php
    $st = config('course.step_titles', []);
    $tTheory = $st['theory'] ?? 'Теория';
    $tTq = $st['theory_quiz'] ?? 'Тест по теории';
    $tPr = $st['practice'] ?? 'Практика';
    $tEx = $st['module_exam'] ?? 'Итоговый тест';
    $modNum = (int) ($moduleSequence ?? $module);
    $mid = (int) ($moduleDbId ?? $modNum);
    $lr = \App\Support\LearnerRoute::hub((int) ($courseId ?? session('course_id')), $modNum);
    $skipPractice = ! empty($hubPresent)
        ? ! collect($hubPresent)->contains(fn ($hp) => ($hp['section']->legacyTypeKey() ?? '') === 'practice' && empty($hp['waived']))
        : \App\Support\CourseModuleMeta::shouldSkipPractice((int) ($contentIdx ?? 1));
    $p = $progress;
    $tqLast = is_array($p->theory_quiz_last_result ?? null) ? $p->theory_quiz_last_result : [];
    $exLast = is_array($p->module_exam_last_result ?? null) ? $p->module_exam_last_result : [];
    $wTq = (int) round(\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_THEORY_QUIZ * 100);
    $wPr = (int) round(\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_PRACTICE * 100);
    $wEx = (int) round(\App\Services\CourseScoringService::MODULE_SCORE_WEIGHT_EXAM * 100);
    $th = (int) ($passThreshold ?? 70);
    $tqAtt = (int) ($p->theory_quiz_attempts ?? 0);
    $tqBest = (int) ($p->theory_quiz_best_score ?? 0);
    $tqPassedEffective = isset($sectionService)
        ? $sectionService->isTheoryQuizEffectivelyPassed($p, $mid)
        : (bool) $p->theory_quiz_passed;
    $exAtt = (int) ($p->module_exam_attempts ?? 0);
    $exBest = (int) ($p->module_exam_best_score ?? 0);
    $exMax = (int) ($examMaxAttemptsDisplay ?? \App\Services\CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS);
    $thEx = (int) ($passThresholdExam ?? $passThreshold ?? $th);
    $showScorePercents = $showScorePercents ?? true;
    $showScorePoints = $showScorePoints ?? ($showModuleScoring ?? true);

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
    if ($showScorePercents && ! empty($tqLast['penalty_points'])) {
        $tqParts[] = 'штраф −'.(int) $tqLast['penalty_points'].' п.п.';
    }
    $tqLine2 = $tqAtt > 0
        ? implode(' · ', $tqParts)
        : ($showScorePercents
            ? 'Порог зачёта '.$th.'% — после попытки здесь появится результат'
            : 'После попытки здесь появится результат');

    $prLine2 = $p->practice_done_at
        ? (($p->practice_done_at instanceof \DateTimeInterface) ? 'Зачтено '.$p->practice_done_at->format('d.m.Y H:i') : 'Зачтено')
        : ($showScorePercents
            ? 'После автопроверки стенда — процент по чек-листу'
            : 'После автопроверки стенда');

    $exParts = [];
    if ($exAtt > 0) {
        $exParts[] = 'попытка '.$exAtt.'/'.$exMax;
    }
    if ($exAtt > 0 && isset($exLast['correct_count'], $exLast['total'])) {
        $exParts[] = (int) $exLast['correct_count'].'/'.(int) $exLast['total'].' верных';
    }
    if ($showScorePoints && $exAtt > 0 && ! empty($exLast['earned_points']) && ! empty($exLast['max_points'])) {
        $exParts[] = (int) $exLast['earned_points'].'/'.(int) $exLast['max_points'].' баллов';
    }
    if ($showScorePercents && $exAtt > 0 && ! empty($exLast['penalty_applied'])) {
        $exParts[] = 'пересдача −'.(int) ($exLast['penalty_points'] ?? 10).' п.п.';
    }
    if ($showScorePercents && $exAtt > 0 && isset($exLast['raw_percent']) && (int) $exLast['raw_percent'] !== $exBest) {
        $exParts[] = 'сырой '.(int) $exLast['raw_percent'].'%';
    }
    $exLine2 = $exAtt > 0
        ? implode(' · ', $exParts)
        : ($showScorePercents
            ? 'Порог '.$thEx.'% · до '.$exMax.' попыток'
            : 'До '.$exMax.' попыток');
@endphp

@section('title', 'Модуль '.$modNum.': '.$meta['title'])

@section('content')
    <div class="page-container">
    <div class="module-hub">
        <a class="back-link module-hub__back" href="{{ route('dashboard') }}">
            @include('partials.ap-icon', ['name' => 'arrow-left', 'class' => ''])
            <span>К модулям курса</span>
        </a>
        <div class="module-header-card">
            <header class="module-hub__head">
                <p class="module-badge">Модуль {{ $modNum }}@if (! empty($meta['letter']))<span class="muted"> · {{ $meta['letter'] }}</span>@endif</p>
                <h1 class="module-hub__h1">Модуль {{ $modNum }}: {{ $meta['title'] ?? 'Без названия' }}</h1>
                @if (! empty($meta['summary']))
                    <p class="muted module-hub__lead">{{ $meta['summary'] }}</p>
                @endif
            </header>

            @php
                $hubHasScoreRow = $showScorePercents || $showScorePoints;
            @endphp
            @if ($hubHasScoreRow)
            <div class="module-progress-row @if (! ($showScorePercents && $showScorePoints)) module-progress-row--single @endif" aria-label="Сводка по модулю">
                @if ($showScorePercents)
                <div class="module-progress-item">
                    <div class="module-progress-label">Этапы</div>
                    <div class="module-progress-value">{{ (int) $percent }}%</div>
                </div>
                @endif
                @if ($showScorePoints)
                    <div class="module-progress-item">
                        <div class="module-progress-label">Баллы</div>
                        <div class="module-progress-value">{{ (int) ($modulePoints ?? 0) }}<span class="module-hub__stat-fr">/100</span></div>
                    </div>
                @endif
            </div>
            @endif
            @if ($showScorePercents)
            <div class="progress-track module-hub__overall-bar" title="Доля завершённых этапов модуля" aria-hidden="true">
                <div class="progress-fill" style="width: {{ min(100, max(0, (int) $percent)) }}%"></div>
            </div>
            @endif
            @if ($showScorePoints)
                <p class="module-hub__legend muted small">
                    @if (! empty($scoreWeightLegend))
                        @if (count($scoreWeightLegend) > 0)
                            Веса в баллах:
                            @foreach ($scoreWeightLegend as $wl)
                                {{ $wl['label'] }} {{ $wl['pct'] }}%@if (! $loop->last) · @endif
                            @endforeach
                            @if ($showScorePercents)
                                . Порог тестов <strong>{{ $th }}%</strong>.
                            @else
                                .
                            @endif
                        @else
                            Баллы за модуль начисляются по итоговым тестам@if ($showScorePercents) с порогом <strong>{{ $th }}%</strong>@endif.
                        @endif
                    @elseif ($skipPractice)
                        Баллы: без практики веса пересчитываются.@if ($showScorePercents) Тесты — порог <strong>{{ $th }}%</strong>.@endif
                    @else
                        Веса в баллах: {{ $tTq }} {{ $wTq }}% · {{ $tPr }} {{ $wPr }}% · {{ $tEx }} {{ $wEx }}%.@if ($showScorePercents) Порог тестов <strong>{{ $th }}%</strong>.@endif
                    @endif
                </p>
            @endif
        </div>

        @if (! empty($showHubBriefing))
            {{-- Не используем <dialog open>: в браузерах top-layer блокирует клики по этапам под окном. --}}
            <div class="hub-briefing card" id="hub-briefing" role="region" aria-labelledby="hub-briefing-title" style="margin-bottom:1rem;border-color:#b8dcc8;background:linear-gradient(160deg,#f0faf7,#fff)">
                <div class="card-inner" style="padding:0.85rem 1rem">
                    <h2 id="hub-briefing-title" style="margin:0 0 0.35rem;font-size:1rem">Перед началом</h2>
                    <p class="muted small" style="margin:0 0 0.75rem">
                        @if (! empty($hubPresent))
                            Пройдите этапы модуля по порядку — от первого к последнему в списке ниже.
                        @else
                            Пройдите этапы по порядку: теория → тест → практика (если есть) → итоговый тест.
                        @endif
                    </p>
                    <form method="post" action="{{ route('course.module.hub.ack', $lr) }}" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn-primary">Понятно, продолжить</button>
                    </form>
                </div>
            </div>
        @endif

        <nav aria-label="Этапы модуля" class="card module-hub__nav">
            <ul class="module-hub__steps">
                @if (! empty($hubPresent))
                    @foreach ($hubPresent as $hp)
                        @include('modules.partials.hub-step-row', [
                            'courseId' => $courseId ?? session('course_id'),
                            'module' => $modNum,
                            'section' => $hp['section'],
                            'waived' => $hp['waived'],
                            'accessible' => $hp['accessible'],
                            'idx' => $loop->iteration,
                            'title' => $hp['section']->title,
                            'p' => $p,
                            'th' => $th,
                            'thEx' => $thEx,
                            'exMax' => $exMax,
                            'sectionService' => $sectionService,
                            'showScorePercents' => $showScorePercents,
                            'showScorePoints' => $showScorePoints,
                        ])
                    @endforeach
                @else
                <li>
                    <a class="hub-row section-card" href="{{ route('course.module.theory', $lr) }}">
                        <div class="hub-row__left">
                            <span class="hub-idx" aria-hidden="true">1</span>
                            <span class="hub-title-wrap"><span class="hub-title">{{ $tTheory }}</span></span>
                        </div>
                        <div class="hub-meta">
                            <div class="hub-line1">
                                <div class="hub-track" title="Этап: просмотр теории">
                                    <div class="hub-track__fill{{ $theoryBar >= 100 ? '' : ' hub-track__fill--muted' }}" style="width: {{ (int) $theoryBar }}%"></div>
                                </div>
                                @if ($showScorePercents)
                                    <span class="hub-pct hub-pct--muted">{{ $p->theory_read_at ? '100' : '0' }}%</span>
                                @endif
                                @if ($p->theory_read_at)
                                    <span class="hub-badge hub-badge--ok badge-done">Готово</span>
                                @else
                                    <span class="hub-badge hub-badge--wait">Дальше</span>
                                @endif
                            </div>
                            <p class="hub-line2">{{ $theoryLine2 }}</p>
                        </div>
                        <span class="hub-row__go" aria-hidden="true">@include('partials.ap-icon', ['name' => 'chevron-right'])</span>
                    </a>
                </li>

                <li>
                    <a class="hub-row section-card" href="{{ route('course.module.theory-quiz', $lr) }}">
                        <div class="hub-row__left">
                            <span class="hub-idx" aria-hidden="true">2</span>
                            <span class="hub-title-wrap"><span class="hub-title">{{ $tTq }}</span></span>
                        </div>
                        <div class="hub-meta">
                            <div class="hub-line1">
                                <div class="hub-track" title="{{ $showScorePercents ? 'Лучший результат, порог '.$th.'%' : 'Лучший результат' }}">
                                    @if ($showScorePercents)
                                        <span class="hub-track__tick" style="left: {{ $th }}%"></span>
                                    @endif
                                    <div class="hub-track__fill{{ $tqBar >= $th ? '' : ' hub-track__fill--muted' }}" style="width: {{ (int) $tqBar }}%"></div>
                                </div>
                                @if ($showScorePercents)
                                    <span class="hub-pct">{{ $tqAtt > 0 ? $tqBest : '—' }}@if($tqAtt > 0)%@endif</span>
                                @endif
                                @if ($tqPassedEffective)
                                    <span class="hub-badge hub-badge--counted badge-counted">Зачтён</span>
                                @elseif ($tqAtt > 0)
                                    <span class="hub-badge hub-badge--no badge-fail">{{ $showScorePercents ? 'Ниже '.$th.'%' : 'Не зачтён' }}</span>
                                @else
                                    <span class="hub-badge hub-badge--wait">Тест</span>
                                @endif
                            </div>
                            <p class="hub-line2">{{ $tqLine2 }}</p>
                        </div>
                        <span class="hub-row__go" aria-hidden="true">@include('partials.ap-icon', ['name' => 'chevron-right'])</span>
                    </a>
                </li>

                @if ($skipPractice)
                    <li>
                        <div class="hub-row section-card hub-row--disabled locked" role="group" aria-label="{{ $tPr }}: не предусмотрена">
                            <div class="hub-row__left">
                                <span class="hub-idx" aria-hidden="true">3</span>
                                <span class="hub-title-wrap"><span class="hub-title">{{ $tPr }}</span></span>
                            </div>
                            <div class="hub-meta">
                                <div class="hub-line1">
                                    <div class="hub-track" title="Нет этапа">
                                        <div class="hub-track__fill hub-track__fill--muted" style="width: 0%"></div>
                                    </div>
                                    @if ($showScorePercents)
                                        <span class="hub-pct hub-pct--muted">—</span>
                                    @endif
                                    <span class="hub-badge hub-badge--na">Нет</span>
                                </div>
                                <p class="hub-line2 muted" style="margin:0">Практика в этом модуле не входит в курс. К итоговому тесту можно перейти после теста по теории.@if ($showScorePoints) Баллы модуля — только теория-тест и экзамен.@endif</p>
                            </div>
                            <span class="hub-row__go hub-pct--muted" aria-hidden="true">·</span>
                        </div>
                    </li>
                @else
                    <li>
                        <a class="hub-row section-card" href="{{ route('course.module.practice', $lr) }}">
                            <div class="hub-row__left">
                                <span class="hub-idx" aria-hidden="true">3</span>
                                <span class="hub-title-wrap"><span class="hub-title">{{ $tPr }}</span></span>
                            </div>
                            <div class="hub-meta">
                                <div class="hub-line1">
                                    <div class="hub-track" title="Автопроверка стенда">
                                        <div class="hub-track__fill{{ $prPct >= 100 ? '' : ($prPct > 0 ? '' : ' hub-track__fill--muted') }}" style="width: {{ (int) min(100, $prPct) }}%"></div>
                                    </div>
                                    @if ($showScorePercents)
                                        <span class="hub-pct">{{ $p->practice_lab_percent !== null ? (int) $p->practice_lab_percent.'%' : ($p->practice_done_at ? '100%' : '—') }}</span>
                                    @endif
                                    @if ($p->practice_done_at)
                                        <span class="hub-badge hub-badge--ok badge-done">Готово</span>
                                    @else
                                        <span class="hub-badge hub-badge--wait">Стенд</span>
                                    @endif
                                </div>
                                <p class="hub-line2">{{ $prLine2 }}</p>
                            </div>
                            <span class="hub-row__go" aria-hidden="true">@include('partials.ap-icon', ['name' => 'chevron-right'])</span>
                        </a>
                    </li>
                @endif

                <li>
                    <a class="hub-row section-card" href="{{ route('course.module.exam', $lr) }}">
                        <div class="hub-row__left">
                            <span class="hub-idx" aria-hidden="true">{{ $skipPractice ? 3 : 4 }}</span>
                            <span class="hub-title-wrap"><span class="hub-title">{{ $tEx }}</span></span>
                        </div>
                        <div class="hub-meta">
                            <div class="hub-line1">
                                <div class="hub-track" title="{{ $showScorePercents ? 'Итог последней попытки, порог '.$thEx.'%' : 'Итог последней попытки' }}">
                                    @if ($showScorePercents)
                                        <span class="hub-track__tick" style="left: {{ $thEx }}%"></span>
                                    @endif
                                    <div class="hub-track__fill{{ $exBar >= $thEx ? '' : ' hub-track__fill--muted' }}" style="width: {{ (int) $exBar }}%"></div>
                                </div>
                                @if ($showScorePercents)
                                    <span class="hub-pct">{{ $exAtt > 0 ? $exBest : '—' }}@if($exAtt > 0)%@endif</span>
                                @endif
                                @if ($p->module_exam_passed)
                                    <span class="hub-badge hub-badge--counted badge-counted">Зачтён</span>
                                @elseif ($exAtt > 0)
                                    <span class="hub-badge hub-badge--warn">Ещё раз</span>
                                @else
                                    <span class="hub-badge hub-badge--wait">Экзамен</span>
                                @endif
                            </div>
                            <p class="hub-line2">{{ $exLine2 }}</p>
                        </div>
                        <span class="hub-row__go" aria-hidden="true">@include('partials.ap-icon', ['name' => 'chevron-right'])</span>
                    </a>
                </li>
                @endif
            </ul>
        </nav>

        @if (! empty($difficultyEnabled) && ! empty($difficultyOptions))
            <section class="module-hub__diff card">
                <h2 class="module-hub__diff-h">Сложности по этапам</h2>
                <form method="post" action="{{ route('course.module.difficulties', $lr) }}">
                    @csrf
                    <div class="module-hub__diff-grid">
                        @foreach ($difficultyOptions as $opt)
                            @php $k = (string) ($opt['key'] ?? ''); @endphp
                            @if ($k !== '')
                                <label>
                                    <input type="checkbox" name="d_{{ $k }}" value="1"
                                        @if (old('d_'.$k, (bool) data_get($p->difficulty_flags, $k))) checked @endif>
                                    {{ $opt['title'] ?? $k }}
                                </label>
                            @endif
                        @endforeach
                    </div>
                    <div style="margin-top: 0.65rem">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </section>
        @endif
    </div>
    </div>
@endsection
