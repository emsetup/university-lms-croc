<!DOCTYPE html>
<html class="ap-html" lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Панель администратора — Трек знаний')</title>
    <link rel="icon" type="image/png" href="{{ asset('croc-app-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/local-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/course.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-panel.css') }}">
    <link rel="stylesheet" href="{{ asset('static/admin/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/portal-typography.css') }}">
    @stack('styles')
    @stack('head')
</head>
<body>
    @php
        $psa = $portalStaffAccess ?? null;
        $dockerHref = route('admin.docker.library');
        $navCourses = request()->routeIs('admin.courses.*')
            || request()->routeIs('admin.theory.*')
            || request()->routeIs('admin.quiz.*')
            || request()->routeIs('admin.course.*')
            || request()->routeIs('admin.practice.*')
            || request()->routeIs('admin.certificates*');
        $navPeople = request()->routeIs('admin.learners.*');
        $navStaff = request()->routeIs('admin.staff.*');
        $navDocker = request()->routeIs('admin.docker.*') || request()->routeIs('admin.practice.*');
    @endphp

    <div
        class="admin-shell"
        data-ap-palette-search="{{ route('admin.command-palette.search') }}"
        data-ap-docker-url="{{ route('admin.docker.library') }}"
        data-ap-staff-url="{{ route('admin.staff.index') }}"
        data-ap-can-portal-learners="{{ $psa && $psa->canViewPortalLearners() ? '1' : '0' }}"
        data-ap-can-create-course="{{ $psa && $psa->canCreateCourses() ? '1' : '0' }}"
        data-ap-can-staff="{{ $psa && $psa->canManageStaff() ? '1' : '0' }}"
        data-ap-can-docker="{{ $psa && $psa->canUseCourseAdminTools() ? '1' : '0' }}"
    >
        <header class="admin-topbar" aria-label="Верхняя панель администратора">
            <a class="admin-topbar__logo" href="{{ route('admin.panel') }}">КРОК<span>· Панель администратора</span></a>
            <nav class="admin-topbar__nav" aria-label="Основные разделы">
                <a class="nav-item @if($navCourses) active @endif" href="{{ route('admin.courses.index') }}">Курсы</a>
                @if (\App\Support\AdminNavigation::canSeePortalLearners())
                    <a class="nav-item @if($navPeople) active @endif" href="{{ route('admin.learners.portal') }}">Обучающиеся</a>
                @endif
                @if (\App\Support\AdminNavigation::canSeeStaff())
                    <a class="nav-item @if($navStaff) active @endif" href="{{ route('admin.staff.index') }}">Сотрудники</a>
                @endif
                @if ($psa && $psa->canUseCourseAdminTools())
                    <a class="nav-item @if($navDocker) active @endif" href="{{ $dockerHref }}">Docker</a>
                @endif
            </nav>
            <div class="admin-topbar__spacer" aria-hidden="true"></div>
            <div class="admin-topbar__actions">
                @include('partials.admin-settings-menu')
                <button type="button" class="nav-cmd-btn ap-cmd-palette-trigger" id="ap-cmd-palette-trigger" title="Палитра команд">
                    @include('partials.ap-icon', ['name' => 'search', 'size' => 'sm'])
                    <span class="kbd" data-ap-kbd-palette>⌘K</span>
                </button>
                <a class="nav-cmd-btn" href="{{ route('documentation.index') }}">Документация</a>
                @include('partials.admin-logs-nav-link')
                <a class="nav-cmd-btn" href="{{ route('portal') }}">→ Портал</a>
                <form method="post" action="{{ route('logout', [], false) }}" style="margin:0;display:inline">
                    @csrf
                    <button type="submit" class="nav-cmd-btn">Выйти</button>
                </form>
            </div>
        </header>

        <div class="admin-body">
            @include('partials.staff-admin-preview-banner')
            @if (session('ok') || session('err'))
                <div class="admin-flash-stack">
                    @if (session('ok'))
                        <div class="admin-flash admin-flash--ok">{{ session('ok') }}</div>
                    @endif
                    @if (session('err'))
                        <div class="admin-flash admin-flash--err">{{ session('err') }}</div>
                    @endif
                </div>
            @endif

            @php
                $bc = $adminBreadcrumbs ?? [];
                $adminDeferBreadcrumb = ! empty($adminShowCourseChrome) && $adminCurrentCourse;
            @endphp
            @if (count($bc) > 0 && ! $adminDeferBreadcrumb)
                <div class="admin-breadcrumb-wrap">
                    @include('partials.admin-breadcrumbs')
                </div>
            @endif

            @if (! empty($adminShowCourseChrome) && $adminCurrentCourse)
                @php
                    $tab = $adminCourseTab ?? null;
                    $cslug = $adminCurrentCourse->slug;
                    $tp = ['adminCourse' => $cslug];
                    /** @var \App\Services\PortalStaffAccess|null $portalStaffAccess */
                    $psa = $portalStaffAccess ?? null;
                    $canTools = $psa && $psa->canUseCourseAdminTools();
                    $cid = (int) $adminCurrentCourse->id;
                    $canViewLearners = $psa && $psa->canViewCourseLearnerStats($cid);
                    $en = (int) \App\Models\CourseEnrollment::query()->where('course_id', $cid)->count();
                    $completed = (int) \App\Models\FinalLabResult::query()
                        ->where('course_id', $cid)
                        ->whereNotNull('completed_at')
                        ->count();
                @endphp
                <div class="ap-course-context">
                    <div class="ap-course-context__row">
                        <div class="ap-course-context__brand">
                            @include('partials.ap-icon', ['name' => 'book-open', 'size' => 'md'])
                            <span class="ap-course-context__title">{{ $adminCurrentCourse->title }}</span>
                            @if ($adminCurrentCourse->is_archived)
                                <span class="ap-badge ap-badge--archive">Архив</span>
                            @elseif ($adminCurrentCourse->is_published)
                                <span class="ap-badge ap-badge--published">Опубликован</span>
                            @else
                                <span class="ap-badge ap-badge--draft">Черновик</span>
                                @if ($psa && $cid > 0 && $psa->isCollaboratorOnCourse($cid))
                                    <span class="ap-badge ap-badge--draft">соавтор</span>
                                @endif
                            @endif
                        </div>
                        <a class="ap-course-context__back" href="{{ route('admin.courses.index') }}">← Курсы</a>
                    </div>
                    <p class="ap-course-context__meta">
                        slug: <code>{{ $adminCurrentCourse->slug }}</code>
                        · {{ $en }} участников
                        · {{ $completed }} завершили
                    </p>
                </div>
                @if ($tab !== null)
                    @include('partials.admin-course-tabs')
                @endif
            @endif

            @if (count($bc) > 0 && $adminDeferBreadcrumb)
                <div class="admin-breadcrumb-wrap">
                    @include('partials.admin-breadcrumbs')
                </div>
            @endif

            <main class="admin-content" id="admin-main-content">
                @yield('content')
            </main>
        </div>
    </div>

    @include('partials.admin-shell-tail')
    @stack('scripts')
</body>
</html>
