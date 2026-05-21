@extends('layouts.admin')

@section('title', 'Настройки портала')

@section('content')
    <div class="ap-page ap-fade">
        <h1 class="ap-page-title">Настройки портала</h1>
        <p class="ap-page-lead">
            Заглушка обновления, просмотр портала от лица обучающегося и просмотр админки с правами сотрудника.
        </p>

        @if ($canMaintenance)
            <section class="ap-card" style="margin-top:1.25rem">
                <h2 class="ap-card__title" style="margin:0 0 0.5rem">Заглушка «Портал обновляется»</h2>
                <p class="muted" style="margin:0 0 1rem;line-height:1.5">
                    Когда включена, вошедшие обучающиеся (не сотрудники) видят страницу обновления вместо курса.
                    @if ($maintenanceSource === 'runtime')
                        Сейчас действует <strong>настройка из админки</strong>.
                    @else
                        Сейчас используется значение из <code>.env</code> (<code>PORTAL_USER_MAINTENANCE</code>).
                    @endif
                </p>
                <p style="margin:0 0 1rem">
                    Статус:
                    @if ($maintenanceEnabled)
                        <span class="ap-badge ap-badge--draft">включена</span>
                    @else
                        <span class="ap-badge ap-badge--published">выключена</span>
                    @endif
                    · по умолчанию в .env:
                    {{ $maintenanceEnvDefault ? 'включена' : 'выключена' }}
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center">
                    <form method="post" action="{{ route('admin.settings.maintenance') }}" style="margin:0">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $maintenanceEnabled ? '0' : '1' }}">
                        <button type="submit" class="btn btn-primary">
                            {{ $maintenanceEnabled ? 'Выключить заглушку' : 'Включить заглушку' }}
                        </button>
                    </form>
                    @if ($maintenanceSource === 'runtime')
                        <form method="post" action="{{ route('admin.settings.maintenance.reset') }}" style="margin:0">
                            @csrf
                            <button type="submit" class="btn btn-ghost">Сбросить к .env</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        @if ($canImpersonate)
            <section class="ap-card" id="prosmotr" style="margin-top:1.25rem">
                <h2 class="ap-card__title" style="margin:0 0 0.5rem">Просмотр от лица обучающегося</h2>
                <p class="muted" style="margin:0 0 1rem;line-height:1.5">
                    Откроется <strong>новая вкладка</strong> с порталом выбранного пользователя (курсы, заглушка — как у него).
                    Ваша учётная запись в админке не меняется. Закройте вкладку просмотра или нажмите «Вернуться в админку» в её шапке.
                    Сотрудников портала выбрать нельзя.
                </p>
                <form
                    id="ap-settings-impersonate-form"
                    method="post"
                    action="{{ route('admin.settings.impersonate') }}"
                    class="ap-settings-impersonate-form"
                    target="_blank"
                    data-search-url="{{ route('admin.settings.learner-search') }}"
                >
                    @csrf
                    <input type="hidden" name="learner_id" id="ap-settings-learner-id" value="">
                    <label class="ap-field-label" for="ap-settings-learner-q">Обучающийся</label>
                    <input
                        type="search"
                        id="ap-settings-learner-q"
                        class="ap-input"
                        placeholder="Почта или имя (минимум 2 символа)"
                        autocomplete="off"
                    >
                    <ul id="ap-settings-learner-results" class="ap-settings-learner-results" hidden></ul>
                    <p id="ap-settings-learner-hint" class="ap-settings-learner-hint muted small" hidden></p>
                    <p id="ap-settings-learner-picked" class="muted small" style="margin:0.5rem 0 0" hidden></p>
                    <button type="submit" class="btn btn-primary" id="ap-settings-impersonate-submit" style="margin-top:0.75rem">
                        Открыть портал в новой вкладке
                    </button>
                </form>
            </section>
        @endif

        @if ($canPreviewStaffAdmin ?? false)
            <section class="ap-card" id="prosmotr-sotrudnik" style="margin-top:1.25rem">
                <h2 class="ap-card__title" style="margin:0 0 0.5rem">Просмотр админки от лица сотрудника</h2>
                <p class="muted" style="margin:0 0 1rem;line-height:1.5">
                    Откроется <strong>новая вкладка</strong> с панелью администратора так, как её видит выбранный сотрудник
                    (меню, курсы, доступные разделы). Ваша учётная запись не меняется; сохранение изменений в режиме просмотра отключено.
                    В списке только сотрудники портала.
                </p>
                <form
                    id="ap-settings-staff-preview-form"
                    method="post"
                    action="{{ route('admin.settings.staff-preview') }}"
                    class="ap-settings-impersonate-form"
                    target="_blank"
                    data-search-url="{{ route('admin.settings.staff-search') }}"
                >
                    @csrf
                    <input type="hidden" name="staff_learner_id" id="ap-settings-staff-learner-id" value="">
                    <label class="ap-field-label" for="ap-settings-staff-q">Сотрудник</label>
                    <input
                        type="search"
                        id="ap-settings-staff-q"
                        class="ap-input"
                        placeholder="Почта или имя (минимум 2 символа)"
                        autocomplete="off"
                    >
                    <ul id="ap-settings-staff-results" class="ap-settings-learner-results" hidden></ul>
                    <p id="ap-settings-staff-hint" class="ap-settings-learner-hint muted small" hidden></p>
                    <p id="ap-settings-staff-picked" class="muted small" style="margin:0.5rem 0 0" hidden></p>
                    <button type="submit" class="btn btn-primary" style="margin-top:0.75rem">
                        Открыть админку в новой вкладке
                    </button>
                </form>
            </section>
        @endif
    </div>
@endsection

@push('styles')
<style>
.ap-settings-impersonate-form { max-width: 28rem; }
.ap-settings-learner-results {
    list-style: none;
    margin: 0.35rem 0 0;
    padding: 0;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    background: #fff;
    max-height: 14rem;
    overflow-y: auto;
}
.ap-settings-learner-results li button {
    display: block;
    width: 100%;
    text-align: left;
    padding: 0.5rem 0.75rem;
    border: 0;
    background: transparent;
    font: inherit;
    cursor: pointer;
}
.ap-settings-learner-results li button:hover { background: #f1f5f9; }
.ap-settings-learner-hint--err { color: #b45309; }
.ap-settings-impersonation-active {
    padding: 0.85rem 1rem;
    border-radius: 8px;
    border: 1px solid #fcd34d;
    background: #fffbeb;
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/admin-settings-impersonate.js') }}" defer></script>
@if ($canPreviewStaffAdmin ?? false)
<script src="{{ asset('js/admin-settings-staff-preview.js') }}" defer></script>
@endif
@endpush
