@extends('layouts.admin')

@section('title', 'Панель администратора — Трек знаний')

@push('scripts')
    <script src="{{ asset('js/admin-activity-panel.js') }}" defer></script>
    <script src="{{ asset('js/admin-dash-changelog.js') }}" defer></script>
@endpush

@section('content')
    @php
        $m = $dashMetrics ?? [];
        $animCourses = (int) ($m['courses_total'] ?? 0);
        $animLearners = (int) ($m['learners_enrolled'] ?? 0);
        $animPct = (int) ($m['completed_pct'] ?? 0);
        $animCerts = (int) ($m['certificates'] ?? 0);
    @endphp
    <div class="ap-page ap-fade">
        <h1 class="ap-page-title">Главная панель</h1>
        <p class="ap-page-lead">
            Сводка по порталу: курсы, обучающиеся и последняя активность.
        </p>

        <div class="ap-grid-metrics ap-dash-metrics">
            <div class="ap-metric">
                <p class="ap-metric__label-row">
                    @include('partials.ap-icon', ['name' => 'book', 'size' => 'lg'])
                    <span>Курсов</span>
                </p>
                <p class="ap-metric__value"><span class="js-ap-dash-num" data-ap-target="{{ $animCourses }}">0</span></p>
                <p class="ap-metric__hint">опубл: {{ (int) ($m['courses_published'] ?? 0) }}</p>
            </div>
            <div class="ap-metric">
                <p class="ap-metric__label-row">
                    @include('partials.ap-icon', ['name' => 'users', 'size' => 'lg'])
                    <span>Обучающихся</span>
                </p>
                <p class="ap-metric__value"><span class="js-ap-dash-num" data-ap-target="{{ $animLearners }}">0</span></p>
                <p class="ap-metric__hint">активных: {{ (int) ($m['learners_active'] ?? 0) }}</p>
            </div>
            <div class="ap-metric">
                <p class="ap-metric__label-row">
                    @include('partials.ap-icon', ['name' => 'check', 'size' => 'lg'])
                    <span>Завершили</span>
                </p>
                <p class="ap-metric__value">
                    <span class="js-ap-dash-num" data-ap-target="{{ $animPct }}" data-ap-suffix="%">0%</span>
                </p>
                <p class="ap-metric__hint">
                    с сертификатом: {{ (int) ($m['completed_cert_learners'] ?? 0) }}
                    @if (($m['learners_enrolled'] ?? 0) > 0)
                        из {{ (int) $m['learners_enrolled'] }}
                    @endif
                </p>
            </div>
            <div class="ap-metric">
                <p class="ap-metric__label-row">
                    @include('partials.ap-icon', ['name' => 'trophy', 'size' => 'lg'])
                    <span>Сертификатов</span>
                </p>
                <p class="ap-metric__value"><span class="js-ap-dash-num" data-ap-target="{{ $animCerts }}">0</span></p>
                <p class="ap-metric__hint">выдано</p>
            </div>
        </div>

        <div class="ap-two-col ap-dash-two-col">
            <section class="ap-card ap-dash-card">
                <div class="ap-dash-card__head">
                    <h2 class="ap-card__title ap-dash-card__title">Активность</h2>
                    <a class="ap-link-inline ap-dash-card__more" href="{{ route('admin.activity') }}">
                        Все события
                        @include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])
                    </a>
                </div>
                @include('admin.partials.activity-panel', [
                    'panelMode' => 'compact',
                    'activityFeedUrl' => route('admin.activity.feed'),
                    'initialItems' => ($dashActivity ?? collect())->all(),
                ])
            </section>

            <div class="ap-dash-col-stack">
                @include('admin.partials.changelog-panel', [
                    'changelogEntries' => $changelogEntries ?? [],
                ])

            <section class="ap-card ap-dash-card">
                <h2 class="ap-card__title">Курсы</h2>
                @if (($dashCoursesQuick ?? collect())->isEmpty())
                    <p class="ap-muted">Курсы не найдены.</p>
                @else
                    <ul class="ap-dash-course-list">
                        @foreach ($dashCoursesQuick as $row)
                            @php
                                /** @var \App\Models\Course $c */
                                $c = $row['course'];
                                $cid = (int) $c->id;
                                $editable = ($dashEditableCourseIds ?? null) === null || isset($dashEditableCourseIds[$cid]);
                                $canPublish = $editable && $portalStaffAccess && ! $portalStaffAccess->isCourseTester()
                                    && ! $c->is_published && ! $c->is_archived;
                            @endphp
                            <li class="ap-dash-course-list__item">
                                <div class="ap-dash-course-list__top">
                                    <strong class="ap-dash-course-list__title">{{ $c->title }}</strong>
                                    @if ($c->is_archived)
                                        <span class="ap-badge ap-badge--archive">Архив</span>
                                    @elseif ($c->is_published)
                                        <span class="ap-badge ap-badge--published">Опубликован</span>
                                    @else
                                        <span class="ap-badge ap-badge--draft">Черновик</span>
                                    @endif
                                </div>
                                <div class="ap-dash-course-list__meta">
                                    Участников: {{ (int) $row['enrolled'] }}
                                    · Завершили: {{ (int) $row['completed'] }}
                                </div>
                                <div class="ap-mini-progress" role="progressbar"
                                     aria-valuenow="{{ (int) $row['progress_pct'] }}" aria-valuemin="0" aria-valuemax="100"
                                     aria-label="Доля завершивших с сертификатом">
                                    <div class="ap-mini-progress__bar" style="width: {{ (int) $row['progress_pct'] }}%"></div>
                                </div>
                                <div class="ap-dash-course-list__actions">
                                    <a class="ap-btn ap-btn--secondary ap-btn--sm" href="{{ route('admin.courses.enter', ['course' => $cid]) }}">
                                        Управлять
                                        @include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])
                                    </a>
                                    @if ($canPublish)
                                        <form method="post" action="{{ route('admin.courses.publish', ['course' => $cid]) }}" style="margin:0;display:inline">
                                            @csrf
                                            <button type="submit" class="ap-btn ap-btn--primary ap-btn--sm">Опубликовать</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if (! empty($dashCanCreateCourse))
                    <div class="ap-dash-create-wrap">
                        <a class="ap-btn ap-btn--primary ap-btn--with-icon" href="{{ route('admin.courses.create') }}">
                            @include('partials.ap-icon', ['name' => 'plus', 'size' => 'sm'])
                            Создать курс
                        </a>
                    </div>
                @endif
            </section>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var duration = 600;
            function easeOutCubic(t) {
                return 1 - Math.pow(1 - t, 3);
            }
            function run() {
                var nodes = document.querySelectorAll('.js-ap-dash-num');
                var start = performance.now();
                function frame(now) {
                    var t = Math.min(1, (now - start) / duration);
                    var k = easeOutCubic(t);
                    for (var i = 0; i < nodes.length; i++) {
                        var el = nodes[i];
                        var target = parseInt(el.getAttribute('data-ap-target') || '0', 10);
                        var suffix = el.getAttribute('data-ap-suffix') || '';
                        var v = Math.round(k * target);
                        el.textContent = v + suffix;
                    }
                    if (t < 1) {
                        requestAnimationFrame(frame);
                    }
                }
                requestAnimationFrame(frame);
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run);
            } else {
                run();
            }
        })();
    </script>
@endsection
