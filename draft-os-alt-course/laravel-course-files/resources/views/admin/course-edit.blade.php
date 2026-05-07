@extends('layouts.course')

@section('title', ($mode ?? 'edit') === 'create' ? 'Админ: создать курс' : 'Админ: курс')

@section('content')
    @php
        $isCreate = ($mode ?? 'edit') === 'create';
        $c = $course;
    @endphp
    <div class="card" style="max-width:960px;margin:0 auto 1rem">
        @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'courses'])
        <p class="muted" style="margin:0 0 0.5rem"><a href="{{ route('admin.courses.index', ['key' => $adminKey]) }}">← Все курсы</a></p>
        <h1 style="margin:0 0 0.35rem">{{ $isCreate ? 'Создать курс' : 'Редактировать курс' }}</h1>
        <p class="muted" style="margin:0;line-height:1.5">Курс определяет набор модулей и прогресс обучающихся. Вместо удаления используется архив: архивные курсы видны только администраторам и скрыты от обучающихся.</p>
    </div>

    @if (session('ok'))
        <div class="card" style="max-width:960px;margin:0 auto 1rem;border-color:#b8dcc8;background:#f0faf5">{{ session('ok') }}</div>
    @endif
    @if (session('err'))
        <div class="card" style="max-width:960px;margin:0 auto 1rem;border-color:#f5c2c7;background:#fff5f5">{{ session('err') }}</div>
    @endif
    @if ($errors->any())
        <div class="card" style="max-width:960px;margin:0 auto 1rem;border-color:#f5c2c7;background:#fff5f5">
            <div style="font-weight:800;margin-bottom:0.35rem">Ошибки формы</div>
            <ul style="margin:0;padding-left:1.1rem">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="max-width:960px;margin:0 auto 1rem">
        <h2 style="margin-top:0">Параметры</h2>
        <form method="post" action="{{ $isCreate ? route('admin.courses.store', ['key' => $adminKey]) : route('admin.courses.update', ['course' => $c->id, 'key' => $adminKey]) }}" style="display:grid;gap:0.75rem;max-width:46rem">
            @csrf
            <div>
                <label class="muted small" style="display:block;margin-bottom:0.25rem">Slug</label>
                <input name="slug" type="text" required maxlength="80" value="{{ old('slug', $c?->slug) }}" placeholder="alt-os-features" class="btn btn-ghost" style="width:100%;text-align:left;padding:0.45rem 0.65rem;border:1px solid var(--line,#dfe8e4)">
                <div class="muted small" style="margin-top:0.25rem">Только латиница/цифры и дефис. Используется в интеграциях и миграциях.</div>
            </div>
            <div>
                <label class="muted small" style="display:block;margin-bottom:0.25rem">Название</label>
                <input name="title" type="text" required maxlength="200" value="{{ old('title', $c?->title) }}" class="btn btn-ghost" style="width:100%;text-align:left;padding:0.45rem 0.65rem;border:1px solid var(--line,#dfe8e4)">
            </div>
            <div>
                <label class="muted small" style="display:block;margin-bottom:0.25rem">Описание</label>
                <textarea name="summary" rows="3" maxlength="5000" class="btn btn-ghost" style="width:100%;text-align:left;padding:0.45rem 0.65rem;border:1px solid var(--line,#dfe8e4);resize:vertical">{{ old('summary', $c?->summary) }}</textarea>
            </div>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end">
                <div>
                    <label class="muted small" style="display:block;margin-bottom:0.25rem">Порядок (sort)</label>
                    <input name="sort" type="number" min="0" max="1000000" value="{{ old('sort', $c?->sort ?? 100) }}" class="btn btn-ghost" style="width:10rem;text-align:left;padding:0.45rem 0.65rem;border:1px solid var(--line,#dfe8e4)">
                </div>
                <div>
                    <label class="muted small" style="display:block;margin-bottom:0.25rem">Публикация</label>
                    @php
                        $pub = old('is_published', $c?->is_published ? '1' : '0');
                    @endphp
                    <select name="is_published" class="btn btn-ghost" style="padding:0.45rem 0.65rem">
                        <option value="1" @selected($pub === '1')>Опубликован</option>
                        <option value="0" @selected($pub === '0')>Черновик</option>
                    </select>
                </div>
                <div>
                    <label class="muted small" style="display:block;margin-bottom:0.25rem">Архив</label>
                    @php
                        $arch = old('is_archived', $c?->is_archived ? '1' : '0');
                    @endphp
                    <select name="is_archived" class="btn btn-ghost" style="padding:0.45rem 0.65rem">
                        <option value="0" @selected($arch === '0')>Нет</option>
                        <option value="1" @selected($arch === '1')>В архиве</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:0.25rem">
                <button type="submit" class="btn btn-primary">{{ $isCreate ? 'Создать' : 'Сохранить' }}</button>
                <a class="btn btn-ghost" href="{{ route('admin.courses.index', ['key' => $adminKey]) }}">Отмена</a>
            </div>
        </form>
    </div>

    @if (! $isCreate && isset($stats))
        <div class="card" style="max-width:960px;margin:0 auto 1rem">
            <h2 style="margin-top:0">Статистика</h2>
            <div class="muted">Участников: <strong>{{ (int) ($stats['enrolled'] ?? 0) }}</strong> · Завершили: <strong>{{ (int) ($stats['completed'] ?? 0) }}</strong></div>
            <div style="margin-top:0.75rem;display:flex;gap:0.5rem;flex-wrap:wrap">
                <a class="btn btn-ghost" href="{{ route('admin.courses.enter', ['course' => $c->id, 'key' => $adminKey, 'next' => 'content']) }}">Открыть “Содержимое”</a>
                <a class="btn btn-ghost" href="{{ route('admin.courses.enter', ['course' => $c->id, 'key' => $adminKey, 'next' => 'learners']) }}">Открыть “Обучающиеся”</a>
                <a class="btn btn-ghost" href="{{ route('admin.courses.enter', ['course' => $c->id, 'key' => $adminKey, 'next' => 'certificates']) }}">Открыть “Сертификаты”</a>
            </div>
        </div>

        <div class="card" style="max-width:960px;margin:0 auto;border-color:#fde68a;background:#fffbeb">
            <h2 style="margin-top:0;color:#92400e">Архив</h2>
            <p class="muted" style="margin:0 0 0.75rem;line-height:1.5">Архивный курс не отображается обучающимся в портале и недоступен для начала обучения. Администраторы могут восстановить курс в любой момент.</p>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                @if ($c->is_archived)
                    <form method="post" action="{{ route('admin.courses.unarchive', ['course' => $c->id, 'key' => $adminKey]) }}" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn-primary">Восстановить из архива</button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.courses.archive', ['course' => $c->id, 'key' => $adminKey]) }}" style="margin:0" onsubmit="return confirm('Перенести курс «{{ $c->title }}» в архив? Он пропадёт из портала обучающихся.');">
                        @csrf
                        <button type="submit" class="btn btn-ghost" style="color:#92400e">Перенести в архив</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection

