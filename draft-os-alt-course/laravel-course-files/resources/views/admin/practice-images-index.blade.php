@extends('layouts.course')

@section('title', 'Практики: Docker-образы')

@section('content')
    @include('partials.admin-instructor-nav', ['active' => 'practice'])

    <div class="card">
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between">
            <div>
                <h1 style="margin:0">Практики · Docker-образы</h1>
                <p class="muted" style="margin:0.25rem 0 0">Библиотека образов (Dockerfile + <code>check.sh</code>), сборка на стенде через lab-daemon.</p>
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
                @if (($tab ?? 'built') === 'library')
                    <a class="btn btn-primary" href="{{ route('admin.practice.images.create') }}">Создать образ</a>
                @endif
            </div>
        </div>

        @php($activeTab = (string) ($tab ?? 'built'))
        <div style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
            <a class="btn {{ $activeTab === 'built' ? 'btn-primary' : 'btn-ghost' }}"
               href="{{ route('admin.practice.images.index', ['tab' => 'built']) }}">Собранные образы</a>
            <a class="btn {{ $activeTab === 'library' ? 'btn-primary' : 'btn-ghost' }}"
               href="{{ route('admin.practice.images.index', ['tab' => 'library']) }}">Библиотека</a>
        </div>

        @if ($activeTab === 'built' && is_array($systemImages ?? null) && count($systemImages) > 0)
            <div style="margin-top:1rem;padding:0.85rem 0.9rem;border:1px solid var(--line,#e5e7eb);border-radius:12px;background:linear-gradient(165deg,#f4faf7,#fff)">
                <div style="display:flex;flex-wrap:wrap;gap:0.65rem;align-items:center;justify-content:space-between">
                    <div>
                        <div style="font-weight:800">Системные (Alt)</div>
                        <div class="muted small">Это уже готовые образы, которые используются курсом через <code>config/practice_lab.php</code>. Чтобы сделать редактируемый рецепт — создайте копию в библиотеке.</div>
                    </div>
                </div>
                <div style="margin-top:0.65rem;overflow:auto">
                    <table class="teacher-report-table" style="min-width:900px">
                        <thead>
                        <tr>
                            <th>Модуль</th>
                            <th>Тег</th>
                            <th style="width:1%">Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($systemImages as $sys)
                            @php($tag = (string) ($sys['docker_tag'] ?? ''))
                            @php($st = is_array($statsByTag[$tag] ?? null) ? $statsByTag[$tag] : null)
                            <tr>
                                <td style="vertical-align:top">
                                    <div style="font-weight:700">{{ $sys['title'] ?? '—' }}</div>
                                    <div class="muted small">key: <code>{{ (int) ($sys['module_key'] ?? 0) }}</code> · шаблон: <code>{{ $sys['template'] ?? '—' }}</code></div>
                                </td>
                                <td style="vertical-align:top">
                                    <code style="word-break:break-all">{{ $tag }}</code>
                                    @if ($st)
                                        <div class="muted small" style="margin-top:0.25rem;line-height:1.35">
                                            Размер: <strong>{{ $st['size_human'] ?? '—' }}</strong>
                                            @if (! empty($st['layers_count']))
                                                <span>· слоёв: <strong>{{ (int) $st['layers_count'] }}</strong></span>
                                            @endif
                                        </div>
                                    @endif
                                    <form method="post" action="{{ route('admin.practice.images.stats.refresh') }}" style="margin-top:0.35rem">
                                        @csrf
                                        <input type="hidden" name="tag" value="{{ $tag }}">
                                        <input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
                                        <button type="submit" class="btn btn-ghost" style="padding:0.25rem 0.55rem;font-size:0.82rem">Проверить</button>
                                    </form>
                                </td>
                                <td class="teacher-report-nowrap" style="vertical-align:top">
                                    <form method="post" action="{{ route('admin.practice.images.system.copy') }}" style="margin:0">
                                        @csrf
                                        <input type="hidden" name="template" value="{{ $sys['template'] ?? '' }}">
                                        <input type="hidden" name="title" value="{{ ($sys['title'] ?? 'Alt образ').' (копия)' }}">
                                        <input type="hidden" name="docker_tag" value="{{ $tag }}">
                                        <button type="submit" class="btn btn-ghost">Создать копию в библиотеке</button>
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
            <form method="get" action="{{ route('admin.practice.images.index') }}" style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center">
                <input type="hidden" name="tab" value="library">
                <input type="text" class="input" name="q" value="{{ $q }}" placeholder="Поиск: название / slug / тег" style="min-width:18rem">
                <select class="input" name="built">
                    <option value="" @selected($built === '')>Все</option>
                    <option value="1" @selected($built === '1')>Собранные</option>
                    <option value="0" @selected($built === '0')>Не собранные</option>
                </select>
                <button type="submit" class="btn btn-ghost">Фильтр</button>
            </form>
        @endif

        <div style="margin-top:1rem;overflow:auto">
            <table class="teacher-report-table" style="min-width:900px">
                <thead>
                <tr>
                    <th>Образ</th>
                    <th>Тег</th>
                    <th>Статус</th>
                    <th style="width:1%">Действия</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $row)
                    @php($st = is_array($statsByTag[$row->docker_tag] ?? null) ? $statsByTag[$row->docker_tag] : null)
                    <tr>
                        <td style="vertical-align:top">
                            <div style="font-weight:700">{{ $row->title }}</div>
                            <div class="muted small">slug: <code>{{ $row->slug }}</code> · шаблон: <code>{{ $row->base_template }}</code></div>
                        </td>
                        <td style="vertical-align:top">
                            <code style="word-break:break-all">{{ $row->docker_tag }}</code>
                            @if ($st)
                                <div class="muted small" style="margin-top:0.25rem;line-height:1.35">
                                    Размер: <strong>{{ $st['size_human'] ?? '—' }}</strong>
                                    @if (! empty($st['layers_count']))
                                        <span>· слоёв: <strong>{{ (int) $st['layers_count'] }}</strong></span>
                                    @endif
                                </div>
                            @endif
                            <form method="post" action="{{ route('admin.practice.images.stats.refresh') }}" style="margin-top:0.35rem">
                                @csrf
                                <input type="hidden" name="tag" value="{{ $row->docker_tag }}">
                                <input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
                                <button type="submit" class="btn btn-ghost" style="padding:0.25rem 0.55rem;font-size:0.82rem">Проверить</button>
                            </form>
                        </td>
                        <td style="vertical-align:top">
                            @if ($row->last_build_status === 'ok')
                                <span class="flash ok" style="display:inline-block;margin:0;padding:0.2rem 0.45rem">Собран</span>
                            @elseif ($row->last_build_status === 'fail')
                                <span class="flash err" style="display:inline-block;margin:0;padding:0.2rem 0.45rem">Ошибка</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                            @if ($row->last_built_at)
                                <div class="muted small" style="margin-top:0.25rem">посл. сборка: {{ $row->last_built_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</div>
                            @endif
                        </td>
                        <td class="teacher-report-nowrap" style="vertical-align:top">
                            <div class="icon-strip" role="group" aria-label="Действия с образом">
                                <a class="icon-btn" href="{{ route('admin.practice.images.edit', ['id' => $row->id]) }}" data-tip="Открыть" aria-label="Открыть">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.8"/><path d="M4 7l3-4h10l3 4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 12h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                </a>
                                <form method="post" action="{{ route('admin.practice.images.build', ['id' => $row->id]) }}" style="margin:0">
                                    @csrf
                                    <button type="submit" class="icon-btn" data-tip="Собрать" aria-label="Собрать">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8 7l4-4 4 4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M5 13h14v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7Z" stroke="currentColor" stroke-width="1.8"/><path d="M8 16h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    </button>
                                </form>
                                <form method="post" action="{{ route('admin.practice.images.stats.refresh') }}" style="margin:0">
                                    @csrf
                                    <input type="hidden" name="tag" value="{{ $row->docker_tag }}">
                                    <input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
                                    <button type="submit" class="icon-btn" data-tip="Проверить" aria-label="Проверить">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21a9 9 0 1 0-9-9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </form>
                                <form method="post" action="{{ route('admin.practice.images.export', ['id' => $row->id]) }}" style="margin:0">
                                    @csrf
                                    <button type="submit" class="icon-btn" data-tip="Экспорт (tar)" aria-label="Экспорт">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 14V3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8 7l4-4 4 4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M5 14v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="muted">
                            @if ($activeTab === 'built')
                                Пока нет собранных образов из библиотеки. Переключитесь на «Библиотека», создайте образ и нажмите «Собрать».
                            @else
                                Пока нет образов. Создайте первый.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem">
            {{ $items->links() }}
        </div>
    </div>
    <style>
        .icon-strip{display:inline-flex;gap:.35rem;align-items:center}
        .icon-btn{width:40px;height:40px;border-radius:10px;border:1px solid rgba(10,119,85,.25);background:#fff;color:var(--accent,#0a7);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;position:relative}
        .icon-btn:hover{background:rgba(10,119,85,.08)}
        .icon-btn svg{width:20px;height:20px}
        .icon-btn[data-tip]:hover::after{content:attr(data-tip);position:absolute;bottom:-34px;left:50%;transform:translateX(-50%);white-space:nowrap;background:#0f172a;color:#fff;padding:6px 8px;border-radius:8px;font-size:.78rem;box-shadow:0 8px 24px rgba(2,6,23,.25);z-index:5}
    </style>
@endsection

