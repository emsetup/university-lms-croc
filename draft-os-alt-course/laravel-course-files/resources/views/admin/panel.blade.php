@extends('layouts.course')

@section('title', 'Админ курса — панель')

@section('content')
    <div class="card" style="max-width: 960px; margin: 0 auto">
        @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'panel'])

        <h1 style="margin-top: 0">Панель администратора курса</h1>
        <p class="muted" style="margin-top: 0">
            Единое меню сверху: переход к содержимому курса (теория в Markdown, просмотр тестов и практик), к сводке по обучающимся и к странице входа для проверки со стороны студента.
        </p>
        <p class="muted small" style="margin: 0 0 1.25rem">
            Доступ по параметру <code>key</code> в URL — значение <code>TEACHER_REPORT_TOKEN</code> или <code>COURSE_ADMIN_TOKEN</code> из <code>.env</code> (см. <code>STAND.md</code>).
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem">
            <a class="card" href="{{ route('admin.theory.index', ['key' => $adminKey]) }}" style="display: block; text-decoration: none; padding: 1rem 1.1rem; border-radius: 12px; color: inherit; border: 1px solid var(--line, #dfe8e4); transition: box-shadow 0.15s, border-color 0.15s">
                <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem; color: var(--accent, #0a7)">Содержимое курса</h2>
                <p class="muted small" style="margin: 0">Теория модулей (редактор MD), просмотр тестов по теории, практики и итоговых экзаменов, выгрузка ZIP.</p>
            </a>
            <a class="card" href="{{ route('teacher.course-report', ['key' => $adminKey]) }}" style="display: block; text-decoration: none; padding: 1rem 1.1rem; border-radius: 12px; color: inherit; border: 1px solid var(--line, #dfe8e4); transition: box-shadow 0.15s, border-color 0.15s">
                <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem; color: var(--accent, #0a7)">Обучающиеся</h2>
                <p class="muted small" style="margin: 0">Сводка по курсу, карточки обучающихся, детализация по модулям, сброс попыток.</p>
            </a>
        </div>
    </div>
@endsection
