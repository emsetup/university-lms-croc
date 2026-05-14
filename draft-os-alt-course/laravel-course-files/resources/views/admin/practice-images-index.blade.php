@extends('layouts.admin')

@section('title', 'Практики: Docker-образы')

@section('content')
    @php($activeTab = (string) ($tab ?? 'built'))
    <div class="ap-wide-page practice-images-page">
        <div class="practice-page__head">
            <div>
                <h1 class="practice-page__title">Практики · Docker-образы</h1>
                <p class="ap-muted small u-m0">Библиотека образов (Dockerfile + <span class="mono">check.sh</span>), сборка на стенде через lab-daemon.</p>
            </div>
            <div class="actions-row">
                @if ($activeTab === 'library')
                    <a class="btn btn-primary" href="{{ route('admin.practice.images.create', ['key' => $adminKey]) }}">Создать образ</a>
                @endif
            </div>
        </div>

        <nav class="ap-content-subtabs" aria-label="Вкладки образов">
            <a class="ap-content-subtabs__a @if ($activeTab === 'built') is-active @endif"
               href="{{ route('admin.practice.images.index', ['key' => $adminKey, 'tab' => 'built']) }}">Собранные образы</a>
            <a class="ap-content-subtabs__a @if ($activeTab === 'library') is-active @endif"
               href="{{ route('admin.practice.images.index', ['key' => $adminKey, 'tab' => 'library']) }}">Библиотека</a>
        </nav>

        @if ($activeTab === 'built' && is_array($systemImages ?? null) && count($systemImages) > 0)
            <div class="admin-card u-mt-1">
                <div class="admin-card__title admin-card__title--sm">Системные (Alt)</div>
                <p class="admin-card__lead">Готовые образы курса через <span class="mono">config/practice_lab.php</span>. Чтобы редактировать рецепт — создайте копию в библиотеке.</p>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Модуль</th>
                                <th>Тег</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($systemImages as $sys)
                                @php($tag = (string) ($sys['docker_tag'] ?? ''))
                                @php($st = is_array($statsByTag[$tag] ?? null) ? $statsByTag[$tag] : null)
                                <tr>
                                    <td>
                                        <strong>{{ $sys['title'] ?? '—' }}</strong>
                                        <div class="admin-table__meta">
                                            key <span class="mono">{{ (int) ($sys['module_key'] ?? 0) }}</span>
                                            · шаблон <span class="mono">{{ $sys['template'] ?? '—' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-gray mono badge-mono-wrap">{{ $tag }}</span>
                                        @if ($st)
                                            <div class="admin-table__meta">
                                                Размер: <strong>{{ $st['size_human'] ?? '—' }}</strong>
                                                @if (! empty($st['layers_count']))
                                                    <span>· слоёв: <strong>{{ (int) $st['layers_count'] }}</strong></span>
                                                @endif
                                            </div>
                                        @endif
                                        <form method="post" action="{{ route('admin.practice.images.stats.refresh', ['key' => $adminKey]) }}" class="admin-inline-form u-mt-1">
                                            @csrf
                                            <input type="hidden" name="tag" value="{{ $tag }}">
                                            <input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
                                            <button type="submit" class="btn btn-secondary btn-sm">Проверить</button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="post" action="{{ route('admin.practice.images.system.copy', ['key' => $adminKey]) }}" class="admin-inline-form">
                                            @csrf
                                            <input type="hidden" name="template" value="{{ $sys['template'] ?? '' }}">
                                            <input type="hidden" name="title" value="{{ ($sys['title'] ?? 'Alt образ').' (копия)' }}">
                                            <input type="hidden" name="docker_tag" value="{{ $tag }}">
                                            <button type="submit" class="btn btn-ghost btn-sm">Создать копию в библиотеке</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($activeTab === 'library')
            <form method="get" action="{{ route('admin.practice.images.index') }}" class="filter-row">
                <input type="hidden" name="key" value="{{ $adminKey }}">
                <input type="hidden" name="tab" value="library">
                <input type="text" class="form-input practice-filter-q" name="q" value="{{ $q }}" placeholder="Поиск: название / slug / тег">
                <select class="form-select" name="built">
                    <option value="" @if ($built === '') selected @endif>Все</option>
                    <option value="1" @if ($built === '1') selected @endif>Собранные</option>
                    <option value="0" @if ($built === '0') selected @endif>Не собранные</option>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Фильтр</button>
            </form>
        @endif

        <div class="admin-card u-mt-1">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Образ</th>
                            <th>Тег</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $row)
                            @php($st = is_array($statsByTag[$row->docker_tag] ?? null) ? $statsByTag[$row->docker_tag] : null)
                            <tr>
                                <td>
                                    <strong>{{ $row->title }}</strong>
                                    <div class="admin-table__meta">
                                        slug <span class="mono">{{ $row->slug }}</span>
                                        · <span class="mono">{{ $row->base_template }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-gray mono badge-mono-wrap">{{ $row->docker_tag }}</span>
                                    @if ($st)
                                        <div class="admin-table__meta">
                                            Размер: <strong>{{ $st['size_human'] ?? '—' }}</strong>
                                            @if (! empty($st['layers_count']))
                                                <span>· слоёв: <strong>{{ (int) $st['layers_count'] }}</strong></span>
                                            @endif
                                        </div>
                                    @endif
                                    <form method="post" action="{{ route('admin.practice.images.stats.refresh', ['key' => $adminKey]) }}" class="admin-inline-form u-mt-1">
                                        @csrf
                                        <input type="hidden" name="tag" value="{{ $row->docker_tag }}">
                                        <input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">Проверить</button>
                                    </form>
                                </td>
                                <td>
                                    @if ($row->last_build_status === 'ok')
                                        <span class="badge badge-green">Собран</span>
                                    @elseif ($row->last_build_status === 'fail')
                                        <span class="badge badge-red">Ошибка</span>
                                    @elseif ($row->last_built_at)
                                        <span class="badge badge-yellow">Сборка</span>
                                    @else
                                        <span class="badge badge-gray">Не собран</span>
                                    @endif
                                    @if ($row->last_built_at)
                                        <div class="admin-table__meta">Посл. сборка: {{ $row->last_built_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="actions-row">
                                        <a class="icon-btn" href="{{ route('admin.practice.images.edit', ['id' => $row->id, 'key' => $adminKey]) }}" data-tip="Редактировать" aria-label="Редактировать">
                                            @include('partials.ap-icon', ['name' => 'pencil', 'size' => 'md'])
                                        </a>
                                        <form method="post" action="{{ route('admin.practice.images.build', ['id' => $row->id, 'key' => $adminKey]) }}" class="admin-inline-form">
                                            @csrf
                                            <button type="submit" class="icon-btn" data-tip="Собрать" aria-label="Собрать">
                                                @include('partials.ap-icon', ['name' => 'plus', 'size' => 'md'])
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('admin.practice.images.stats.refresh', ['key' => $adminKey]) }}" class="admin-inline-form">
                                            @csrf
                                            <input type="hidden" name="tag" value="{{ $row->docker_tag }}">
                                            <input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
                                            <button type="submit" class="icon-btn" data-tip="Проверить" aria-label="Проверить">
                                                @include('partials.ap-icon', ['name' => 'check', 'size' => 'md'])
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('admin.practice.images.export', ['id' => $row->id, 'key' => $adminKey]) }}" class="admin-inline-form">
                                            @csrf
                                            <button type="submit" class="icon-btn" data-tip="Экспорт tar" aria-label="Экспорт tar">
                                                @include('partials.ap-icon', ['name' => 'external', 'size' => 'md'])
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state" role="status">
                                        <p class="empty-state__title">Нет образов</p>
                                        <p class="empty-state__text">
                                            @if ($activeTab === 'built')
                                                Пока нет собранных образов из библиотеки. Переключитесь на «Библиотека», создайте образ и нажмите «Собрать».
                                            @else
                                                Пока нет образов. Создайте первый.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="u-mt-1">
            {{ $items->links() }}
        </div>
    </div>
@endsection
