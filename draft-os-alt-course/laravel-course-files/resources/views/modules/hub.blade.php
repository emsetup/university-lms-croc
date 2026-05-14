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
    <div class="page-container">
    <div class="module-hub">
        <a class="back-link module-hub__back" href="{{ route('dashboard') }}">
            @include('partials.ap-icon', ['name' => 'arrow-left', 'class' => ''])
            <span>К модулям курса</span>
        </a>
        <div class="module-header-card">
            <header class="module-hub__head">
                <p class="module-badge">Модуль {{ $meta['letter'] ?? $module }}</p>
                <h1 class="module-hub__h1">Модуль {{ $module }}: {{ $meta['title'] ?? 'Без названия' }}</h1>
                @if (! empty($meta['summary']))
                    <p class="muted module-hub__lead">{{ $meta['summary'] }}</p>
                @endif
            </header>

            <div class="module-progress-row" aria-label="Сводка по модулю">
                <div class="module-progress-item">
                    <div class="module-progress-label">Этапы</div>
                    <div class="module-progress-value">{{ (int) $percent }}%</div>
                </div>
                <div class="module-progress-item">
                    <div class="module-progress-label">Баллы</div>
                    <div class="module-progress-value">{{ (int) ($modulePoints ?? 0) }}<span class="module-hub__stat-fr">/100</span></div>
                </div>
            </div>
            <div class="progress-track module-hub__overall-bar" title="Доля завершённых этапов модуля" aria-hidden="true">
                <div class="progress-fill" style="width: {{ min(100, max(0, (int) $percent)) }}%"></div>
            </div>
            <p class="module-hub__legend muted small">
                @if ($skipPractice)
                    Баллы: без практики веса пересчитываются. Тесты — порог <strong>{{ $th }}%</strong>.
                @else
                    Веса в баллах: {{ $tTq }} {{ $wTq }}% · {{ $tPr }} {{ $wPr }}% · {{ $tEx }} {{ $wEx }}%. Порог тестов <strong>{{ $th }}%</strong>.
                @endif
            </p>
        </div>

        @if (! empty($showHubBriefing))
            {{-- Не используем <dialog open>: в браузерах top-layer блокирует клики по этапам под окном. --}}
            <div class="hub-briefing card" id="hub-briefing" role="region" aria-labelledby="hub-briefing-title" style="margin-bottom:1rem;border-color:#b8dcc8;background:linear-gradient(160deg,#f0faf7,#fff)">
                <div class="card-inner" style="padding:0.85rem 1rem">
                    <h2 id="hub-briefing-title" style="margin:0 0 0.35rem;font-size:1rem">Перед началом</h2>
                    <p class="muted small" style="margin:0 0 0.75rem">Пройдите этапы по порядку: теория → тест → практика (если есть) → итоговый тест.</p>
                    <form method="post" action="{{ route('modules.hub.ack', $module) }}" style="margin:0">
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
                    <a class="hub-row section-card" href="{{ route('modules.theory', $module) }}">
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
                    <a class="hub-row section-card" href="{{ route('modules.theory-quiz', $module) }}">
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
                                    <span class="hub-badge hub-badge--counted badge-counted">Зачтён</span>
                                @elseif ($tqAtt > 0)
                                    <span class="hub-badge hub-badge--no badge-fail">Ниже {{ $th }}%</span>
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
                        <a class="hub-row section-card" href="{{ route('modules.practice', $module) }}">
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
                    <a class="hub-row section-card" href="{{ route('modules.exam', $module) }}">
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

        <section class="module-hub__diff card">
            <h2 class="module-hub__diff-h">Сложности по этапам</h2>
            <form method="post" action="{{ route('modules.difficulties', $module) }}">
                @csrf
                <div class="module-hub__diff-grid">
                    <label><input type="checkbox" name="d_theory" value="1" @if (old('d_theory', (bool) data_get($p->difficulty_flags, 'theory'))) checked @endif> {{ $tTheory }}</label>
                    <label><input type="checkbox" name="d_theory_quiz" value="1" @if (old('d_theory_quiz', (bool) data_get($p->difficulty_flags, 'theory_quiz'))) checked @endif> {{ $tTq }}</label>
                    @if (! $skipPractice)
                        <label><input type="checkbox" name="d_practice" value="1" @if (old('d_practice', (bool) data_get($p->difficulty_flags, 'practice'))) checked @endif> {{ $tPr }}</label>
                    @endif
                    <label><input type="checkbox" name="d_module_exam" value="1" @if (old('d_module_exam', (bool) data_get($p->difficulty_flags, 'module_exam'))) checked @endif> {{ $tEx }}</label>
                </div>
                <div style="margin-top: 0.65rem">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </section>
    </div>
    </div>
@endsection
