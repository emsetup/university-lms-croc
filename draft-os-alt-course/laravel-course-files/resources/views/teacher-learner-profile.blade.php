@extends('layouts.admin')

@php
    use App\Support\DurationFormat;
    use App\Support\LearnerDisplay;

    $maxPtsMod = \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE;
    $enrollment = $learner->courseEnrollments->sortBy('id')->first();
    $courseModel = ($forcedCourse ?? null)
        ?? $enrollment?->course
        ?? \App\Models\Course::query()->where('slug', 'alt-os-features')->first();
    $tp = $courseModel ? ['adminCourse' => $courseModel->slug] : [];
    $cid = $courseModel ? (int) $courseModel->id : 0;
    $modCount = \App\Services\CourseScoringService::moduleCount($cid > 0 ? $cid : null);
    $en = $cid > 0 ? (int) \App\Models\CourseEnrollment::query()->where('course_id', $cid)->count() : 0;
    $completed = $cid > 0
        ? (int) \App\Models\FinalLabResult::query()->where('course_id', $cid)->whereNotNull('completed_at')->count()
        : 0;
    $learnersListUrl = $tp !== [] ? route('admin.learners.course', $tp) : route('teacher.course-report');
    $modsDone = (int) ($summaryRow['modules_passed_count'] ?? 0);
    $gt = (int) ($summaryRow['grand_total'] ?? 0);
    $maxGt = (int) ($summaryRow['max_grand_total'] ?? 0);
    if ($maxGt < 1) {
        $maxGt = $modCount * \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE + \App\Services\CourseScoringService::MAX_FINAL_LAB_POINTS;
    }
    $gtPct = min(100, (int) round(100 * $gt / max(1, $maxGt)));
    $timeTotal = (int) ($summaryRow['time_tracked']['total'] ?? 0);
    $timeStr = DurationFormat::fromSeconds($timeTotal);

    $learnerFio = LearnerDisplay::portalDisplayName($learner);
    $initials = LearnerDisplay::initials((string) $learner->email, $learnerFio);
@endphp

@section('title', 'Обучающийся: '.($learnerFio !== '' ? $learnerFio : $learner->email))

@section('content')
    <script>
        document.body.dataset.apTeacherReport = '1';
        document.documentElement.classList.add('ap-smooth-anchor-scroll');
        document.querySelector('.admin-topbar__nav a.nav-item')?.classList.add('active');
    </script>

    <div class="learner-page-content">
    <div class="ap-report-bleed">
        @if ($courseModel)
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
                <span class="breadcrumb-current">{{ $learnerFio !== '' ? $learnerFio : $learner->email }}</span>
            </nav>
        </div>
    </div>

    <div class="ap-report-page ap-report-stack">
        <header class="card">
            <div class="ap-report-learner-head">
                <div class="ap-report-learner-head__main">
                    <div class="ap-report-avatar" aria-hidden="true">{{ $initials }}</div>
                    <div>
                        @if ($learnerFio !== '')
                            <p class="ap-report-learner-title">{{ $learnerFio }}</p>
                            <p class="ap-report-learner-email-sub">{{ $learner->email }}</p>
                        @else
                            <p class="ap-report-learner-title">{{ $learner->email }}</p>
                        @endif
                    </div>
                </div>
                <a class="btn btn-ghost btn-sm" href="{{ $learnersListUrl }}">← К списку</a>
            </div>
            <p class="ap-report-metrics-line">
                {{ $modsDone }} из {{ $modCount }} модулей
                &nbsp;·&nbsp;
                {{ $gt }} / {{ $maxGt }} баллов
                &nbsp;·&nbsp;
                {{ $timeStr }}
                &nbsp;·&nbsp;
                {{ $gtPct }}%
            </p>
            <div class="ap-report-progress-row">
                <div class="progress-track" style="flex:1;min-width:0" aria-label="Заполнение курса">
                    <div class="progress-fill" style="width:{{ $gtPct }}%"></div>
                </div>
                <span class="ap-report-progress-pct">{{ $gtPct }}%</span>
            </div>
            @if (isset($summaryRow['grand_total_percent']) || isset($summaryRow['module_points_percent']))
                <p class="ap-report-submetrics">
                    @if (isset($summaryRow['grand_total_percent']))
                        Доля курса: {{ $summaryRow['grand_total_percent'] }}%
                    @endif
                    @if (isset($summaryRow['grand_total_percent']) && isset($summaryRow['module_points_percent']))
                        &nbsp;·&nbsp;
                    @endif
                    @if (isset($summaryRow['module_points_percent']))
                        Баллы по модулям: {{ $summaryRow['module_points_percent'] }}%
                    @endif
                </p>
            @endif
        </header>

        <nav class="quick-nav" id="tl-jump" aria-label="Быстрый переход к модулю">
            <span class="quick-nav-label">К модулю</span>
            @foreach ($modulePanels as $pn)
                <a class="quick-nav-btn" href="#module-{{ $pn['module_id'] }}">М{{ $pn['module_id'] }}</a>
            @endforeach
        </nav>

        <section>
            <h2 class="ap-report-section-title">Модули</h2>
            <div class="ap-report-stack">
                @foreach ($modulePanels as $pn)
                    @php
                        $r = $pn['report'];
                        $pr = $pn['progress'];
                        $pts = is_array($r) ? (int) ($r['points'] ?? 0) : 0;
                        $tq = is_array($r) ? (int) ($r['theory_quiz_pct'] ?? 0) : 0;
                        $skipPr = is_array($r) && ! empty($r['skip_practice']);
                        $prc = is_array($r) && ! $skipPr ? (int) ($r['practice_pct'] ?? 0) : null;
                        $ex = is_array($r) ? (int) ($r['exam_pct'] ?? 0) : 0;
                        $sec = $pr
                            ? (int) ($pr->seconds_theory ?? 0) + (int) ($pr->seconds_theory_quiz ?? 0)
                                + (int) ($pr->seconds_practice ?? 0) + (int) ($pr->seconds_module_exam ?? 0)
                            : 0;
                        $idle = $pts === 0 && $tq === 0 && ($skipPr || $prc === 0) && $ex === 0 && $sec === 0;
                        $mid = (int) $pn['module_id'];
                        $modUrl = $tp !== []
                            ? route('admin.learners.course.learner.module', array_merge($tp, ['learner' => $learner->id, 'courseModule' => $mid]))
                            : route('teacher.course-report.learner.module', ['learner' => $learner->id, 'module' => $mid]);
                        $idxPanel = (int) ($pn['content_source_index'] ?? 1);
                        $skipPractice = \App\Support\CourseModuleMeta::shouldSkipPractice($idxPanel);
                        $exHistR = $pn['module_exam_history'] ?? [];
                        $canResetProgress = ($portalStaffAccess ?? null)?->canResetLearnerProgress() ?? false;
                        $canResetEx =
                            $canResetProgress
                            && $pr
                            && ((int) $pr->module_exam_attempts >= 1
                                || is_array($pr->module_exam_last_result)
                                || (is_array($exHistR) && count($exHistR) > 0));
                    @endphp
                    @if ($idle)
                        <article class="card module-surface" id="module-{{ $mid }}" data-section="module-{{ $mid }}">
                            <div class="module-card-header module-card-header--idle">
                                <div class="module-card-header__left">
                                    <div class="ap-report-mod-badges">
                                        <span class="module-num">{{ $mid }}</span>
                                        @if ($pn['letter'] !== '')
                                            <span class="module-letter">{{ mb_substr($pn['letter'], 0, 1) }}</span>
                                        @endif
                                    </div>
                                    <span class="module-card-title">{{ $pn['title'] }}</span>
                                </div>
                                <div class="module-card-header__idle-actions">
                                    <span class="module-card-idle-state">Не начат</span>
                                    <a class="btn-open-module-ghost" href="{{ $modUrl }}">Открыть →</a>
                                </div>
                            </div>
                        </article>
                    @else
                        <article class="card module-surface" id="module-{{ $mid }}" data-section="module-{{ $mid }}">
                            <div class="module-card-header">
                                <div class="module-card-header__left">
                                    <div class="ap-report-mod-badges">
                                        <span class="module-num">{{ $mid }}</span>
                                        @if ($pn['letter'] !== '')
                                            <span class="module-letter">{{ mb_substr($pn['letter'], 0, 1) }}</span>
                                        @endif
                                    </div>
                                    <h3 class="module-card-title">
                                        <a href="{{ $modUrl }}">{{ $pn['title'] }}</a>
                                    </h3>
                                </div>
                                <div class="module-card-time">{{ DurationFormat::fromSeconds($sec) }}</div>
                            </div>
                            <div class="module-card-body">
                                <div class="section-row">
                                    <span class="section-label">Теория</span>
                                    <div class="section-bar">
                                        <div class="progress-track-xs" aria-hidden="true">
                                            <div class="progress-fill-xs" data-value="{{ $tq }}"></div>
                                        </div>
                                    </div>
                                    <span class="section-pct">{{ $tq }}%</span>
                                    <div class="section-actions"></div>
                                </div>
                                <div class="section-row">
                                    <span class="section-label">Практика</span>
                                    @if ($skipPractice)
                                        <div class="section-bar muted" style="font-size:13px">Не предусмотрена</div>
                                        <span class="section-pct">—</span>
                                        <div class="section-actions"></div>
                                    @else
                                        <div class="section-bar">
                                            <div class="progress-track-xs" aria-hidden="true">
                                                <div class="progress-fill-xs" data-value="{{ (int) $prc }}"></div>
                                            </div>
                                        </div>
                                        <span class="section-pct">{{ (int) $prc }}%</span>
                                        <div class="section-actions"></div>
                                    @endif
                                </div>
                                <div class="section-row">
                                    <span class="section-label">Экзамен</span>
                                    <div class="section-bar">
                                        <div class="progress-track-xs" aria-hidden="true">
                                            <div class="progress-fill-xs" data-value="{{ $ex }}"></div>
                                        </div>
                                    </div>
                                    <span class="section-pct">{{ $ex }}%</span>
                                    <div class="section-actions">
                                        @if ($canResetEx)
                                            <a class="btn-reset" href="{{ $modUrl }}#ta-reset-ex">Сброс</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="module-card-footer module-card-footer--with-action">
                                <span>Итог модуля: {{ $pts }} / {{ $maxPtsMod }}</span>
                                <a class="btn-open-module" href="{{ $modUrl }}">Открыть модуль <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>
        </section>

        <section class="card">
            <h3 class="ap-report-final-title">Финальная лаборатория</h3>
            @php($final = $learner->finalLabResult)
            @if ($final)
                <p class="ap-report-sec-meta-line">
                    Попыток: {{ (int) $final->attempts }}
                    <span class="muted">&nbsp;·&nbsp;</span>
                    Лучший балл: {{ (int) $final->best_score }}%
                    <span class="muted">&nbsp;·&nbsp;</span>
                    {{ $final->passed ? 'Сдана' : 'Не сдана' }}
                    <span class="muted">&nbsp;·&nbsp;</span>
                    Завершена: {{ optional($final->completed_at)->format('d.m.Y H:i') ?: '—' }}
                </p>
                @if ($final->certificate_serial || $final->certificate_full_name)
                    <div class="ap-report-final-box">
                        <div class="muted" style="font-size:12px;margin-bottom:6px;font-weight:600">Сертификат</div>
                        <div class="ap-report-sec-meta-line" style="margin:0">
                            <span><strong>№</strong> {{ $final->certificate_serial ?: '—' }}</span>
                            <span class="muted">&nbsp;·&nbsp;</span>
                            <span><strong>ФИО</strong> {{ $final->certificate_full_name ?: '—' }}</span>
                            <span class="muted">&nbsp;·&nbsp;</span>
                            <span><strong>Выдан</strong> {{ optional($final->certificate_issued_at)->format('d.m.Y H:i') ?: '—' }}</span>
                        </div>
                    </div>
                @endif
            @else
                <p class="muted" style="margin:0;font-size:14px">Финальная лаборатория пока не начиналась.</p>
            @endif
        </section>
    </div>
    </div>
    <button type="button" class="scroll-to-top" aria-label="Наверх">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 19V5M5 12l7-7 7 7"/>
        </svg>
    </button>
    <script>
        document.querySelectorAll('.progress-fill-xs').forEach(function (el) {
            el.style.width = (el.dataset.value != null && el.dataset.value !== '' ? el.dataset.value : '0') + '%';
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
