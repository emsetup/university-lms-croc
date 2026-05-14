@extends('layouts.admin')

@section('title', 'Модули курса')

@section('content')
    @php $rp = array_merge($ap ?? [], $adminKey !== '' ? ['key' => $adminKey] : []); @endphp

    <div class="ap-page-head">
        <h1 class="ap-page-title">Модули курса</h1>
        <p class="ap-page-lead ap-muted">
            <a href="{{ route('admin.course.settings', $ap ?? []) }}">← Настройки курса</a>
            · порядок модулей задаётся перетаскиванием
        </p>
    </div>

    <div class="card" style="max-width:960px;margin:1rem auto 1rem">
        <p class="muted" style="margin:0;line-height:1.5">
            Для каждого модуля — своя цепочка разделов (теория, тест, практика, экзамен).
            Поле «пакет контента» — номер набора теории и вопросов в файлах курса (как в старом <code>config/course.php</code>); без номера подставляется 1.
        </p>
    </div>

    <div class="card" style="max-width:960px;margin:0 auto 1rem">
        <h2 style="margin-top:0">Порядок модулей</h2>
        <p class="muted small">Перетащите строки, затем «Сохранить порядок модулей».</p>
        <ul id="course-modules-list" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.75rem">
            @forelse ($modules as $m)
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
                                · <a href="{{ route('admin.theory.edit', array_merge($rp, ['module' => $m->effectiveContentIndex()])) }}">содержимое (MD)</a>,
                                <a href="{{ route('admin.quiz.edit.module', array_merge($rp, ['module' => $m->effectiveContentIndex(), 'kind' => 'theory_quiz'])) }}">тест</a>,
                                <a href="{{ route('admin.quiz.edit.module', array_merge($rp, ['module' => $m->effectiveContentIndex(), 'kind' => 'module_exam'])) }}">экзамен</a>
                            </div>
                            <form method="post" action="{{ route('admin.course.settings.module.update', array_merge($rp, ['courseModule' => $m->id])) }}" style="display:grid;gap:0.5rem;margin:0">
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
                                <a class="btn btn-ghost" href="{{ route('admin.course.module.sections', array_merge($rp, ['courseModule' => $m->id])) }}">Разделы модуля</a>
                                <button type="button" class="btn btn-ghost ap-mod-page-del-open" style="color:#b91c1c"
                                        data-ap-mod-del-url="{{ route('admin.course.settings.module.destroy', array_merge($rp, ['courseModule' => $m->id])) }}"
                                        data-module-title="{{ e($m->title) }}">Удалить</button>
                            </div>
                        </div>
                    </div>
                </li>
            @empty
                <li class="muted" style="padding:1rem;border:1px dashed var(--line,#dfe8e4);border-radius:10px;background:#fafafa;list-style:none">Модулей пока нет. Добавьте первый модуль в блоке ниже.</li>
            @endforelse
        </ul>

        <form id="course-modules-reorder" method="post" action="{{ route('admin.course.settings.modules.reorder', $rp) }}" style="margin-top:1rem">
            @csrf
            <div id="module-order-fields"></div>
            <button type="submit" class="btn btn-primary" @disabled($modules->isEmpty())>Сохранить порядок модулей</button>
        </form>
    </div>

    <div class="card" style="max-width:960px;margin:0 auto">
        <h2 style="margin-top:0">Добавить модуль</h2>
        <p class="muted small">Разделы для нового модуля копируются с первого существующего; если модулей ещё не было — создаётся стандартный набор из четырёх типов.</p>
        <form method="post" action="{{ route('admin.course.settings.module.store', $rp) }}" style="display:grid;gap:0.65rem;max-width:36rem">
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

    <form method="post" id="ap-mod-page-del-form" action="#" style="margin:0;display:none">
        @csrf
    </form>

    <div class="ap-modal" id="ap-mod-page-del-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-mod-page-del-title">
        <div class="ap-modal__backdrop" data-ap-mod-page-del-close tabindex="-1"></div>
        <div class="ap-modal__panel">
            <button type="button" class="ap-modal__close" data-ap-mod-page-del-close aria-label="Закрыть">&times;</button>
            <h2 id="ap-mod-page-del-title" class="ap-modal__title">Удалить модуль?</h2>
            <p class="ap-muted" id="ap-mod-page-del-text"></p>
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-mod-page-del-close>Отмена</button>
                <button type="button" class="btn btn-primary" id="ap-mod-page-del-confirm" style="background:#b91c1c;border-color:#b91c1c">Удалить</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var delModal = document.getElementById('ap-mod-page-del-modal');
            var delForm = document.getElementById('ap-mod-page-del-form');
            var delText = document.getElementById('ap-mod-page-del-text');
            var delConfirm = document.getElementById('ap-mod-page-del-confirm');
            function openDelModal() {
                if (!delModal) return;
                delModal.classList.add('is-open');
                delModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('ap-modal-open');
            }
            function closeDelModal() {
                if (!delModal) return;
                delModal.classList.remove('is-open');
                delModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('ap-modal-open');
            }
            document.querySelectorAll('[data-ap-mod-page-del-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                    closeDelModal();
                });
            });
            document.querySelectorAll('.ap-mod-page-del-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var url = btn.getAttribute('data-ap-mod-del-url') || '';
                    var title = btn.getAttribute('data-module-title') || '';
                    if (!delForm || !url) return;
                    delForm.setAttribute('action', url);
                    if (delText) delText.textContent = 'Будет удалён модуль «' + title + '» и все его разделы. Это действие необратимо.';
                    openDelModal();
                });
            });
            if (delConfirm && delForm) {
                delConfirm.addEventListener('click', function () {
                    delForm.submit();
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape' || !delModal || !delModal.classList.contains('is-open')) return;
                closeDelModal();
                e.preventDefault();
            });

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
