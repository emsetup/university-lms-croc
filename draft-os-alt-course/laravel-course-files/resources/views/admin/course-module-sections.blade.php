@extends('layouts.admin')

@section('title', 'Разделы модуля')

@section('content')
    @php
        $rpCourse = array_merge(\App\Support\AdminNavigation::adminCourseRouteParams(), ($adminKey ?? '') !== '' ? ['key' => $adminKey] : []);
    @endphp

    <div class="ap-narrow-page">
        <div class="admin-card">
            <p class="u-m0"><a class="btn btn-ghost btn-sm" href="{{ route('admin.course.settings', $rpCourse) }}">Все модули</a></p>
            <div class="practice-page__head u-mt-1">
                <h1 class="practice-page__title">Разделы: {{ $courseModule->title }}</h1>
                <div class="actions-row">
                    <a class="btn btn-secondary btn-sm" href="{{ route('admin.course.module.content.edit', array_merge($rpCourse, ['courseModule' => $courseModule->id])) }}">Контент (БД)</a>
                    <a class="btn btn-secondary btn-sm" href="{{ route('admin.course.module.practice', array_merge($rpCourse, ['courseModule' => $courseModule->id])) }}">Практика (Docker)</a>
                </div>
            </div>
            <p class="ap-muted small u-m0 u-mt-1">
                Пакет контента <strong>№{{ $courseModule->effectiveContentIndex() }}</strong> —
                <a href="{{ route('admin.theory.edit', array_merge($rpCourse, ['module' => $courseModule->effectiveContentIndex()])) }}">теория и сниппеты</a>,
                <a href="{{ route('admin.quiz.edit.module', array_merge($rpCourse, ['module' => $courseModule->effectiveContentIndex(), 'kind' => 'theory_quiz'])) }}">тест по теории</a>,
                <a href="{{ route('admin.quiz.edit.module', array_merge($rpCourse, ['module' => $courseModule->effectiveContentIndex(), 'kind' => 'module_exam'])) }}">итоговый тест</a>.
            </p>
        </div>

        <div class="admin-card">
            <h2 class="admin-card__title">Добавить раздел</h2>
            <form method="post" action="{{ route('admin.course.module.sections.store', ['courseModule' => $courseModule->id, 'key' => $adminKey]) }}" class="form-row">
                @csrf
                <div class="form-field">
                    <label class="form-label" for="new-sec-type">Тип</label>
                    <select id="new-sec-type" name="type" class="form-select">
                        @foreach ($types as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label" for="new-sec-title">Название</label>
                    <input id="new-sec-title" type="text" name="title" required maxlength="200" class="form-input" placeholder="Например: Теория">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Добавить</button>
            </form>
        </div>

        <div class="admin-card">
            <h2 class="admin-card__title">Порядок и разделы</h2>
            <p class="admin-card__lead">Перетащите строки за ручку слева, затем нажмите «Сохранить порядок».</p>

            <ul id="course-sections-list" class="ap-sec-list-modern">
                @forelse ($sections as $s)
                    <li data-id="{{ $s->id }}" draggable="true" class="ap-sec-list-modern__item">
                        <span class="ap-sec-list-modern__drag drag-h" title="Перетащить">≡</span>
                        <div class="ap-sec-list-modern__body">
                            <div class="ap-sec-list-modern__title">{{ $s->title }}</div>
                            <div class="admin-table__meta">
                                Тип <span class="mono">{{ $s->type }}</span>
                                · id {{ $s->id }}
                                · {{ $s->is_enabled ? 'включён' : 'выключен' }}
                            </div>
                        </div>
                        <div class="ap-sec-list-modern__actions">
                            <a class="btn btn-secondary btn-sm" href="{{ route('admin.course.module.section.settings', ['courseModule' => $courseModule->id, 'section' => $s->id, 'key' => $adminKey]) }}">Настройки типа</a>
                            <form method="post" action="{{ route('admin.course.module.sections.update', ['courseModule' => $courseModule->id, 'section' => $s->id, 'key' => $adminKey]) }}" class="admin-inline-form">
                                @csrf
                                <input type="hidden" name="title" value="{{ $s->title }}">
                                <input type="hidden" name="is_enabled" value="{{ $s->is_enabled ? '0' : '1' }}">
                                <button type="submit" class="btn btn-ghost btn-sm">{{ $s->is_enabled ? 'Выключить' : 'Включить' }}</button>
                            </form>
                            <button type="button" class="btn btn-danger btn-sm ap-sec-del-open"
                                    data-ap-sec-del-url="{{ route('admin.course.module.sections.destroy', ['courseModule' => $courseModule->id, 'section' => $s->id, 'key' => $adminKey]) }}"
                                    data-section-title="{{ e($s->title) }}">Удалить</button>
                        </div>
                    </li>
                @empty
                    <li class="empty-state">
                        <p class="empty-state__title">Разделов пока нет</p>
                        <p class="empty-state__text">Добавьте первый блок формой выше.</p>
                    </li>
                @endforelse
            </ul>

            <form method="post" id="ap-sec-del-form" action="#" class="ap-hidden-form" hidden>
                @csrf
            </form>

            <form id="course-sections-reorder" method="post" action="{{ route('admin.course.module.sections.reorder', ['courseModule' => $courseModule->id, 'key' => $adminKey]) }}" class="u-mt-1">
                @csrf
                <div id="order-fields"></div>
                <button type="submit" class="btn btn-primary btn-sm" @disabled($sections->isEmpty())>Сохранить порядок</button>
            </form>
        </div>
    </div>

    <div class="ap-modal" id="ap-sec-page-del-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-sec-page-del-title">
        <div class="ap-modal__backdrop" data-ap-sec-page-del-close tabindex="-1"></div>
        <div class="ap-modal__panel">
            <button type="button" class="ap-modal__close" data-ap-sec-page-del-close aria-label="Закрыть">&times;</button>
            <h2 id="ap-sec-page-del-title" class="ap-modal__title">Удалить раздел?</h2>
            <p class="ap-muted" id="ap-sec-page-del-text"></p>
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-sec-page-del-close>Отмена</button>
                <button type="button" class="btn btn-danger" id="ap-sec-page-del-confirm">Удалить</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var delModal = document.getElementById('ap-sec-page-del-modal');
            var delForm = document.getElementById('ap-sec-del-form');
            var delText = document.getElementById('ap-sec-page-del-text');
            var delConfirm = document.getElementById('ap-sec-page-del-confirm');
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
            document.querySelectorAll('[data-ap-sec-page-del-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                    closeDelModal();
                });
            });
            document.querySelectorAll('.ap-sec-del-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var url = btn.getAttribute('data-ap-sec-del-url') || '';
                    var title = btn.getAttribute('data-section-title') || '';
                    if (!delForm || !url) return;
                    delForm.setAttribute('action', url);
                    if (delText) delText.textContent = 'Будет удалён раздел «' + title + '». Это действие необратимо.';
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
