@extends('layouts.admin')

@section('title', 'Курсы портала — Трек знаний')

@push('scripts')
    <script src="{{ asset('js/admin-course-catalog-stats.js') }}" defer></script>
@endpush

@section('content')
    <div class="ap-page ap-fade ap-catalog">
        <div class="ap-catalog__toolbar">
            <h1 class="ap-page-title ap-catalog__title">Курсы портала</h1>
            @if (! empty($canCreateCourse))
                <button type="button" class="btn btn-primary ap-catalog__create-btn" id="ap-open-create-course" aria-haspopup="dialog" aria-controls="ap-create-course-modal">
                    @include('partials.ap-icon', ['name' => 'plus', 'size' => 'sm'])
                    Создать курс
                </button>
            @endif
        </div>

        <div class="ap-catalog__search">
            <label class="ap-catalog__search-label" for="ap-course-search">Поиск</label>
            <input type="search" id="ap-course-search" class="ap-catalog__search-input" placeholder="Начните вводить название курса…" autocomplete="off">
        </div>

        <div class="ap-catalog__pills" role="tablist" aria-label="Фильтр по статусу">
            <button type="button" class="ap-pill ap-pill--active" data-ap-filter="all" role="tab" aria-selected="true">Все</button>
            <button type="button" class="ap-pill" data-ap-filter="published" role="tab" aria-selected="false">Опубликованные</button>
            <button type="button" class="ap-pill" data-ap-filter="draft" role="tab" aria-selected="false">Черновики</button>
            <button type="button" class="ap-pill" data-ap-filter="archive" role="tab" aria-selected="false">Архив</button>
        </div>

        @if ($errors->any() && (old('title') || old('slug')))
            <div class="admin-flash admin-flash--err ap-catalog__form-errors" role="alert">
                <strong>Не удалось создать курс.</strong>
                <ul style="margin:0.35rem 0 0;padding-left:1.1rem">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="ap-catalog__grid" id="ap-course-catalog-grid" data-ap-stats-url="{{ route('admin.courses.catalog-stats') }}">
            @forelse ($courses as $c)
                @php
                    $cid = (int) $c['id'];
                    $editable = ($editableCourseIds ?? null) === null || isset($editableCourseIds[$cid]);
                    $canTools = $portalStaffAccess && $portalStaffAccess->canUseCourseAdminTools();
                    $canViewStats = $portalStaffAccess && $portalStaffAccess->canViewCourseLearnerStats($cid);
                    $isInstructor = $portalStaffAccess && $portalStaffAccess->isInstructor();
                    $canPublish = $editable && $portalStaffAccess && ! $portalStaffAccess->isCourseTester()
                        && empty($c['is_published']) && empty($c['is_archived']);
                    $canPreviewCourse = $portalStaffAccess && $portalStaffAccess->canPreviewCourse($cid);
                    $bucket = ! empty($c['is_archived']) ? 'archive' : (! empty($c['is_published']) ? 'published' : 'draft');
                    $titleLower = mb_strtolower((string) $c['title'], 'UTF-8');
                    $enrolled = (int) $c['enrolled'];
                    $completed = (int) ($c['completed'] ?? 0);
                    $ratePct = (int) ($c['completed_rate_pct'] ?? 0);
                @endphp
                <article
                    class="ap-catalog-card"
                    style="--ap-stagger: {{ $loop->index }}"
                    data-course-id="{{ $cid }}"
                    data-ap-title="{{ e($titleLower) }}"
                    data-ap-bucket="{{ $bucket }}"
                    data-ap-visible="1"
                >
                    <div class="ap-catalog-card__head">
                        <span class="ap-catalog-card__icon" aria-hidden="true">
                            @include('partials.ap-icon', ['name' => 'book-open', 'size' => 'lg'])
                        </span>
                        <div class="ap-catalog-card__head-main">
                            <h2 class="ap-catalog-card__name" title="{{ $c['title'] }}">{{ $c['title'] }}</h2>
                            <div class="ap-catalog-card__status">
                                @if (! empty($c['is_archived']))
                                    <span class="ap-badge ap-badge--archive">Архив</span>
                                @elseif (! empty($c['is_published']))
                                    <span class="ap-badge ap-badge--published">Опубликован</span>
                                @else
                                    <span class="ap-badge ap-badge--draft">Черновик</span>
                                    @if (! empty($c['is_collaborator']))
                                        <span class="ap-badge ap-badge--draft">соавтор</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="ap-catalog-card__body">
                        <div class="ap-catalog-card__meta">
                            <p class="ap-catalog-card__slug" title="{{ $c['slug'] }}"><code>{{ $c['slug'] }}</code></p>
                            <div class="ap-catalog-card__creator">
                                @include('partials.staff-person-chip', [
                                    'staffId' => $c['creator_staff_id'] ?? null,
                                    'name' => $c['creator_name'] ?? '—',
                                    'email' => $c['creator_email'] ?? '',
                                    'initials' => $c['creator_initials'] ?? '—',
                                    'canLinkToProfile' => ! empty($canViewStaffProfiles),
                                    'size' => 'sm',
                                ])
                            </div>
                        </div>
                        <div class="ap-catalog-card__stats-block">
                            <p class="ap-catalog-card__stats">
                                Участников {{ $enrolled }}
                                · Завершили {{ $completed }} ({{ $ratePct }}%)
                            </p>
                            <div class="ap-catalog-card__progress-label">
                                Средний прогресс участников: <span class="js-ap-catalog-avg-label">—</span>%
                            </div>
                            <div class="ap-mini-progress ap-catalog-card__progress is-loading" role="progressbar"
                                 aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                                 aria-label="Средний прогресс по курсу">
                                <div class="ap-mini-progress__bar" style="width: 0"></div>
                            </div>
                        </div>
                    </div>
                    <div class="ap-catalog-card__actions">
                        <a
                            class="btn btn-primary ap-catalog-card__btn-primary"
                            href="{{ route('admin.courses.enter', ['course' => $cid, 'next' => $isInstructor ? 'learners' : 'content']) }}"
                        >
                            {{ $isInstructor ? 'Обучающиеся' : 'Управлять курсом' }}
                            @include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])
                        </a>
                        @if ($canPreviewCourse)
                            @include('partials.course-preview-launch', [
                                'previewUrl' => route('admin.course.preview', ['adminCourse' => $c['slug']]),
                                'previewLabel' => 'Предпросмотр',
                                'previewClass' => 'btn btn-ghost ap-catalog-card__publish',
                            ])
                        @endif
                        @if ($canPublish)
                            <form method="post" action="{{ route('admin.courses.publish', ['course' => $cid]) }}" class="ap-catalog-card__publish-form">
                                @csrf
                                <button type="submit" class="btn btn-ghost ap-catalog-card__publish">Опубликовать</button>
                            </form>
                        @elseif (! $canPreviewCourse)
                            <span class="ap-catalog-card__actions-spacer" aria-hidden="true"></span>
                        @endif
                    </div>
                </article>
            @empty
                <p class="ap-muted ap-catalog__empty">Нет курсов в доступном вам списке.</p>
            @endforelse
        </div>
    </div>

    @if (($errors->any() && (old('title') || old('slug'))) || ! empty($openCreateModal ?? false))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                @if ($errors->any() && (old('title') || old('slug')))
                    window.dispatchEvent(new CustomEvent('ap-open-create-course', { detail: { preserveForm: true } }));
                @else
                    window.dispatchEvent(new CustomEvent('ap-open-create-course'));
                @endif
            });
        </script>
    @endif

    <script>
        (function () {
            var grid = document.getElementById('ap-course-catalog-grid');
            var search = document.getElementById('ap-course-search');
            var pills = document.querySelectorAll('.ap-pill[data-ap-filter]');
            var currentFilter = 'all';

            function normalize(s) {
                return (s || '').toLowerCase().trim();
            }

            function cardMatchesFilter(card, filter) {
                var b = card.getAttribute('data-ap-bucket');
                // "Все" = все активные (без архива), чтобы архивные курсы не мешали.
                if (filter === 'all') return b !== 'archive';
                if (filter === 'published') return b === 'published';
                if (filter === 'draft') return b === 'draft';
                if (filter === 'archive') return b === 'archive';
                return true;
            }

            function applyFilters() {
                if (!grid) return;
                var q = normalize(search ? search.value : '');
                var cards = grid.querySelectorAll('.ap-catalog-card');
                cards.forEach(function (card) {
                    var title = card.getAttribute('data-ap-title') || '';
                    var okSearch = !q || title.indexOf(q) !== -1;
                    var okPill = cardMatchesFilter(card, currentFilter);
                    var show = okSearch && okPill;
                    card.style.display = show ? '' : 'none';
                    card.setAttribute('data-ap-visible', show ? '1' : '0');
                });
            }

            pills.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    currentFilter = btn.getAttribute('data-ap-filter') || 'all';
                    pills.forEach(function (p) {
                        var on = p === btn;
                        p.classList.toggle('ap-pill--active', on);
                        p.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    applyFilters();
                });
            });

            if (search) {
                search.addEventListener('input', applyFilters);
            }

            applyFilters();
        })();
    </script>
@endsection
