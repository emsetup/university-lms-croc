@php
    use App\Support\DurationFormat;

    $bk = (string) ($sr['backend_key'] ?? '');
    $sectionTitle = (string) ($sr['label'] ?? 'Раздел');
    $sectionId = (int) ($sr['section_id'] ?? 0);
    $sectionType = (string) ($sr['section_type'] ?? '');
    if ($sectionType === '') {
        $sectionType = match ($bk) {
            'theory' => 'text',
            'theory_quiz' => 'quiz',
            'practice' => 'practice',
            'module_exam' => 'exam',
            'survey' => 'survey',
            default => 'text',
        };
    }
    $anchor = $sectionId > 0
        ? 'section-'.$sectionId
        : match ($bk) {
            'theory' => 'theory',
            'theory_quiz' => 'test',
            'practice' => 'practice',
            'module_exam' => 'exam',
            'survey' => 'survey',
            default => $bk,
        };
    $dataType = match ($sectionType) {
        'quiz' => 'test',
        'exam' => 'exam',
        'survey' => 'survey',
        'text' => 'theory',
        default => $sectionType,
    };
@endphp

@switch ($sectionType)
    @case ('text')
        <section class="card ap-report-mod-section section-card" id="{{ $anchor }}" data-section="{{ $anchor }}" data-type="{{ $dataType }}">
            <div class="section-card-header">
                <h2 class="section-card-title" id="ta-theory-h">{{ $sectionTitle }}</h2>
                <div class="section-menu-wrap ap-report-dropdown js-ap-dropdown">
                    <button type="button" class="section-menu-btn js-ap-dropdown-btn" aria-expanded="false" aria-haspopup="true" aria-label="Меню раздела">{!! $svgMore !!}</button>
                    <div class="dropdown-menu ap-report-dropdown__panel js-ap-dropdown-panel" hidden>
                        <a class="dropdown-item ap-report-dropdown__link" href="#tlm-jump">К быстрому переходу</a>
                    </div>
                </div>
            </div>
            <p class="section-card-lead">Просмотр материала и учёт времени.</p>
            <div class="section-card-divider" role="presentation"></div>
            <div class="section-card-body">
                @if ($p)
                    <p class="ap-report-sec-meta-line">
                        Прочитано: {{ $p->theory_read_at ? $p->theory_read_at->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—' }}
                    </p>
                    <p class="ap-report-sec-meta-line" style="margin-bottom:0">
                        Время: {{ DurationFormat::fromSeconds($secTheory) }}
                    </p>
                @else
                    <p class="muted" style="margin:0;font-size:14px">Нет записи прогресса.</p>
                @endif
            </div>
        </section>
        @break

    @case ('quiz')
        @php
            $thHist = is_array($sr['quiz_history'] ?? null) ? $sr['quiz_history'] : null;
            if ($thHist === null) {
                $thHist = $panel['theory_quiz_history'] ?? [];
                if ($p && (! is_array($thHist) || count($thHist) === 0) && is_array($p->theory_quiz_last_result) && count($p->theory_quiz_last_result) > 0) {
                    $thHist = [$p->theory_quiz_last_result];
                }
            }
            $thBank = is_array($sr['quiz_questions'] ?? null) && $sr['quiz_questions'] !== []
                ? $sr['quiz_questions']
                : ($panel['theory_questions'] ?? []);
            $tqGroupId = 'tq-'.$mid.($sectionId > 0 ? '-s'.$sectionId : '');
            $canResetThisTq = $canResetProgress && (
                (isset($sr['quiz_attempts']) && (int) $sr['quiz_attempts'] >= 1)
                || (is_array($thHist) && count($thHist) > 0)
                || $canResetTq
            );
            $tqResetAnchor = 'ta-reset-tq'.($sectionId > 0 ? '-'.$sectionId : '');
        @endphp
        <section class="card ap-report-mod-section section-card" id="{{ $anchor }}" data-section="{{ $anchor }}" data-type="{{ $dataType }}">
            <div class="section-card-header">
                <h2 class="section-card-title" id="ta-tq-h-{{ $sectionId > 0 ? $sectionId : $mid }}">{{ $sectionTitle }}</h2>
                <div class="section-card-header__actions">
                    @if ($canResetThisTq)
                        <a class="btn-reset" href="#{{ $tqResetAnchor }}">Сброс</a>
                    @endif
                    <div class="section-menu-wrap ap-report-dropdown js-ap-dropdown">
                        <button type="button" class="section-menu-btn js-ap-dropdown-btn" aria-expanded="false" aria-haspopup="true" aria-label="Меню раздела">{!! $svgMore !!}</button>
                        <div class="dropdown-menu ap-report-dropdown__panel js-ap-dropdown-panel" hidden>
                            <a class="dropdown-item ap-report-dropdown__link" href="#tlm-jump">К быстрому переходу</a>
                            @if ($canResetThisTq)
                                <a class="dropdown-item ap-report-dropdown__link dropdown-item--danger" href="#{{ $tqResetAnchor }}">Сброс попытки…</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <p class="section-card-lead">Переключайте вкладки, чтобы просмотреть каждую попытку.</p>
            <div class="section-card-divider" role="presentation"></div>
            <div class="section-card-body">
                @include('partials.teacher-attempt-tabs', [
                    'attempts' => $thHist,
                    'groupId' => $tqGroupId,
                    'attemptNoKey' => 'attempt_no',
                    'passedKey' => 'passed',
                    'penaltyFlagKey' => 'penalty_points',
                    'questionBank' => $thBank,
                    'svgThOk' => $svgThOk,
                    'svgThFail' => $svgThFail,
                ])
            </div>
            @if ($canResetThisTq)
                <div class="ap-report-reset-block" id="{{ $tqResetAnchor }}">
                    <form method="post" action="{{ $resetPostUrl }}" class="js-ta-reset-form">
                        @csrf
                        <input type="hidden" name="step" value="theory_quiz">
                        @if ($sectionId > 0)
                            <input type="hidden" name="section_id" value="{{ $sectionId }}">
                        @endif
                        <label><input type="checkbox" name="confirm" value="1" required> Сбросить последнюю «занятую» попытку: у обучающегося счётчик попыток уменьшится на 1, текущие результаты и история на его стороне очистятся; здесь останется запись в журнале сбросов.</label>
                        <input type="text" name="note" maxlength="500" placeholder="Комментарий к сбросу (необязательно)">
                        <button type="submit" class="btn btn-danger btn-sm" style="margin-top:8px">Сбросить тест по теории</button>
                    </form>
                </div>
            @endif
        </section>
        @break

    @case ('practice')
        <section class="card ap-report-mod-section section-card" id="{{ $anchor }}" data-section="{{ $anchor }}" data-type="{{ $dataType }}">
            <div class="section-card-header">
                <h2 class="section-card-title" id="ta-pr-h">{{ $sectionTitle }}</h2>
                <div class="section-card-header__actions">
                    @if ($canResetPr)
                        <a class="btn-reset" href="#ta-reset-pr">Сброс</a>
                    @endif
                    <div class="section-menu-wrap ap-report-dropdown js-ap-dropdown">
                        <button type="button" class="section-menu-btn js-ap-dropdown-btn" aria-expanded="false" aria-haspopup="true" aria-label="Меню раздела">{!! $svgMore !!}</button>
                        <div class="dropdown-menu ap-report-dropdown__panel js-ap-dropdown-panel" hidden>
                            <a class="dropdown-item ap-report-dropdown__link" href="#tlm-jump">К быстрому переходу</a>
                            @if ($canResetPr)
                                <a class="dropdown-item ap-report-dropdown__link dropdown-item--danger" href="#ta-reset-pr">Сброс попытки…</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <p class="section-card-lead">@if ($contentIdxPanel === 1)Практика: Docker-стенд. @endif Лабораторный стенд и зачёт.</p>
            <div class="section-card-divider" role="presentation"></div>
            <div class="section-card-body">
                @if ($contentIdxPanel === 1 && $p && \Illuminate\Support\Facades\Schema::hasColumn('module_progress', 'practice_m1_quest') && is_array($p->practice_m1_quest) && count($p->practice_m1_quest) > 0)
                    <p class="ap-report-sec-lead" style="margin-top:0">Архив старого веб-квеста (JSON в БД, до перехода на Docker).</p>
                    <pre class="ap-report-pre" style="max-height:16rem">{{ json_encode($p->practice_m1_quest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
                <p class="ap-report-sec-meta-line">
                    Время на практике: <strong>{{ DurationFormat::fromSeconds($secPr) }}</strong>
                </p>
                @if ($ps)
                    <div class="attempt-card">
                        <div class="attempt-header">
                            <span>Текущая сессия</span>
                            <span class="muted" style="font-weight:600">{{ $ps->status }}</span>
                        </div>
                        <div class="ap-report-pr-grid">
                            <div class="ap-report-pr-cell">
                                <span class="ap-report-pr-cell__k">Баллы проверки</span>
                                <span class="ap-report-pr-cell__v">{{ $ps->last_check_score ?? '—' }} / {{ $ps->last_check_max_score ?? '—' }}</span>
                            </div>
                            <div class="ap-report-pr-cell">
                                <span class="ap-report-pr-cell__k">Проверка</span>
                                <span class="ap-report-pr-cell__v">{{ $ps->last_check_at ? $ps->last_check_at->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—' }}</span>
                            </div>
                            <div class="ap-report-pr-cell">
                                <span class="ap-report-pr-cell__k">Принято</span>
                                <span class="ap-report-pr-cell__v">{{ $ps->accepted_at ? $ps->accepted_at->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—' }}</span>
                            </div>
                            <div class="ap-report-pr-cell">
                                <span class="ap-report-pr-cell__k">Зачёт проверки</span>
                                <span class="ap-report-pr-cell__v">{{ $ps->last_check_passed ? 'да' : 'нет' }}</span>
                            </div>
                        </div>
                        @if ($ps->last_check_log)
                            <p class="muted" style="font-size:12px;margin:8px 0 4px">Журнал проверки:</p>
                            <pre class="ap-report-pre">{{ \Illuminate\Support\Str::limit((string) $ps->last_check_log, 6000) }}</pre>
                        @endif
                    </div>
                @else
                    <p class="muted" style="margin:0;font-size:14px">Сессии практики нет.</p>
                @endif
            </div>
            @if ($canResetPr)
                <div class="ap-report-reset-block" id="ta-reset-pr">
                    <form method="post" action="{{ $resetPostUrl }}" class="js-ta-reset-form">
                        @csrf
                        <input type="hidden" name="step" value="practice">
                        <label><input type="checkbox" name="confirm" value="1" required> Сбросить практику: снимок сессии уйдёт в журнал, отметка «сдано» и проценты у обучающегося сбросятся; контейнер нужно будет запустить заново.</label>
                        <input type="text" name="note" maxlength="500" placeholder="Комментарий к сбросу (необязательно)">
                        <button type="submit" class="btn btn-danger btn-sm" style="margin-top:8px">Сбросить практику</button>
                    </form>
                </div>
            @endif
        </section>
        @break

    @case ('exam')
        @php
            $exHist = is_array($sr['quiz_history'] ?? null) ? $sr['quiz_history'] : null;
            if ($exHist === null) {
                $exHist = $panel['module_exam_history'] ?? [];
                if ($p && (! is_array($exHist) || count($exHist) === 0) && is_array($p->module_exam_last_result) && count($p->module_exam_last_result) > 0) {
                    $exHist = [$p->module_exam_last_result];
                }
            }
            $exBank = is_array($sr['quiz_questions'] ?? null) && $sr['quiz_questions'] !== []
                ? $sr['quiz_questions']
                : ($panel['exam_questions'] ?? []);
            $exGroupId = 'ex-'.$mid.($sectionId > 0 ? '-s'.$sectionId : '');
            $canResetThisEx = $canResetProgress && (
                (isset($sr['quiz_attempts']) && (int) $sr['quiz_attempts'] >= 1)
                || (is_array($exHist) && count($exHist) > 0)
                || $canResetEx
            );
            $exResetAnchor = 'ta-reset-ex'.($sectionId > 0 ? '-'.$sectionId : '');
        @endphp
        <section class="card ap-report-mod-section section-card" id="{{ $anchor }}" data-section="{{ $anchor }}" data-type="{{ $dataType }}">
            <div class="section-card-header">
                <h2 class="section-card-title" id="ta-ex-h-{{ $sectionId > 0 ? $sectionId : $mid }}">{{ $sectionTitle }}</h2>
                <div class="section-card-header__actions">
                    @if ($canResetThisEx)
                        <a class="btn-reset" href="#{{ $exResetAnchor }}">Сброс</a>
                    @endif
                    <div class="section-menu-wrap ap-report-dropdown js-ap-dropdown">
                        <button type="button" class="section-menu-btn js-ap-dropdown-btn" aria-expanded="false" aria-haspopup="true" aria-label="Меню раздела">{!! $svgMore !!}</button>
                        <div class="dropdown-menu ap-report-dropdown__panel js-ap-dropdown-panel" hidden>
                            <a class="dropdown-item ap-report-dropdown__link" href="#tlm-jump">К быстрому переходу</a>
                            @if ($canResetThisEx)
                                <a class="dropdown-item ap-report-dropdown__link dropdown-item--danger" href="#{{ $exResetAnchor }}">Сброс попытки…</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <p class="section-card-lead">Переключайте вкладки, чтобы просмотреть каждую попытку.</p>
            <div class="section-card-divider" role="presentation"></div>
            <div class="section-card-body">
                <p class="ap-report-sec-meta-line" style="margin-top:0">
                    Время на экзамене: <strong>{{ DurationFormat::fromSeconds($secEx) }}</strong>
                </p>
                @include('partials.teacher-attempt-tabs', [
                    'attempts' => $exHist,
                    'groupId' => $exGroupId,
                    'attemptNoKey' => 'attempt',
                    'passedKey' => 'passed_this_attempt',
                    'penaltyFlagKey' => 'penalty_applied',
                    'questionBank' => $exBank,
                    'svgThOk' => $svgThOk,
                    'svgThFail' => $svgThFail,
                ])
            </div>
            @if ($canResetThisEx)
                <div class="ap-report-reset-block" id="{{ $exResetAnchor }}">
                    <form method="post" action="{{ $resetPostUrl }}" class="js-ta-reset-form">
                        @csrf
                        <input type="hidden" name="step" value="module_exam">
                        @if ($sectionId > 0)
                            <input type="hidden" name="section_id" value="{{ $sectionId }}">
                        @endif
                        <label><input type="checkbox" name="confirm" value="1" required> Сбросить последнюю попытку экзамена: счётчик попыток −1, видимые результаты очищаются, снимок — в журнале.</label>
                        <input type="text" name="note" maxlength="500" placeholder="Комментарий к сбросу (необязательно)">
                        <button type="submit" class="btn btn-danger btn-sm" style="margin-top:8px">Сбросить экзамен</button>
                    </form>
                </div>
            @endif
        </section>
        @break

    @case ('survey')
        @php
            $surveysMap = is_array($panel['surveys'] ?? null) ? $panel['surveys'] : [];
            $sv = is_array($surveysMap[$sectionId] ?? null)
                ? $surveysMap[$sectionId]
                : (is_array($panel['survey'] ?? null) ? $panel['survey'] : []);
            $svCard = is_array($sv['card'] ?? null) ? $sv['card'] : [];
            $svSubmitted = ! empty($svCard['submitted']);
        @endphp
        <section class="card ap-report-mod-section section-card" id="{{ $anchor }}" data-section="{{ $anchor }}" data-type="survey">
            <div class="section-card-header">
                <h2 class="section-card-title" id="ta-survey-h">{{ $sectionTitle }}</h2>
                <div class="section-card-header__actions">
                    @if (! empty($sv['responses_url']))
                        <a class="btn btn-ghost btn-sm" href="{{ $sv['responses_url'] }}" target="_blank" rel="noopener">Сводка</a>
                    @endif
                    <div class="section-menu-wrap ap-report-dropdown js-ap-dropdown">
                        <button type="button" class="section-menu-btn js-ap-dropdown-btn" aria-expanded="false" aria-haspopup="true" aria-label="Меню раздела">{!! $svgMore !!}</button>
                        <div class="dropdown-menu ap-report-dropdown__panel js-ap-dropdown-panel" hidden>
                            <a class="dropdown-item ap-report-dropdown__link" href="#tlm-jump">К быстрому переходу</a>
                            @if (! empty($sv['responses_url']))
                                <a class="dropdown-item ap-report-dropdown__link" href="{{ $sv['responses_url'] }}" target="_blank" rel="noopener">Сводная таблица и CSV</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <p class="section-card-lead">Сбор ответов без оценки · данные для аналитики курса.</p>
            <div class="section-card-divider" role="presentation"></div>
            <div class="section-card-body">
                @if (! $svSubmitted)
                    <p class="ap-report-sec-meta-line" style="margin-bottom:0">Обучающийся ещё не отправил ответы.</p>
                @elseif (! empty($svCard['anonymous']))
                    <p class="ap-report-sec-meta-line">Анонимный опрос — ответы скрыты в карточке участника.</p>
                    @if (! empty($svCard['submitted_at']))
                        <p class="ap-report-sec-meta-line" style="margin-bottom:0">Отправлено: {{ $svCard['submitted_at'] }}</p>
                    @endif
                @else
                    @if (! empty($svCard['submitted_at']))
                        <p class="ap-report-sec-meta-line">Отправлено: {{ $svCard['submitted_at'] }}</p>
                    @endif
                    <ul class="ap-report-survey-answers">
                        @foreach ($svCard['items'] ?? [] as $it)
                            <li class="ap-report-survey-answers__item">
                                <p class="ap-report-sec-meta-line ap-report-survey-answers__q">{{ $it['question'] ?? '' }}</p>
                                <p class="ap-report-sec-meta-line ap-report-survey-answers__a" style="margin-bottom:0">{{ $it['answer'] ?? '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
        @break
@endswitch
