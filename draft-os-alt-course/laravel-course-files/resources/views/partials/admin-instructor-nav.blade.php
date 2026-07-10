{{-- Навигация панели /adm (OIDC + portal_staff) --}}
@php
    /** @var \App\Services\PortalStaffAccess|null $portalStaffAccess */
    $portalStaffAccess = $portalStaffAccess ?? null;
    $active = $active ?? '';
    $adminCourseTitle = (string) (session('admin_course_title') ?: '');
    $hasAdminCourse = (bool) session('admin_course_id');
    $isCoursePicker = request()->routeIs('admin.courses.index');
    $isPortalPanel = request()->routeIs('admin.panel') || $isCoursePicker || request()->routeIs('admin.learners.portal');
    $settingsNavActive = $active === 'settings'
        || request()->routeIs('admin.course.module.*')
        || request()->routeIs('admin.course.settings*');
    $canTools = $portalStaffAccess && $portalStaffAccess->canUseCourseAdminTools();
    $courseIdNav = (int) session('admin_course_id', 0);
    $canViewLearners = $portalStaffAccess && $courseIdNav > 0 && $portalStaffAccess->canViewCourseLearnerStats($courseIdNav);
    $canStaff = $portalStaffAccess && $portalStaffAccess->canManageStaff();
    $canPortalLearners = $portalStaffAccess && $portalStaffAccess->canViewPortalLearners();

    $apNav = \App\Support\AdminNavigation::adminCourseRouteParams();

    $currentLabel = '';
    if ($active === 'panel' || request()->routeIs('admin.panel')) {
        $currentLabel = 'Панель';
    } elseif ($active === 'staff' || request()->routeIs('admin.staff.*')) {
        $currentLabel = 'Сотрудники';
    } elseif ($active === 'courses' || $isCoursePicker || request()->routeIs('admin.courses.*')) {
        $currentLabel = 'Курсы';
    } elseif ($active === 'learners_portal' || request()->routeIs('admin.learners.portal')) {
        $currentLabel = 'Обучающиеся';
    } elseif ($active === 'practice' || request()->routeIs('admin.practice.*')) {
        $currentLabel = 'Практики';
    } elseif (request()->routeIs('admin.course.settings') || request()->routeIs('admin.course.module.*')) {
        $currentLabel = 'Модули';
    } elseif ($active === 'theory' || request()->routeIs('admin.theory.*') || request()->routeIs('admin.quiz.*')) {
        $currentLabel = 'Содержимое курса';
    } elseif ($active === 'learners_course' || request()->routeIs('admin.learners.course')) {
        $currentLabel = 'Обучающиеся курса';
    } elseif ($active === 'certificates' || request()->routeIs('admin.certificates*')) {
        $currentLabel = 'Сертификаты';
    }
@endphp
<nav class="ai-nav" aria-label="Панель администратора курса">
    <div class="ai-nav__inner">
        <div class="ai-nav__crumbs ai-nav__crumbs--top" aria-label="Хлебные крошки">
            <a class="ai-nav__crumb" href="{{ route('admin.panel') }}">Админ портала</a>
            @if ($hasAdminCourse && ! $isPortalPanel)
                <span class="ai-nav__sep">·</span>
                <a class="ai-nav__crumb" href="{{ route('admin.courses.index') }}">Курсы</a>
                <span class="ai-nav__sep">·</span>
                <a class="ai-nav__crumb" href="{{ ! empty($apNav) ? route('admin.theory.index', $apNav) : route('admin.courses.index') }}">{{ $adminCourseTitle }}</a>
                @if ($currentLabel !== '')
                    <span class="ai-nav__sep">·</span>
                    <span class="ai-nav__crumb ai-nav__crumb--current">{{ $currentLabel }}</span>
                @endif
            @else
                @if ($currentLabel !== '' && $currentLabel !== 'Панель')
                    <span class="ai-nav__sep">·</span>
                    <span class="ai-nav__crumb ai-nav__crumb--current">{{ $currentLabel }}</span>
                @endif
            @endif
        </div>
        <div class="ai-nav__links">
            <a href="{{ route('admin.panel') }}"
               class="ai-nav__a @if ($active === 'panel') ai-nav__a--active @endif">Панель</a>
            @if ($canStaff)
                <a href="{{ route('admin.staff.index') }}"
                   class="ai-nav__a @if ($active === 'staff') ai-nav__a--active @endif">Сотрудники</a>
            @endif
            <a href="{{ route('admin.courses.index') }}"
               class="ai-nav__a @if ($active === 'courses') ai-nav__a--active @endif">Курсы</a>
            @if ($isPortalPanel && $canPortalLearners)
                <a href="{{ route('admin.learners.portal') }}"
                   class="ai-nav__a @if ($active === 'learners_portal') ai-nav__a--active @endif">Обучающиеся</a>
            @endif
            @if ($hasAdminCourse && ! $isPortalPanel && $canTools && ! empty($apNav))
                <a href="{{ route('admin.course.settings', $apNav) }}"
                   class="ai-nav__a @if ($settingsNavActive) ai-nav__a--active @endif">Модули</a>
                <a href="{{ route('admin.practice.images.index', $apNav) }}"
                   class="ai-nav__a @if ($active === 'practice') ai-nav__a--active @endif">Практики</a>
                <a href="{{ route('admin.theory.index', $apNav) }}"
                   class="ai-nav__a @if ($active === 'theory') ai-nav__a--active @endif">Содержимое курса</a>
                <a href="{{ route('admin.learners.course', $apNav) }}"
                   class="ai-nav__a @if ($active === 'learners_course') ai-nav__a--active @endif">Обучающиеся курса</a>
                <a href="{{ route('admin.certificates', $apNav) }}"
                   class="ai-nav__a @if ($active === 'certificates') ai-nav__a--active @endif">Сертификаты</a>
            @elseif ($hasAdminCourse && ! $isPortalPanel && $canViewLearners && ! empty($apNav))
                <a href="{{ route('admin.learners.course', $apNav) }}"
                   class="ai-nav__a @if ($active === 'learners_course') ai-nav__a--active @endif">Обучающиеся курса</a>
            @elseif ($hasAdminCourse && ! $isPortalPanel && ! empty($apNav))
                <a href="{{ route('admin.theory.index', $apNav) }}"
                   class="ai-nav__a @if ($active === 'theory') ai-nav__a--active @endif">Содержимое курса</a>
            @endif
            <a href="{{ route('portal') }}" class="ai-nav__a ai-nav__a--external">К порталу</a>
        </div>
    </div>
</nav>
<style>
    .ai-nav {
        margin: 0 0 1.25rem;
        padding: 0.55rem 0.85rem;
        border-radius: 12px;
        border: 1px solid var(--line, #dfe8e4);
        background: linear-gradient(165deg, #f4faf7, #fff);
        box-shadow: 0 2px 12px rgba(15, 42, 30, 0.05);
    }
    .ai-nav__inner {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.65rem 1rem;
        justify-content: space-between;
    }
    .ai-nav__links {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 0.5rem;
        align-items: center;
    }
    .ai-nav__a {
        display: inline-block;
        padding: 0.4rem 0.75rem;
        border-radius: 8px;
        font-size: 0.88rem;
        font-weight: 600;
        text-decoration: none;
        color: var(--accent, #0a7);
        border: 1px solid transparent;
    }
    .ai-nav__a:hover {
        background: rgba(10, 119, 85, 0.08);
        text-decoration: none;
    }
    .ai-nav__a--active {
        background: rgba(10, 119, 85, 0.14);
        border-color: rgba(10, 119, 85, 0.35);
        color: var(--text, #0f172a);
    }
    .ai-nav__a--external {
        color: var(--muted, #5c6b76);
        font-weight: 500;
    }
    .ai-nav__a--external:hover {
        color: var(--accent, #0a7);
    }
    .ai-nav__crumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: center;
        font-size: 0.82rem;
        color: var(--muted, #5c6b76);
    }
    .ai-nav__crumbs--top {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--text, #0f172a);
        letter-spacing: 0.01em;
    }
    .ai-nav__crumb {
        color: inherit;
        text-decoration: none;
        border-bottom: 1px dashed transparent;
    }
    .ai-nav__crumb:hover {
        color: var(--accent, #0a7);
        border-bottom-color: rgba(10, 119, 85, 0.45);
        text-decoration: none;
    }
    .ai-nav__crumb--current {
        color: var(--text, #0f172a);
        font-weight: 700;
    }
    .ai-nav__sep {
        opacity: 0.65;
    }
</style>
