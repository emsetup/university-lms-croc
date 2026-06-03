@extends('layouts.admin')

@php
    use App\Support\AdminNavigation;
    use App\Support\DurationFormat;

    $layoutCourseChrome = AdminNavigation::showCourseChrome();

    $enrollment = $learner->courseEnrollments->sortBy('id')->first();
    $courseModel = ($forcedCourse ?? null)
        ?? $enrollment?->course
        ?? \App\Models\Course::query()->where('slug', 'alt-os-features')->first();
    $tp = $courseModel ? ['adminCourse' => $courseModel->slug] : [];
    $cid = $courseModel ? (int) $courseModel->id : 0;
    $en = $cid > 0 ? (int) \App\Models\CourseEnrollment::query()->where('course_id', $cid)->count() : 0;
    $completed = $cid > 0
        ? (int) \App\Models\FinalLabResult::query()->where('course_id', $cid)->whereNotNull('completed_at')->count()
        : 0;
    $canResetProgress = $cid > 0 && (($portalStaffAccess ?? null)?->canResetLearnerProgressForCourse($cid) ?? false);
    $learnersListUrl = $tp !== [] ? route('admin.learners.course', $tp) : route('teacher.course-report');
    $learnerCardUrl = $tp !== []
        ? route('admin.learners.course.learner', array_merge($tp, ['learner' => $learner->id]))
        : route('teacher.course-report.learner', $learner->id);
    $p = $panel['progress'];
    $rep = $panel['report'];
    $mid = (int) $panel['module_id'];
    $resetPostUrl = $tp !== []
        ? route('admin.learners.course.learner.reset', array_merge($tp, ['learner' => $learner->id, 'courseModule' => $mid]))
        : route('teacher.course-report.learner.module.reset', ['learner' => $learner->id, 'module' => $mid]);
    $ps = $panel['practice_session'];
    $tqHistTop = $panel['theory_quiz_history'] ?? [];
    $exHistTop = $panel['module_exam_history'] ?? [];
    $canResetTq =
        $canResetProgress
        && $p
        && ((int) $p->theory_quiz_attempts >= 1
            || is_array($p->theory_quiz_last_result)
            || (is_array($tqHistTop) && count($tqHistTop) > 0));
    $canResetPr = $canResetProgress && $p && ($p->practice_done_at || $ps);
    $canResetEx =
        $canResetProgress
        && $p
        && ((int) $p->module_exam_attempts >= 1
            || is_array($p->module_exam_last_result)
            || (is_array($exHistTop) && count($exHistTop) > 0));
    $secTheory = $p ? (int) ($p->seconds_theory ?? 0) : 0;
    $secTq = $p ? (int) ($p->seconds_theory_quiz ?? 0) : 0;
    $secPr = $p ? (int) ($p->seconds_practice ?? 0) : 0;
    $secEx = $p ? (int) ($p->seconds_module_exam ?? 0) : 0;
    $secMod = $secTheory + $secTq + $secPr + $secEx;
    $contentIdxPanel = (int) ($panel['content_source_index'] ?? 1);
    $skipPractice = \App\Support\CourseModuleMeta::shouldSkipPractice($contentIdxPanel);
    $pts = is_array($rep) ? (int) ($rep['points'] ?? 0) : 0;
    $tqPct = is_array($rep) ? (int) ($rep['theory_quiz_pct'] ?? 0) : 0;
    $prPct = is_array($rep) && ! $skipPractice ? (int) ($rep['practice_pct'] ?? 0) : null;
    $exPct = is_array($rep) ? (int) ($rep['exam_pct'] ?? 0) : 0;
    $b100 = $pts % 100;
    $n1 = $pts % 10;
    $ptsWord = $pts
        . ' '
        . (($b100 >= 11 && $b100 <= 14)
            ? 'баллов'
            : ($n1 === 1
                ? 'балл'
                : (($n1 >= 2 && $n1 <= 4)
                    ? 'балла'
                    : 'баллов')));
    $svgMore = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>';
    $svgThOk = '<svg class="badge-threshold__icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
    $svgThFail = '<svg class="badge-threshold__icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>';
@endphp

@section('title', 'Модуль '.$mid.' — '.$panel['title'])

@section('content')
    <script>
        document.body.dataset.apTeacherReport = '1';
        document.documentElement.classList.add('ap-smooth-anchor-scroll');
        document.querySelector('.admin-topbar__nav a.nav-item')?.classList.add('active');
    </script>

    <div class="learner-page-content">
    <div class="ap-report-bleed">
        @if (! $layoutCourseChrome && $courseModel)
            <div class="ap-course-context">
                <div class="ap-course-context__row">
                    <div class="ap-course-context__brand">
                        @include('partials.ap-icon', ['name' => 'book-open', 'size' => 'md'])
                        <span class="ap-course-context__title">{{ $courseModel->title }}</span>
                        @if ($courseModel->is_archived)
                            <span class="ap-badge ap-badge--archive">Архив</span>
                        @elseif ($courseModel->is_published)
                            <span class="ap-badge ap-badge--published">Опубликован</span>
                        @else
                            <span class="ap-badge ap-badge--draft">Черновик</span>
                        @endif
                    </div>
                    <a class="ap-course-context__back" href="{{ route('admin.courses.index') }}">← Курсы</a>
                </div>
                <p class="ap-course-context__meta">
                    slug: <code>{{ $courseModel->slug }}</code>
                    · {{ $en }} участников
                    · {{ $completed }} завершили
                </p>
            </div>
            @php
                $psaTabs = $portalStaffAccess ?? null;
                $canToolsTabs = $psaTabs && $psaTabs->canUseCourseAdminTools();
                $canViewLearnersTabs = $psaTabs && $cid > 0 && $psaTabs->canViewCourseLearnerStats($cid);
            @endphp
            <nav class="ap-course-tabs" aria-label="Разделы курса">
                @if ($canToolsTabs)
                    <a class="ap-course-tabs__a" href="{{ route('admin.course.settings', $tp) }}">Модули</a>
                @endif
                @if ($canToolsTabs || ($psaTabs && $psaTabs->isCourseTester()))
                    <a class="ap-course-tabs__a" href="{{ route('admin.theory.index', $tp) }}">Содержимое</a>
                @endif
                @if ($canToolsTabs || $canViewLearnersTabs)
                    <a class="ap-course-tabs__a ap-course-tabs__a--active" href="{{ route('admin.learners.course', $tp) }}">Обучающиеся</a>
                @endif
                @if ($canToolsTabs)
                    <a class="ap-course-tabs__a" href="{{ route('admin.certificates', $tp) }}">Сертификаты</a>
                @endif
            </nav>
        @endif
        <div class="admin-breadcrumb-wrap ap-report-breadcrumb-below-tabs">
            <nav class="breadcrumb" aria-label="Хлебные крошки">
                <a href="{{ route('admin.panel') }}">Панель</a>
                <span class="breadcrumb-sep" aria-hidden="true">›</span>
                <a href="{{ route('admin.courses.index') }}">Курсы</a>
                @if ($courseModel)
                    <span class="breadcrumb-sep" aria-hidden="true">›</span>
                    <a href="{{ route('admin.theory.index', $tp) }}">{{ $courseModel->title }}</a>
                    <span class="breadcrumb-sep" aria-hidden="true">›</span>
                    <a href="{{ $learnersListUrl }}">Обучающиеся</a>
                @endif
                <span class="breadcrumb-sep" aria-hidden="true">›</span>
                <a href="{{ $learnerCardUrl }}">{{ $learner->email }}</a>
                <span class="breadcrumb-sep" aria-hidden="true">›</span>
                <span class="breadcrumb-current">Модуль {{ $mid }}</span>
            </nav>
        </div>
    </div>

    <div class="ap-report-page ap-report-stack">
        <header class="card ap-report-mod-page-head">
            <div class="tlm-page-kicker-row">
                <p class="tlm-page-kicker">
                    Модуль {{ $mid }} @if ($panel['letter'] !== '')<span class="muted">· {{ $panel['letter'] }}</span>@endif
                </p>
                <a class="btn btn-ghost btn-sm" href="{{ $learnerCardUrl }}">← К обучающемуся</a>
            </div>
            <h1 class="ap-report-mod-page-title">{{ $panel['title'] }}</h1>
            @if (is_array($rep))
                <p class="ap-report-mod-summary-line">
                    {{ $ptsWord }}
                    <span class="muted">&nbsp;·&nbsp;</span>
                    Теория {{ $tqPct }}%
                    <span class="muted">&nbsp;·&nbsp;</span>
                    @if ($skipPractice)
                        Практика —
                    @else
                        Практика {{ (int) $prPct }}%
                    @endif
                    <span class="muted">&nbsp;·&nbsp;</span>
                    Экзамен {{ $exPct }}%
                </p>
                <p class="ap-report-mod-time-sum">
                    Σ {{ DurationFormat::fromSeconds($secMod) }}
                    <span class="muted"> (теория {{ DurationFormat::fromSeconds($secTheory) }} · тест {{ DurationFormat::fromSeconds($secTq) }} · практика {{ DurationFormat::fromSeconds($secPr) }} · экзамен {{ DurationFormat::fromSeconds($secEx) }})</span>
                </p>
            @endif
        </header>

        <nav class="quick-nav" id="tlm-jump" aria-label="Быстрый переход по разделам">
            <span class="quick-nav-label">К разделу</span>
            <a class="quick-nav-btn" href="#theory">Теория</a>
            <a class="quick-nav-btn" href="#test">Тест по теории</a>
            @if (! $skipPractice)
                <a class="quick-nav-btn" href="#practice">Практика</a>
            @endif
            <a class="quick-nav-btn" href="#exam">Экзамен</a>
            @if ($p && is_array($panel['instructor_resets']) && count($panel['instructor_resets']) > 0)
                <a class="quick-nav-btn" href="#audit">Журнал сбросов</a>
            @endif
        </nav>

        <section class="card ap-report-mod-section section-card" id="theory" data-section="theory" data-type="theory">
            <div class="section-card-header">
                <h2 class="section-card-title" id="ta-theory-h">Теория</h2>
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

        <section class="card ap-report-mod-section section-card" id="test" data-section="test" data-type="test">
            <div class="section-card-header">
                <h2 class="section-card-title" id="ta-tq-h">Тест по теории</h2>
                <div class="section-card-header__actions">
                    @if ($canResetTq)
                        <a class="btn-reset" href="#ta-reset-tq">Сброс</a>
                    @endif
                    <div class="section-menu-wrap ap-report-dropdown js-ap-dropdown">
                        <button type="button" class="section-menu-btn js-ap-dropdown-btn" aria-expanded="false" aria-haspopup="true" aria-label="Меню раздела">{!! $svgMore !!}</button>
                        <div class="dropdown-menu ap-report-dropdown__panel js-ap-dropdown-panel" hidden>
                            <a class="dropdown-item ap-report-dropdown__link" href="#tlm-jump">К быстрому переходу</a>
                            @if ($canResetTq)
                                <a class="dropdown-item ap-report-dropdown__link dropdown-item--danger" href="#ta-reset-tq">Сброс попытки…</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <p class="section-card-lead">Переключайте вкладки, чтобы просмотреть каждую попытку.</p>
            <div class="section-card-divider" role="presentation"></div>
            <div class="section-card-body">
            @php
                $thHist = $panel['theory_quiz_history'] ?? [];
                if ($p && (! is_array($thHist) || count($thHist) === 0) && is_array($p->theory_quiz_last_result) && count($p->theory_quiz_last_result) > 0) {
                    $thHist = [$p->theory_quiz_last_result];
                }
            @endphp
            @include('partials.teacher-attempt-tabs', [
                'attempts' => $thHist,
                'groupId' => 'tq-' . $mid,
                'attemptNoKey' => 'attempt_no',
                'passedKey' => 'passed',
                'penaltyFlagKey' => 'penalty_points',
                'questionBank' => $panel['theory_questions'],
                'svgThOk' => $svgThOk,
                'svgThFail' => $svgThFail,
            ])
            </div>
            @if ($canResetTq)
                <div class="ap-report-reset-block" id="ta-reset-tq">
                    <form method="post" action="{{ $resetPostUrl }}" class="js-ta-reset-form">
                        @csrf
                        <input type="hidden" name="step" value="theory_quiz">
                        <label><input type="checkbox" name="confirm" value="1" required> Сбросить последнюю «занятую» попытку: у обучающегося счётчик попыток уменьшится на 1, текущие результаты и история на его стороне очистятся; здесь останется запись в журнале сбросов.</label>
                        <input type="text" name="note" maxlength="500" placeholder="Комментарий к сбросу (необязательно)">
                        <button type="submit" class="btn btn-danger btn-sm" style="margin-top:8px">Сбросить тест по теории</button>
                    </form>
                </div>
            @endif
        </section>

        <section class="card ap-report-mod-section section-card @if ($skipPractice) ap-report-section-muted @endif" id="practice" data-section="practice" data-type="practice">
            <div class="section-card-header">
                <h2 class="section-card-title" id="ta-pr-h">Практика</h2>
                @if (! $skipPractice)
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
                @endif
            </div>
            @if (! $skipPractice)
                <p class="section-card-lead">@if ($mid === 1)Практика: Docker-стенд. @endif Лабораторный стенд и зачёт.</p>
            @endif
            <div class="section-card-divider" role="presentation"></div>
            <div class="section-card-body">
            @if ($skipPractice)
                <p class="muted" style="margin:0;font-size:14px">В учебном плане этого модуля практическое занятие <strong>не предусмотрено</strong>. Итоговый балл за модуль считается только из теста по теории и итогового экзамена (веса пересчитаны).</p>
            @else
                @if ($mid === 1 && $p && \Illuminate\Support\Facades\Schema::hasColumn('module_progress', 'practice_m1_quest') && is_array($p->practice_m1_quest) && count($p->practice_m1_quest) > 0)
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
            @endif
            </div>
            @if (! $skipPractice)
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
            @endif
        </section>

        <section class="card ap-report-mod-section section-card" id="exam" data-section="exam" data-type="exam">
            <div class="section-card-header">
                <h2 class="section-card-title" id="ta-ex-h">Экзамен</h2>
                <div class="section-card-header__actions">
                    @if ($canResetEx)
                        <a class="btn-reset" href="#ta-reset-ex">Сброс</a>
                    @endif
                    <div class="section-menu-wrap ap-report-dropdown js-ap-dropdown">
                        <button type="button" class="section-menu-btn js-ap-dropdown-btn" aria-expanded="false" aria-haspopup="true" aria-label="Меню раздела">{!! $svgMore !!}</button>
                        <div class="dropdown-menu ap-report-dropdown__panel js-ap-dropdown-panel" hidden>
                            <a class="dropdown-item ap-report-dropdown__link" href="#tlm-jump">К быстрому переходу</a>
                            @if ($canResetEx)
                                <a class="dropdown-item ap-report-dropdown__link dropdown-item--danger" href="#ta-reset-ex">Сброс попытки…</a>
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
            @php
                $exHist = $panel['module_exam_history'] ?? [];
                if ($p && (! is_array($exHist) || count($exHist) === 0) && is_array($p->module_exam_last_result) && count($p->module_exam_last_result) > 0) {
                    $exHist = [$p->module_exam_last_result];
                }
            @endphp
            @include('partials.teacher-attempt-tabs', [
                'attempts' => $exHist,
                'groupId' => 'ex-' . $mid,
                'attemptNoKey' => 'attempt',
                'passedKey' => 'passed_this_attempt',
                'penaltyFlagKey' => 'penalty_applied',
                'questionBank' => $panel['exam_questions'],
                'svgThOk' => $svgThOk,
                'svgThFail' => $svgThFail,
            ])
            </div>
            @if ($canResetEx)
                <div class="ap-report-reset-block" id="ta-reset-ex">
                    <form method="post" action="{{ $resetPostUrl }}" class="js-ta-reset-form">
                        @csrf
                        <input type="hidden" name="step" value="module_exam">
                        <label><input type="checkbox" name="confirm" value="1" required> Сбросить последнюю попытку экзамена: счётчик попыток −1, видимые результаты очищаются, снимок — в журнале.</label>
                        <input type="text" name="note" maxlength="500" placeholder="Комментарий к сбросу (необязательно)">
                        <button type="submit" class="btn btn-danger btn-sm" style="margin-top:8px">Сбросить экзамен</button>
                    </form>
                </div>
            @endif
        </section>

        @if ($p && is_array($panel['instructor_resets']) && count($panel['instructor_resets']) > 0)
            <section class="card ap-report-mod-section section-card" id="audit" data-section="audit" data-type="audit">
                <div class="section-card-header">
                    <h2 class="section-card-title" id="ta-audit-h">Журнал сбросов (аудит)</h2>
                    <div class="section-menu-wrap ap-report-dropdown js-ap-dropdown">
                        <button type="button" class="section-menu-btn js-ap-dropdown-btn" aria-expanded="false" aria-haspopup="true" aria-label="Меню раздела">{!! $svgMore !!}</button>
                        <div class="dropdown-menu ap-report-dropdown__panel js-ap-dropdown-panel" hidden>
                            <a class="dropdown-item ap-report-dropdown__link" href="#tlm-jump">К быстрому переходу</a>
                        </div>
                    </div>
                </div>
                <p class="section-card-lead" style="margin-top:8px">Каждая запись — состояние <strong>до</strong> сброса. Данные не удаляются при последующих действиях обучающегося.</p>
                <div class="section-card-divider" role="presentation"></div>
                <div class="section-card-body">
                @foreach (array_reverse($panel['instructor_resets'], true) as $idx => $r)
                    <details class="ap-report-audit-item">
                        <summary>
                            {{ $r['at'] ?? '—' }}
                            · <strong>{{ $r['step'] ?? '?' }}</strong>
                            @if (! empty($r['note']))<span class="muted"> — {{ $r['note'] }}</span>@endif
                        </summary>
                        <pre class="ap-report-pre">{{ json_encode($r['snapshot'] ?? $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @endforeach
                </div>
            </section>
        @endif

        <p class="muted" style="margin-top:4px;font-size:14px">
            <a href="{{ $learnerCardUrl }}">← Ко всем модулям обучающегося</a>
        </p>
    </div>
    </div>

    <button type="button" class="scroll-to-top" aria-label="Наверх">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 19V5M5 12l7-7 7 7"/>
        </svg>
    </button>
    <script>
        (function () {
            document.querySelectorAll('.js-ap-dropdown').forEach(function (root) {
                var btn = root.querySelector('.js-ap-dropdown-btn');
                var panel = root.querySelector('.js-ap-dropdown-panel');
                if (!btn || !panel) {
                    return;
                }
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var wasHidden = panel.hasAttribute('hidden');
                    document.querySelectorAll('.js-ap-dropdown').forEach(function (r) {
                        var p = r.querySelector('.js-ap-dropdown-panel');
                        var b = r.querySelector('.js-ap-dropdown-btn');
                        if (p) {
                            p.setAttribute('hidden', '');
                        }
                        if (b) {
                            b.setAttribute('aria-expanded', 'false');
                        }
                    });
                    if (wasHidden) {
                        panel.removeAttribute('hidden');
                        btn.setAttribute('aria-expanded', 'true');
                    }
                });
            });
            document.addEventListener('click', function () {
                document.querySelectorAll('.js-ap-dropdown-panel').forEach(function (p) {
                    p.setAttribute('hidden', '');
                });
                document.querySelectorAll('.js-ap-dropdown-btn').forEach(function (b) {
                    b.setAttribute('aria-expanded', 'false');
                });
            });
            document.querySelectorAll('.js-ap-dropdown-panel').forEach(function (panel) {
                panel.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            });
        })();
        document.querySelectorAll('[data-ap-attempt-tabs]').forEach(function (root) {
            var tabs = root.querySelectorAll('[data-ap-attempt-tab]');
            var panels = root.querySelectorAll('[data-ap-attempt-panel]');
            if (!tabs.length) {
                return;
            }
            function activate(idx) {
                tabs.forEach(function (btn) {
                    var on = btn.getAttribute('data-ap-attempt-tab') === String(idx);
                    btn.classList.toggle('is-active', on);
                    btn.setAttribute('aria-selected', on ? 'true' : 'false');
                    btn.tabIndex = on ? 0 : -1;
                });
                panels.forEach(function (panel) {
                    var on = panel.getAttribute('data-ap-attempt-panel') === String(idx);
                    panel.classList.toggle('is-active', on);
                    if (on) {
                        panel.removeAttribute('hidden');
                    } else {
                        panel.setAttribute('hidden', '');
                    }
                });
            }
            tabs.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activate(btn.getAttribute('data-ap-attempt-tab'));
                });
                btn.addEventListener('keydown', function (e) {
                    var i = Array.prototype.indexOf.call(tabs, btn);
                    if (e.key === 'ArrowRight' && i < tabs.length - 1) {
                        e.preventDefault();
                        tabs[i + 1].focus();
                        activate(tabs[i + 1].getAttribute('data-ap-attempt-tab'));
                    } else if (e.key === 'ArrowLeft' && i > 0) {
                        e.preventDefault();
                        tabs[i - 1].focus();
                        activate(tabs[i - 1].getAttribute('data-ap-attempt-tab'));
                    }
                });
            });
        });
        document.querySelectorAll('.js-ta-reset-form').forEach(function (f) {
            f.addEventListener('submit', function (e) {
                if (!confirm('Выполнить сброс для этого шага?')) {
                    e.preventDefault();
                }
            });
        });
        (function () {
            var scrollRoot = document.querySelector('.admin-content');
            var sections = document.querySelectorAll('[data-section]');
            var navBtns = document.querySelectorAll('.quick-nav-btn');
            if (sections.length && navBtns.length) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) {
                            return;
                        }
                        var active = document.querySelector(
                            '.quick-nav-btn[href="#' + entry.target.dataset.section + '"]'
                        );
                        if (!active) {
                            return;
                        }
                        navBtns.forEach(function (btn) {
                            btn.classList.remove('active');
                        });
                        active.classList.add('active');
                    });
                }, {
                    threshold: 0.3,
                    rootMargin: '-60px 0px 0px 0px',
                    root: scrollRoot || null,
                });
                sections.forEach(function (s) {
                    observer.observe(s);
                });
            }
            var scrollBtn = document.querySelector('.scroll-to-top');
            if (scrollBtn) {
                var onScroll = function () {
                    var y = scrollRoot ? scrollRoot.scrollTop : window.scrollY;
                    if (y > 400) {
                        scrollBtn.classList.add('visible');
                    } else {
                        scrollBtn.classList.remove('visible');
                    }
                };
                (scrollRoot || window).addEventListener('scroll', onScroll, { passive: true });
                onScroll();
                scrollBtn.addEventListener('click', function () {
                    if (scrollRoot) {
                        scrollRoot.scrollTo({ top: 0, behavior: 'smooth' });
                    } else {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            }
        })();
    </script>
@endsection
