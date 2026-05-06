@extends('layouts.course')

@section('title', 'Настройки раздела: '.$section->title)

@section('content')
    <div class="card" style="max-width:720px;margin:0 auto 1rem">
        @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'settings'])
        <p class="muted" style="margin:0 0 0.5rem"><a href="{{ route('admin.course.module.sections', ['courseModule' => $courseModule->id, 'key' => $adminKey]) }}">← Разделы модуля</a></p>
        <h1 style="margin:0">{{ $section->title }}</h1>
        <p class="muted small" style="margin:0.35rem 0 0">Тип: <strong>{{ $section->type }}</strong></p>
        <form method="post" action="{{ route('admin.course.module.sections.update', ['courseModule' => $courseModule->id, 'section' => $section->id, 'key' => $adminKey]) }}" style="margin-top:0.75rem;display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center">
            @csrf
            <label class="muted small" style="margin:0">Название:</label>
            <input type="text" name="title" value="{{ $section->title }}" maxlength="200" required class="btn btn-ghost" style="flex:1;min-width:12rem;padding:0.35rem 0.5rem;border:1px solid var(--line,#dfe8e4)">
            <button type="submit" class="btn btn-ghost">Переименовать</button>
        </form>
    </div>

    @if (session('ok'))
        <div class="card" style="max-width:720px;margin:0 auto 1rem;border-color:#b8dcc8;background:#f0faf5">{{ session('ok') }}</div>
    @endif

    <div class="card" style="max-width:720px;margin:0 auto">
        <h2 style="margin-top:0">Параметры</h2>
        <form method="post" action="{{ route('admin.course.module.section.settings.save', ['courseModule' => $courseModule->id, 'section' => $section->id, 'key' => $adminKey]) }}">
            @csrf

            @if ($section->type === 'text')
                <p class="muted small">Текст теории по-прежнему в Markdown (<code>config/snippets</code> / редактор «Содержимое курса»).</p>
                <label class="muted small" style="display:block;margin:0.75rem 0 0.25rem">Мин. время на странице (сек), 0 = не ограничивать</label>
                <input type="number" name="min_read_seconds" value="{{ (int) ($settings['min_read_seconds'] ?? 0) }}" min="0" max="86400" class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
                <label class="muted small" style="display:block;margin:0.75rem 0 0.25rem">Лимит времени на просмотр (мин), пусто = без лимита</label>
                <input type="number" name="time_limit_minutes" value="{{ $settings['time_limit_minutes'] ?? '' }}" min="0" max="600" placeholder="—" class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
            @elseif ($section->type === 'quiz')
                <label class="muted small" style="display:block;margin:0 0 0.25rem">Лимит времени на попытку (мин)</label>
                <input type="number" name="time_limit_minutes" value="{{ (int) ($settings['time_limit_minutes'] ?? 30) }}" min="1" max="600" required class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
                <label class="muted small" style="display:block;margin:0.75rem 0 0.25rem">Макс. число попыток (пусто = без лимита)</label>
                <input type="number" name="attempt_limit" value="{{ $settings['attempt_limit'] ?? '' }}" min="1" max="50" placeholder="∞" class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
                <label class="muted small" style="display:block;margin:0.75rem 0 0.25rem">Порог зачёта (%)</label>
                <input type="number" name="pass_percent" value="{{ (int) ($settings['pass_percent'] ?? 70) }}" min="1" max="100" required class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
                <label style="display:flex;align-items:center;gap:0.5rem;margin:0.75rem 0 0">
                    <input type="hidden" name="shuffle" value="0">
                    <input type="checkbox" name="shuffle" value="1" @checked(!empty($settings['shuffle']))>
                    <span class="muted small">Перемешивать вопросы при каждой попытке</span>
                </label>
                <p class="muted small" style="margin:0.75rem 0 0.25rem">Штраф к сырому % (п.п.) по номеру попытки:</p>
                @php $pen = is_array($settings['penalties'] ?? null) ? $settings['penalties'] : []; @endphp
                <div style="display:flex;flex-wrap:wrap;gap:0.75rem">
                    <div><span class="muted small">2-я</span><br><input type="number" name="penalty_attempt_2" value="{{ $pen['2'] ?? '' }}" min="0" max="100" placeholder="10" class="btn btn-ghost" style="width:5rem;padding:0.35rem;border:1px solid var(--line,#dfe8e4)"></div>
                    <div><span class="muted small">3-я</span><br><input type="number" name="penalty_attempt_3" value="{{ $pen['3'] ?? '' }}" min="0" max="100" placeholder="—" class="btn btn-ghost" style="width:5rem;padding:0.35rem;border:1px solid var(--line,#dfe8e4)"></div>
                    <div><span class="muted small">4-я</span><br><input type="number" name="penalty_attempt_4" value="{{ $pen['4'] ?? '' }}" min="0" max="100" placeholder="—" class="btn btn-ghost" style="width:5rem;padding:0.35rem;border:1px solid var(--line,#dfe8e4)"></div>
                </div>
            @elseif ($section->type === 'practice')
                <p class="muted small">Содержимое практики — в Markdown и Docker-образах (как раньше). Здесь — опциональные лимиты.</p>
                <label class="muted small" style="display:block;margin:0.75rem 0 0.25rem">Макс. попыток (пусто = без лимита)</label>
                <input type="number" name="attempt_limit" value="{{ $settings['attempt_limit'] ?? '' }}" min="1" max="50" placeholder="—" class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
                <label class="muted small" style="display:block;margin:0.75rem 0 0.25rem">Лимит времени (мин), пусто = нет</label>
                <input type="number" name="time_limit_minutes" value="{{ $settings['time_limit_minutes'] ?? '' }}" min="0" max="10080" placeholder="—" class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
            @elseif ($section->type === 'exam')
                <label class="muted small" style="display:block;margin:0 0 0.25rem">Лимит времени на попытку (мин), если не задан в модуле</label>
                <input type="number" name="time_limit_minutes" value="{{ (int) ($settings['time_limit_minutes'] ?? 60) }}" min="1" max="600" required class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
                <label class="muted small" style="display:block;margin:0.75rem 0 0.25rem">Число попыток</label>
                <input type="number" name="attempt_limit" value="{{ (int) ($settings['attempt_limit'] ?? 2) }}" min="1" max="20" required class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
                <label class="muted small" style="display:block;margin:0.75rem 0 0.25rem">Порог зачёта (%)</label>
                <input type="number" name="pass_percent" value="{{ (int) ($settings['pass_percent'] ?? 70) }}" min="1" max="100" required class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
                <label class="muted small" style="display:block;margin:0.75rem 0 0.25rem">Минут видимости разбора после попытки</label>
                <input type="number" name="breakdown_visible_minutes" value="{{ (int) ($settings['breakdown_visible_minutes'] ?? 30) }}" min="0" max="10080" class="btn btn-ghost" style="width:100%;max-width:12rem;text-align:left;padding:0.45rem;border:1px solid var(--line,#dfe8e4)">
                <label style="display:flex;align-items:center;gap:0.5rem;margin:0.75rem 0 0">
                    <input type="hidden" name="one_by_one" value="0">
                    <input type="checkbox" name="one_by_one" value="1" @checked(($settings['one_by_one'] ?? true) !== false)>
                    <span class="muted small">Вопросы по одному (пошаговый интерфейс экзамена)</span>
                </label>
                @php $pen = is_array($settings['penalties'] ?? null) ? $settings['penalties'] : []; @endphp
                <p class="muted small" style="margin:0.75rem 0 0.25rem">Штраф к сырому % (п.п.) по номеру попытки:</p>
                <div style="display:flex;flex-wrap:wrap;gap:0.75rem">
                    <div><span class="muted small">2-я</span><br><input type="number" name="penalty_attempt_2" value="{{ $pen['2'] ?? '' }}" min="0" max="100" placeholder="10" class="btn btn-ghost" style="width:5rem;padding:0.35rem;border:1px solid var(--line,#dfe8e4)"></div>
                    <div><span class="muted small">3-я</span><br><input type="number" name="penalty_attempt_3" value="{{ $pen['3'] ?? '' }}" min="0" max="100" placeholder="—" class="btn btn-ghost" style="width:5rem;padding:0.35rem;border:1px solid var(--line,#dfe8e4)"></div>
                    <div><span class="muted small">4-я</span><br><input type="number" name="penalty_attempt_4" value="{{ $pen['4'] ?? '' }}" min="0" max="100" placeholder="—" class="btn btn-ghost" style="width:5rem;padding:0.35rem;border:1px solid var(--line,#dfe8e4)"></div>
                </div>
            @endif

            <div style="margin-top:1.25rem">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a class="btn btn-ghost" href="{{ route('admin.course.module.sections', ['courseModule' => $courseModule->id, 'key' => $adminKey]) }}">Отмена</a>
            </div>
        </form>
    </div>
@endsection
