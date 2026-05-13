@extends('layouts.course')

@section('title', 'Админ портала — панель')

@section('content')
    <div class="card" style="max-width: 960px; margin: 0 auto">
        @include('partials.admin-instructor-nav', ['active' => 'panel'])

        <h1 style="margin-top: 0">Панель администратора портала</h1>
        <p class="muted" style="margin-top: 0">
            Сначала выберите курс в разделе «Курсы», затем редактируйте его содержимое, вопросы тестов, обучающихся и сертификаты (в зависимости от вашей роли).
        </p>
        <p class="muted small" style="margin: 0 0 1.25rem">
            Доступ к этому разделу только у сотрудников, назначенных в «Сотрудники». Вход — через корпоративный SSO на главной странице портала.
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem">
            <a class="card" href="{{ route('admin.courses.index') }}" style="display: block; text-decoration: none; padding: 1rem 1.1rem; border-radius: 12px; color: inherit; border: 1px solid var(--line, #dfe8e4); transition: box-shadow 0.15s, border-color 0.15s">
                <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem; color: var(--accent, #0a7)">Курсы</h2>
                <p class="muted small" style="margin: 0">Список программ. Выберите курс и перейдите к его настройкам и материалам.</p>
            </a>
            @if ($portalStaffAccess && $portalStaffAccess->canViewPortalLearners())
                <a class="card" href="{{ route('admin.learners.portal') }}" style="display: block; text-decoration: none; padding: 1rem 1.1rem; border-radius: 12px; color: inherit; border: 1px solid var(--line, #dfe8e4); transition: box-shadow 0.15s, border-color 0.15s">
                    <h2 style="margin: 0 0 0.35rem; font-size: 1.05rem; color: var(--accent, #0a7)">Обучающиеся</h2>
                    <p class="muted small" style="margin: 0">Сводка по всем курсам: сколько зачислено и сколько начали обучение.</p>
                </a>
            @endif
        </div>
    </div>
@endsection
