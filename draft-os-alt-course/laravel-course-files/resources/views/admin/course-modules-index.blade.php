@extends('layouts.course')

@section('title', 'Настройки курса — модули')

@section('content')
    <div class="card" style="max-width:960px;margin:0 auto 1rem">
        @include('partials.admin-instructor-nav', ['active' => 'settings'])
        <h1 style="margin:0 0 0.35rem">Модули курса</h1>
        <p class="muted" style="margin:0;line-height:1.5">
            Порядок модулей задаётся перетаскиванием. Для каждого модуля — своя цепочка разделов (теория, тест, практика, экзамен).
            Поле «пакет контента» — номер набора теории и вопросов в файлах курса (как в старом <code>config/course.php</code>); без номера подставляется 1.
        </p>
    </div>

    @if (session('ok'))
        <div class="card" style="max-width:960px;margin:0 auto 1rem;border-color:#b8dcc8;background:#f0faf5">{{ session('ok') }}</div>
    @endif
    @if (session('err'))
        <div class="card" style="max-width:960px;margin:0 auto 1rem;border-color:#f5c2c7;background:#fff5f5">{{ session('err') }}</div>
    @endif

    <div class="card" style="max-width:960px;margin:0 auto 1rem">
        <h2 style="margin-top:0">Порядок модулей</h2>
        <p class="muted small">Перетащите строки, затем «Сохранить порядок модулей».</p>
        <ul id="course-modules-list" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.75rem">
            @foreach ($modules as $m)
                <li data-id="{{ $m->id }}" draggable="true" style="border:1px solid var(--line,#dfe8e4);border-radius:10px;background:#fff;padding:0.75rem">
                    <div style="display:flex;align-items:flex-start;gap:0.65rem">
                        <span class="drag-h" title="Перетащить" style="user-select:none;color:var(--muted,#5c6b76);font-size:1.1rem;cursor:grab;padding-top:0.35rem">≡</span>
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:700;margin-bottom:0.35rem">{{ $m->title }}</div>
                            <div class="muted small" style="margin-bottom:0.5rem;line-height:1.45">
                                id {{ $m->id }}
                                @if ($m->letter)
                                    · буква <code>{{ $m->letter }}</code>
                                @endif
                                · пакет контента <strong>№{{ $m->effectiveContentIndex() }}</strong>
                                · <a href="{{ route('admin.theory.edit', ['module' => $m->effectiveContentIndex()]) }}">содержимое (MD)</a>,
                                <a href="{{ route('admin.quiz.edit.module', ['module' => $m->effectiveContentIndex(), 'kind' => 'theory_quiz']) }}">тест</a>,
                                <a href="{{ route('admin.quiz.edit.module', ['module' => $m->effectiveContentIndex(), 'kind' => 'module_exam']) }}">экзамен</a>
                            </div>
                            <form method="post" action="{{ route('admin.course.settings.module.update', ['courseModule' => $m->id]) }}" style="display:grid;gap:0.5rem;margin:0">
                                @csrf
                                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:flex-end">
                                    <div>
                                        <label class="muted small" style="display:block;margin-bottom:0.2rem">Название</label>
                                        <input type="text" name="title" value="{{ $m->title }}" required maxlength="200" class="btn btn-ghost" style="min-width:14rem;padding:0.4rem 0.55rem;border:1px solid var(--line,#dfe8e4)">
                                    </div>
                                    <div>
                                        <label class="muted small" style="display:block;margin-bottom:0.2rem">Буква</label>
                                        <input type="text" name="letter" value="{{ $m->letter }}" maxlength="8" class="btn btn-ghost" style="width:4rem;padding:0.4rem 0.55rem;border:1px solid var(--line,#dfe8e4)">
                                    </div>
                                    <div>
                                        <label class="muted small" style="display:block;margin-bottom:0.2rem">Пакет №</label>
                                        <input type="number" name="content_source_index" value="{{ $m->content_source_index }}" min="1" max="99" placeholder="1" class="btn btn-ghost" style="width:5rem;padding:0.4rem 0.55rem;border:1px solid var(--line,#dfe8e4)">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Сохранить</button>
                                </div>
                                <div>
                                    <label class="muted small" style="display:block;margin-bottom:0.2rem">Краткое описание (для обучающихся)</label>
                                    <textarea name="summary" rows="2" maxlength="5000" class="btn btn-ghost" style="width:100%;text-align:left;padding:0.45rem 0.55rem;border:1px solid var(--line,#dfe8e4);resize:vertical">{{ $m->summary }}</textarea>
                                </div>
                            </form>
                            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.65rem">
                                <a class="btn btn-ghost" href="{{ route('admin.course.module.sections', ['courseModule' => $m->id]) }}">Разделы модуля</a>
                                <form method="post" action="{{ route('admin.course.settings.module.destroy', ['courseModule' => $m->id]) }}" style="margin:0" onsubmit="return confirm('Удалить модуль «{{ $m->title }}» и все его разделы?');">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost" style="color:#b91c1c">Удалить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <form id="course-modules-reorder" method="post" action="{{ route('admin.course.settings.modules.reorder') }}" style="margin-top:1rem">
            @csrf
            <div id="module-order-fields"></div>
            <button type="submit" class="btn btn-primary" @disabled($modules->isEmpty())>Сохранить порядок модулей</button>
        </form>
    </div>

    <div class="card" style="max-width:960px;margin:0 auto">
        <h2 style="margin-top:0">Добавить модуль</h2>
        <p class="muted small">Разделы для нового модуля копируются с первого существующего; если модулей ещё не было — создаётся стандартный набор из четырёх типов.</p>
        <form method="post" action="{{ route('admin.course.settings.module.store') }}" style="display:grid;gap:0.65rem;max-width:36rem">
            @csrf
            <div>
                <label class="muted small" style="display:block;margin-bottom:0.2rem">Название</label>
                <input type="text" name="title" required maxlength="200" class="btn btn-ghost" style="width:100%;padding:0.45rem 0.65rem;border:1px solid var(--line,#dfe8e4)">
            </div>
            <div>
                <label class="muted small" style="display:block;margin-bottom:0.2rem">Буква (необязательно)</label>
                <input type="text" name="letter" maxlength="8" class="btn btn-ghost" style="width:6rem;padding:0.45rem 0.65rem;border:1px solid var(--line,#dfe8e4)">
            </div>
            <div>
                <label class="muted small" style="display:block;margin-bottom:0.2rem">Пакет контента № (необязательно, по умолчанию 1)</label>
                <input type="number" name="content_source_index" min="1" max="99" class="btn btn-ghost" style="width:6rem;padding:0.45rem 0.65rem;border:1px solid var(--line,#dfe8e4)">
            </div>
            <div>
                <label class="muted small" style="display:block;margin-bottom:0.2rem">Описание</label>
                <textarea name="summary" rows="2" maxlength="5000" class="btn btn-ghost" style="width:100%;padding:0.45rem 0.65rem;border:1px solid var(--line,#dfe8e4);resize:vertical"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Добавить модуль</button>
        </form>
    </div>

    <script>
        (function () {
            var list = document.getElementById('course-modules-list');
            var orderWrap = document.getElementById('module-order-fields');
            var reorderForm = document.getElementById('course-modules-reorder');
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
