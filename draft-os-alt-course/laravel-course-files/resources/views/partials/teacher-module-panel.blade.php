@php
    use App\Support\DurationFormat;

    $p = $panel['progress'];
    $rep = $panel['report'];
    $mid = $panel['module_id'];
    $modSeq = (int) ($panel['sequence'] ?? $mid);
    $ps = $panel['practice_session'];
    $sectionRows = is_array($panel['section_rows'] ?? null) ? $panel['section_rows'] : [];
    $presentTypes = [];
    foreach ($sectionRows as $_sr) {
        $bk = (string) ($_sr['backend_key'] ?? '');
        $presentTypes[$bk] = true;
        $st = (string) ($_sr['section_type'] ?? '');
        if ($st !== '') {
            $presentTypes[$st] = true;
        }
    }
    $hasTheory = isset($presentTypes['theory']) || isset($presentTypes['text']);
    $hasTheoryQuiz = isset($presentTypes['theory_quiz']) || isset($presentTypes['quiz']);
    $hasPractice = isset($presentTypes['practice']);
    $hasExam = isset($presentTypes['module_exam']) || isset($presentTypes['exam']);
    if ($sectionRows === []) {
        $hasTheory = true;
        $hasTheoryQuiz = true;
        $hasPractice = true;
        $hasExam = true;
    }
@endphp
<details class="card teacher-mod-panel" id="mod-{{ $mid }}" style="margin-top:0.75rem">
    <summary style="cursor:pointer;padding:0.35rem 0;list-style-position:outside">
        <strong>Модуль {{ $modSeq }}</strong>
        @if ($panel['letter'] !== '')
            <span class="muted">({{ $panel['letter'] }})</span>
        @endif
        — {{ $panel['title'] }}
        @if (is_array($rep))
            <span class="muted"> · балл <strong>{{ $rep['points'] ?? 0 }}</strong></span>
        @endif
    </summary>
    <div style="padding-top:0.5rem;border-top:1px solid var(--line, #e1e8ea)">

        <h3 class="muted" style="margin:0 0 0.5rem;font-size:0.95rem">Учёт времени по разделам</h3>
        @if ($p)
            <table class="teacher-report-table" style="margin-bottom:1rem">
                <tbody>
                @if ($sectionRows !== [])
                    @foreach ($sectionRows as $_sr)
                        @php
                            $srBk = (string) ($_sr['backend_key'] ?? '');
                            $secSeconds = match ($srBk) {
                                'theory' => (int) ($p->seconds_theory ?? 0),
                                'theory_quiz' => (int) ($p->seconds_theory_quiz ?? 0),
                                'practice' => (int) ($p->seconds_practice ?? 0),
                                'module_exam' => (int) ($p->seconds_module_exam ?? 0),
                                default => 0,
                            };
                        @endphp
                        <tr>
                            <td>{{ $_sr['label'] ?? 'Раздел' }}</td>
                            <td>{{ DurationFormat::fromSeconds($secSeconds) }}</td>
                        </tr>
                    @endforeach
                @else
                    @if ($hasTheory)
                        <tr>
                            <td>Теория (просмотр)</td>
                            <td>{{ DurationFormat::fromSeconds((int) ($p->seconds_theory ?? 0)) }}</td>
                        </tr>
                    @endif
                    @if ($hasTheoryQuiz)
                        <tr>
                            <td>Тест по теории</td>
                            <td>{{ DurationFormat::fromSeconds((int) ($p->seconds_theory_quiz ?? 0)) }}</td>
                        </tr>
                    @endif
                    @if ($hasPractice)
                        <tr>
                            <td>Практика</td>
                            <td>{{ DurationFormat::fromSeconds((int) ($p->seconds_practice ?? 0)) }}</td>
                        </tr>
                    @endif
                    @if ($hasExam)
                        <tr>
                            <td>Итоговый тест модуля</td>
                            <td>{{ DurationFormat::fromSeconds((int) ($p->seconds_module_exam ?? 0)) }}</td>
                        </tr>
                    @endif
                @endif
                <tr>
                    <td><strong>Сумма по модулю</strong></td>
                    <td><strong>{{ DurationFormat::fromSeconds(
                        (int) ($p->seconds_theory ?? 0)
                        + (int) ($p->seconds_theory_quiz ?? 0)
                        + (int) ($p->seconds_practice ?? 0)
                        + (int) ($p->seconds_module_exam ?? 0)
                    ) }}</strong></td>
                </tr>
                </tbody>
            </table>
            <p class="muted small" style="margin-top:0">
                Просмотр теории: {{ $p->theory_read_at ? $p->theory_read_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—' }}.
                @if ($hasPractice)
                    Практика отмечена: {{ $p->practice_done_at ? $p->practice_done_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—' }}.
                @endif
                Модуль закрыт: {{ $p->module_cleared_at ? $p->module_cleared_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—' }}.
            </p>
            @php $df = $p->difficulty_flags ?? []; @endphp
            @if (is_array($df) && (array_filter($df)))
                <p class="muted small"><strong>«Было сложно»:</strong>
                    @foreach ($sectionRows as $_sr)
                        @php
                            $lk = (string) ($_sr['backend_key'] ?? '');
                        @endphp
                        @if (! empty($df[$lk]))
                            {{ $_sr['label'] ?? $lk }}@if(!$loop->last); @endif
                        @endif
                    @endforeach
                    @if ($sectionRows === [])
                        @foreach (['theory' => 'теория', 'theory_quiz' => 'тест по теории', 'practice' => 'практика', 'module_exam' => 'итоговый тест'] as $k => $lab)
                            @if (!empty($df[$k]))
                                {{ $lab }}@if(!$loop->last); @endif
                            @endif
                        @endforeach
                    @endif
                </p>
            @endif
        @else
            <p class="muted">Нет записи прогресса по этому модулю.</p>
        @endif

        @if ($hasTheoryQuiz)
            <h3 class="muted" style="margin:1.25rem 0 0.5rem;font-size:0.95rem">Тест по теории — все попытки</h3>
            @php
                $thHist = $panel['theory_quiz_history'] ?? [];
                if ($p && (! is_array($thHist) || count($thHist) === 0) && is_array($p->theory_quiz_last_result) && count($p->theory_quiz_last_result) > 0) {
                    $thHist = [$p->theory_quiz_last_result];
                }
            @endphp
            @if ($p && is_array($thHist) && count($thHist) > 0)
                @foreach ($thHist as $ti => $tattempt)
                    <div class="card" style="margin-bottom:0.75rem;background:var(--card, #fbfcfd)">
                        <p style="margin:0 0 0.5rem">
                            <strong>Попытка {{ $tattempt['attempt_no'] ?? ($ti + 1) }}</strong>
                            @if (isset($tattempt['recorded_at']))
                                <span class="muted small">{{ $tattempt['recorded_at'] }}</span>
                            @endif
                            — итог <strong>{{ $tattempt['final_percent'] ?? '—' }}%</strong>
                            (сырой {{ $tattempt['raw_percent'] ?? '—' }}%@if(!empty($tattempt['penalty_points'])), штраф −{{ $tattempt['penalty_points'] }} п.п.@endif)
                            @if (!empty($tattempt['passed']))
                                <span class="tag" style="margin-left:0.35rem">порог пройден</span>
                            @endif
                        </p>
                        @include('partials.teacher-quiz-breakdown-items', [
                            'items' => $tattempt['items'] ?? [],
                            'questionBank' => $panel['theory_questions'],
                        ])
                    </div>
                @endforeach
            @else
                <p class="muted">Попыток нет или история не сохранялась.</p>
            @endif
        @endif

        @if ($hasPractice)
            <h3 class="muted" style="margin:1.25rem 0 0.5rem;font-size:0.95rem">Практика (лабораторный стенд)</h3>
            @if ($ps)
                <table class="teacher-report-table" style="margin-bottom:0.75rem">
                    <tbody>
                    <tr><td>Статус</td><td>{{ $ps->status }}</td></tr>
                    <tr><td>Последняя проверка</td><td>{{ $ps->last_check_at ? $ps->last_check_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—' }}</td></tr>
                    <tr><td>Баллы проверки</td><td>{{ $ps->last_check_score ?? '—' }} / {{ $ps->last_check_max_score ?? '—' }}</td></tr>
                    <tr><td>Прошла проверку</td><td>{{ $ps->last_check_passed ? 'да' : 'нет' }}</td></tr>
                    <tr><td>Принято вручную</td><td>{{ $ps->accepted_at ? $ps->accepted_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—' }}</td></tr>
                    </tbody>
                </table>
                @if ($ps->last_check_log)
                    <p class="muted small" style="margin:0 0 0.25rem">Фрагмент журнала последней проверки:</p>
                    <pre class="teacher-pre-log">{{ \Illuminate\Support\Str::limit((string) $ps->last_check_log, 4000) }}</pre>
                @endif
            @else
                <p class="muted">Сессии практики нет (контейнер не запускался или данные очищены).</p>
            @endif
        @endif

        @if ($hasExam)
            <h3 class="muted" style="margin:1.25rem 0 0.5rem;font-size:0.95rem">Итоговый тест модуля — все попытки</h3>
            @php
                $exHist = $panel['module_exam_history'] ?? [];
                if ($p && (! is_array($exHist) || count($exHist) === 0) && is_array($p->module_exam_last_result) && count($p->module_exam_last_result) > 0) {
                    $exHist = [$p->module_exam_last_result];
                }
            @endphp
            @if ($p && is_array($exHist) && count($exHist) > 0)
                @foreach ($exHist as $ei => $eattempt)
                    <div class="card" style="margin-bottom:0.75rem;background:var(--card, #fbfcfd)">
                        <p style="margin:0 0 0.5rem">
                            <strong>Попытка {{ $eattempt['attempt'] ?? ($ei + 1) }}</strong>
                            @if (isset($eattempt['recorded_at']))
                                <span class="muted small">{{ $eattempt['recorded_at'] }}</span>
                            @endif
                            — итог <strong>{{ $eattempt['final_percent'] ?? '—' }}%</strong>
                            (сырой {{ $eattempt['raw_percent'] ?? '—' }}%@if(!empty($eattempt['penalty_applied'])), штраф пересдачи −{{ $eattempt['penalty_points'] ?? 10 }} п.п.@endif)
                            @if (!empty($eattempt['passed_this_attempt']))
                                <span class="tag" style="margin-left:0.35rem">порог в этой попытке</span>
                            @endif
                        </p>
                        @include('partials.teacher-quiz-breakdown-items', [
                            'items' => $eattempt['items'] ?? [],
                            'questionBank' => $panel['exam_questions'],
                        ])
                    </div>
                @endforeach
            @else
                <p class="muted">Попыток нет или история пуста.</p>
            @endif
        @endif

        @if ($p && is_array($panel['instructor_resets']) && count($panel['instructor_resets']) > 0)
            <h3 class="muted" style="margin:1.25rem 0 0.5rem;font-size:0.95rem">Сбросы преподавателя (аудит)</h3>
            <pre class="teacher-pre-log">{{ json_encode($panel['instructor_resets'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div>
</details>
<style>
    .teacher-mod-panel summary { user-select: none; }
    .teacher-pre-log {
        font-size: 0.78rem;
        max-height: 14rem;
        overflow: auto;
        padding: 0.6rem 0.75rem;
        background: #f4f6f8;
        border-radius: 8px;
        border: 1px solid var(--line, #e1e8ea);
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>
