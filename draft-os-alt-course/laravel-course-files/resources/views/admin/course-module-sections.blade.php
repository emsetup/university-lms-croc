@extends('layouts.course')

@section('title', 'Разделы модуля')

@section('content')
    <div class="card" style="max-width:960px;margin:0 auto 1rem">
        @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'settings'])
        <p class="muted" style="margin:0 0 0.5rem"><a href="{{ route('admin.course.settings', ['key' => $adminKey]) }}">← Все модули</a></p>
        <h1 style="margin:0 0 0.35rem">Разделы: {{ $courseModule->title }}</h1>
        <p class="muted small" style="margin:0;line-height:1.5">
            Пакет контента <strong>№{{ $courseModule->effectiveContentIndex() }}</strong> —
            <a href="{{ route('admin.theory.edit', ['module' => $courseModule->effectiveContentIndex(), 'key' => $adminKey]) }}">теория и сниппеты</a>,
            <a href="{{ route('admin.quiz.edit.module', ['module' => $courseModule->effectiveContentIndex(), 'kind' => 'theory_quiz', 'key' => $adminKey]) }}">тест по теории</a>,
            <a href="{{ route('admin.quiz.edit.module', ['module' => $courseModule->effectiveContentIndex(), 'kind' => 'module_exam', 'key' => $adminKey]) }}">итоговый тест</a>.
        </p>
    </div>

    @if (session('ok'))
        <div class="card" style="max-width:960px;margin:0 auto 1rem;border-color:#b8dcc8;background:#f0faf5">{{ session('ok') }}</div>
    @endif
    @if (session('err'))
        <div class="card" style="max-width:960px;margin:0 auto 1rem;border-color:#f5c2c7;background:#fff5f5">{{ session('err') }}</div>
    @endif

    <div class="card" style="max-width:960px;margin:0 auto 1rem">
        <h2 style="margin-top:0">Добавить раздел</h2>
        <form method="post" action="{{ route('admin.course.module.sections.store', ['courseModule' => $courseModule->id, 'key' => $adminKey]) }}" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-end">
            @csrf
            <div>
                <label class="muted small" style="display:block;margin-bottom:0.25rem">Тип</label>
                <select name="type" class="btn btn-ghost" style="padding:0.45rem 0.65rem">
                    @foreach ($types as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;min-width:200px">
                <label class="muted small" style="display:block;margin-bottom:0.25rem">Название</label>
                <input type="text" name="title" required maxlength="200" class="btn btn-ghost" style="width:100%;text-align:left;padding:0.45rem 0.65rem;border:1px solid var(--line,#dfe8e4)" placeholder="Например: Теория">
            </div>
            <button type="submit" class="btn btn-primary">Добавить</button>
        </form>
    </div>

    <div class="card" style="max-width:960px;margin:0 auto">
        <h2 style="margin-top:0">Порядок и разделы</h2>
        <p class="muted small">Перетащите строки за ручку слева, затем нажмите «Сохранить порядок».</p>

        <ul id="course-sections-list" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.5rem">
            @foreach ($sections as $s)
                <li data-id="{{ $s->id }}" draggable="true" style="display:flex;align-items:center;gap:0.65rem;padding:0.65rem 0.75rem;border:1px solid var(--line,#dfe8e4);border-radius:10px;background:#fff;cursor:grab">
                    <span class="drag-h" title="Перетащить" style="user-select:none;color:var(--muted,#5c6b76);font-size:1.1rem">≡</span>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700">{{ $s->title }}</div>
                        <div class="muted small">Тип: <code>{{ $s->type }}</code> · id {{ $s->id }} · {{ $s->is_enabled ? 'включён' : 'выключен' }}</div>
                    </div>
                    <a class="btn btn-ghost" href="{{ route('admin.course.module.section.settings', ['courseModule' => $courseModule->id, 'section' => $s->id, 'key' => $adminKey]) }}">Настройки типа</a>
                    <form method="post" action="{{ route('admin.course.module.sections.update', ['courseModule' => $courseModule->id, 'section' => $s->id, 'key' => $adminKey]) }}" style="margin:0">
                        @csrf
                        <input type="hidden" name="title" value="{{ $s->title }}">
                        <input type="hidden" name="is_enabled" value="{{ $s->is_enabled ? '0' : '1' }}">
                        <button type="submit" class="btn btn-ghost">{{ $s->is_enabled ? 'Выключить' : 'Включить' }}</button>
                    </form>
                    <form method="post" action="{{ route('admin.course.module.sections.destroy', ['courseModule' => $courseModule->id, 'section' => $s->id, 'key' => $adminKey]) }}" style="margin:0" onsubmit="return confirm('Удалить раздел «{{ $s->title }}»?');">
                        @csrf
                        <button type="submit" class="btn btn-ghost" style="color:#b91c1c">Удалить</button>
                    </form>
                </li>
            @endforeach
        </ul>

        <form id="course-sections-reorder" method="post" action="{{ route('admin.course.module.sections.reorder', ['courseModule' => $courseModule->id, 'key' => $adminKey]) }}" style="margin-top:1rem">
            @csrf
            <div id="order-fields"></div>
            <button type="submit" class="btn btn-primary">Сохранить порядок</button>
        </form>
    </div>

    <script>
        (function () {
            var list = document.getElementById('course-sections-list');
            var orderWrap = document.getElementById('order-fields');
            var reorderForm = document.getElementById('course-sections-reorder');
            if (!list || !orderWrap || !reorderForm) return;
            function fillOrderFields() {
                orderWrap.innerHTML = '';
                list.querySelectorAll('li[data-id]').forEach(function (li) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'order[]';
                    inp.value = li.getAttribute('data-id');
                    orderWrap.appendChild(inp);
                });
            }
            fillOrderFields();
            var dragEl = null;
            list.querySelectorAll('li[draggable="true"]').forEach(function (row) {
                row.addEventListener('dragstart', function (e) {
                    dragEl = row;
                    e.dataTransfer.effectAllowed = 'move';
                    row.style.opacity = '0.5';
                });
                row.addEventListener('dragend', function () {
                    row.style.opacity = '';
                    dragEl = null;
                    fillOrderFields();
                });
                row.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (!dragEl || dragEl === row) return;
                    var rect = row.getBoundingClientRect();
                    var next = (e.clientY - rect.top) > rect.height / 2;
                    list.insertBefore(dragEl, next ? row.nextSibling : row);
                });
            });
            reorderForm.addEventListener('submit', function () { fillOrderFields(); });
        })();
    </script>
@endsection
