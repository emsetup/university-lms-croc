@extends('layouts.admin')

@section('title', 'Настройки портала')

@section('content')
    <div class="ap-page ap-fade">
        <h1 class="ap-page-title">Настройки портала</h1>
        <p class="ap-page-lead">
            Заглушка обновления и просмотр интерфейса от лица обучающегося.
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
                    rel="noopener"
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
                        data-search-url="{{ route('admin.settings.learner-search') }}"
                    >
                    <ul id="ap-settings-learner-results" class="ap-settings-learner-results" hidden></ul>
                    <p id="ap-settings-learner-picked" class="muted small" style="margin:0.5rem 0 0" hidden></p>
                    <button type="submit" class="btn btn-primary" id="ap-settings-impersonate-submit" disabled style="margin-top:0.75rem">
                        Открыть портал в новой вкладке
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
.ap-settings-impersonation-active {
    padding: 0.85rem 1rem;
    border-radius: 8px;
    border: 1px solid #fcd34d;
    background: #fffbeb;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var form = document.getElementById('ap-settings-impersonate-form');
    if (!form) return;
    var input = document.getElementById('ap-settings-learner-q');
    var hidden = document.getElementById('ap-settings-learner-id');
    var list = document.getElementById('ap-settings-learner-results');
    var picked = document.getElementById('ap-settings-learner-picked');
    var submit = document.getElementById('ap-settings-impersonate-submit');
    var url = input.getAttribute('data-search-url');
    var timer = null;

    function clearPick() {
        hidden.value = '';
        picked.hidden = true;
        submit.disabled = true;
    }

    input.addEventListener('input', function () {
        clearPick();
        var q = input.value.trim();
        if (q.length < 2) {
            list.hidden = true;
            list.innerHTML = '';
            return;
        }
        clearTimeout(timer);
        timer = setTimeout(function () {
            fetch(url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    list.innerHTML = '';
                    (data.items || []).forEach(function (item) {
                        var li = document.createElement('li');
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.textContent = item.label;
                        btn.addEventListener('click', function () {
                            hidden.value = String(item.id);
                            picked.textContent = 'Выбран: ' + item.label;
                            picked.hidden = false;
                            list.hidden = true;
                            submit.disabled = false;
                        });
                        li.appendChild(btn);
                        list.appendChild(li);
                    });
                    list.hidden = list.children.length === 0;
                });
        }, 250);
    });
})();
</script>
@endpush
