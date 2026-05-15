<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Курс ОС «Альт»')</title>
    <link rel="icon" type="image/png" href="{{ asset('croc-app-icon.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('croc-app-icon-512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('croc-app-icon-512.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/course.css') }}">
    @if (request()->routeIs('admin.*'))
        <link rel="stylesheet" href="{{ asset('css/admin-panel.css') }}">
        <link rel="stylesheet" href="{{ asset('static/admin/admin.css') }}">
    @endif
    <style id="quiz-theory-console">
        /* Тесты: переносы и фрагменты конфигурации в тексте вопроса */
        .quiz-q-text,
        .quiz-q > div:first-child { white-space: pre-wrap; line-height: 1.45; }
        .quiz-q-text code,
        .quiz-q > div:first-child code,
        .quiz-q-text .mono,
        .quiz-q > div:first-child .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.92em; background: #f1f5f4; padding: 0.12em 0.35em; border-radius: 4px; }
        /* Markdown в тексте вопросов (итоговый тест, тест по теории) */
        .module-exam-q--md,
        .quiz-q-text--md { line-height: 1.5; font-weight: 600; }
        .module-exam-q--md > :first-child,
        .quiz-q-text--md > :first-child { margin-top: 0; }
        .module-exam-q--md p,
        .quiz-q-text--md p { margin: 0.4rem 0; font-weight: 600; }
        .module-exam-q--md pre,
        .quiz-q-text--md pre {
            font-weight: 400;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.88rem;
            line-height: 1.45;
            margin: 0.5rem 0 0.65rem;
            padding: 0.65rem 0.85rem;
            overflow-x: auto;
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 6px;
            white-space: pre;
        }
        .module-exam-q--md pre code,
        .quiz-q-text--md pre code {
            font-size: inherit;
            background: transparent;
            padding: 0;
            color: inherit;
        }
        .module-exam-q--md code:not(pre code),
        .quiz-q-text--md code:not(pre code) {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.9em;
            background: #f1f5f4;
            padding: 0.1em 0.35em;
            border-radius: 4px;
        }
        .quiz-q-num { margin-right: 0.25rem; }
        /* Итоговый тест: сопоставление перетаскиванием */
        .module-exam-match {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr);
            gap: 0.75rem 1rem;
            margin: 0.75rem 0 0.25rem;
            align-items: start;
        }
        @media (max-width: 720px) {
            .module-exam-match { grid-template-columns: 1fr; }
        }
        .module-exam-match__left { display: flex; flex-direction: column; gap: 0.45rem; }
        .module-exam-match__row {
            display: flex; align-items: stretch; gap: 0.45rem;
            min-height: 2.85rem;
        }
        .module-exam-match__ln {
            flex: 0 0 1.5rem; text-align: right; font-size: 0.78rem; font-weight: 700; color: #64748b; padding-top: 0.35rem;
        }
        .module-exam-match__cell {
            flex: 1; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.82rem; line-height: 1.35; padding: 0.45rem 0.55rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center;
        }
        .module-exam-match__right { min-width: 0; }
        .module-exam-match__list {
            list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.45rem;
        }
        .module-exam-match__card {
            cursor: grab; user-select: none;
            padding: 0.5rem 0.65rem; border-radius: 6px; border: 1px solid #c5d5c8; background: linear-gradient(180deg, #f4fff6 0%, #e8f5eb 100%);
            font-size: 0.88rem; line-height: 1.35; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        .module-exam-match__card:active { cursor: grabbing; }
        .module-exam-match__card.module-exam-match__card--drag { opacity: 0.55; }
    </style>
    <style id="course-catalog-cards">
        /* Портал, личный кабинет, админ: сетка курсов без растягивания по высоте строки */
        .module-grid.courses-catalog-grid {
            align-items: start;
        }
        /* Краткое описание на карточке курса (полный текст — в модалке / форме редактирования) */
        .course-card__description {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }
    </style>
    <style id="quiz-dialog-modals">
        dialog.quiz-modal {
            border: none;
            padding: 0;
            margin: 0;
            max-width: calc(100vw - 1.5rem);
            width: min(28rem, 100%);
            background: transparent;
            color: #0f172a;
        }
        dialog.quiz-modal::backdrop {
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(3px);
        }
        dialog.quiz-modal .quiz-modal-inner {
            background: #fff;
            border-radius: 14px;
            padding: 1.2rem 1.35rem 1.15rem;
            box-shadow: 0 24px 64px rgba(15, 23, 42, 0.2);
            border: 1px solid #e2e8f0;
        }
        dialog.quiz-modal .quiz-modal-heading {
            margin: 0 0 0.5rem;
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.3;
            color: #0f172a;
        }
        dialog.quiz-modal .quiz-modal-badge {
            display: inline-block;
            margin: 0 0 0.45rem;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #bbf7d0;
        }
        dialog.quiz-modal .quiz-modal-form {
            margin-top: 0.85rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        dialog.quiz-modal .quiz-modal-actions {
            margin-top: 0.85rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: flex-end;
        }
        dialog.quiz-modal .quiz-modal-list {
            margin: 0.35rem 0 0;
            padding-left: 1.1rem;
            line-height: 1.5;
            font-size: 0.92rem;
            color: #334155;
        }
        dialog.quiz-modal .quiz-modal-warn {
            color: #9a3412;
        }
        dialog.quiz-modal .quiz-modal-cancel {
            font-size: 0.88rem;
            color: #64748b;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        dialog.quiz-modal .quiz-modal-cancel:hover {
            color: #0f172a;
        }
    </style>
</head>
<body class="course-ui">
@php
    $isPortalUi = request()->routeIs('portal') || request()->routeIs('login');
    $isAdminUi = request()->routeIs('admin.*');
    $hasCourse = (bool) session('course_id');
    $hasAdminCourse = (bool) session('admin_course_id');
    if ($isAdminUi) {
        if ($hasAdminCourse) {
            $brandKicker = 'Учебный курс';
            $brandTitle = (string) (session('admin_course_title') ?: 'Курс');
        } else {
            $brandKicker = 'Трек знаний';
            $brandTitle = 'Панель администратора';
        }
    } elseif ($isPortalUi) {
        $brandTitle = 'Образовательный портал';
        $brandKicker = 'Трек знаний';
    } else {
        $brandTitle = session('course_title') ?: 'Образовательный портал';
        $brandKicker = $hasCourse ? 'Учебный курс' : 'Трек знаний';
    }
    $psaChrome = $portalStaffAccess ?? null;
@endphp
<div
    class="course-shell"
    @if ($isAdminUi)
        data-ap-palette-search="{{ route('admin.command-palette.search') }}"
        data-ap-docker-url="{{ route('admin.docker.library') }}"
        data-ap-staff-url="{{ route('admin.staff.index') }}"
        data-ap-can-portal-learners="{{ $psaChrome && $psaChrome->canViewPortalLearners() ? '1' : '0' }}"
        data-ap-can-create-course="{{ $psaChrome && $psaChrome->canCreateCourses() ? '1' : '0' }}"
        data-ap-can-staff="{{ $psaChrome && $psaChrome->canManageStaff() ? '1' : '0' }}"
        data-ap-can-docker="{{ $psaChrome && ! $psaChrome->isCourseTester() ? '1' : '0' }}"
    @endif
>
    @if (! $isAdminUi)
        <div class="portal-site-column">
    @endif
    <header class="course-header @if ($isAdminUi) course-header--admin @else course-header--public @endif">
        <a class="brand" href="{{ route('portal') }}">
            <span class="brand-mark">
                <img class="brand-wordmark" src="{{ asset('croc-wordmark.svg') }}" alt="КРОК">
            </span>
            <span class="brand-text">
                <span class="brand-kicker">{{ $brandKicker }}</span>
                <span class="brand-title">{{ $brandTitle }}</span>
            </span>
        </a>
        @if (session('learner_id'))
            <div class="course-header__actions">
                <a class="btn btn-ghost" href="{{ route('portal') }}">Курсы</a>
                @if (! empty($portalStaffAccess))
                    <a class="btn btn-ghost" href="{{ route('admin.panel') }}">Управление</a>
                @endif
                <a class="btn btn-ghost" href="{{ route('account') }}">Личный кабинет</a>
                @if (! $isAdminUi && ! $isPortalUi && $hasCourse)
                    <a class="btn btn-ghost" href="{{ route('course.dashboard', ['course' => (int) session('course_id')]) }}">Модули</a>
                @endif
                <form method="post" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Выйти</button>
                </form>
            </div>
        @endif
    </header>
    @if (session('ok'))
        <div class="flash ok">{{ session('ok') }}</div>
    @endif
    @if (session('err'))
        <div class="flash err">{{ session('err') }}</div>
    @endif
    @if ($isAdminUi && ! empty($adminBreadcrumbs) && count($adminBreadcrumbs))
        <div class="admin-breadcrumb-wrap" style="max-width:1100px;margin:0 auto;padding:0.5rem 1rem 0">
            @include('partials.admin-breadcrumbs')
        </div>
    @endif
    @yield('content')
    @if (! $isAdminUi)
        </div>
    @endif
</div>
@if ($isAdminUi)
    @include('partials.admin-shell-tail')
@endif
<script>
(function () {
    function promoteQuizModalsToModalLayer() {
        document.querySelectorAll('dialog.quiz-modal[open]').forEach(function (d) {
            if (typeof d.showModal !== 'function') {
                return;
            }
            d.removeAttribute('open');
            try {
                d.showModal();
            } catch (e) {
                d.setAttribute('open', '');
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', promoteQuizModalsToModalLayer);
    } else {
        promoteQuizModalsToModalLayer();
    }
})();
</script>
</body>
</html>
