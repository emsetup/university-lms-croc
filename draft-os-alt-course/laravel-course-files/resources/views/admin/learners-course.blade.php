@extends('layouts.admin')

@section('title', 'Обучающиеся — '.$course->title)

@section('content')
    <div
        class="ap-learners-split"
        data-ap-learners
        data-ap-csrf="{{ csrf_token() }}"
        data-ap-preselect-email="{{ e((string) request()->query('user', '')) }}"
    >
        <aside class="ap-learners-split__left" aria-label="Список обучающихся">
            <div class="ap-learners-split__left-head">
                <h1 class="ap-learners-split__title">Обучающиеся</h1>
                <p class="ap-muted ap-learners-split__lead">{{ $course->title }}</p>
                <label class="ap-learners-split__search-label" for="ap-learners-search">Поиск по email или ФИО</label>
                <input id="ap-learners-search" type="search" class="ap-modal__input ap-learners-split__search" placeholder="Email или ФИО…" autocomplete="off">
                <div class="ap-toggle-row ap-learners-split__sort">
                    <label class="ap-toggle">
                        <input type="checkbox" id="ap-learners-sort-active" class="ap-toggle__input" value="1">
                        <span class="ap-toggle__track" aria-hidden="true"></span>
                        <span class="ap-toggle__label">Сначала недавно активные</span>
                    </label>
                </div>
            </div>
            <ul id="ap-learners-list" class="ap-learners-list" role="listbox" aria-label="Обучающиеся"></ul>
        </aside>
        <div class="ap-learners-split__right">
            <div id="ap-learners-empty" class="ap-learners-empty">
                <p class="ap-muted">Выберите обучающегося слева, чтобы увидеть прогресс по модулям.</p>
            </div>
            <div id="ap-learners-detail" class="ap-learners-detail" hidden>
                <div id="ap-learners-detail-inner" class="ap-learners-detail__inner">
                    <header class="ap-learners-detail__head">
                        <div class="ap-learners-detail__avatar" id="ap-learners-av" aria-hidden="true">—</div>
                        <div class="ap-learners-detail__head-text">
                            <div class="ap-learners-detail__primary" id="ap-learners-primary"></div>
                            <div class="ap-learners-detail__secondary ap-muted" id="ap-learners-secondary" hidden></div>
                        </div>
                    </header>
                    <p class="ap-learners-detail__summary" id="ap-learners-summary"></p>
                    <div class="ap-learners-detail__bar-wrap" aria-hidden="true">
                        <div id="ap-learners-bar" class="ap-learners-detail__bar" style="width:0%"></div>
                    </div>
                    <nav class="ap-learners-jump" id="ap-learners-jump" aria-label="К модулю"></nav>
                    <div id="ap-learners-modules" class="ap-learners-modules"></div>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="ap-learners-json-data">@json($learnersJson)</script>

    <div id="ap-learners-reset-modal" class="ap-modal" role="dialog" aria-modal="true" aria-hidden="true" hidden>
        <div class="ap-modal__backdrop" data-ap-learners-modal-close tabindex="-1"></div>
        <div class="ap-modal__panel">
            <div class="ap-modal__head">
                <h2 class="ap-modal__title">Сбросить попытки?</h2>
                <button type="button" class="btn btn-ghost" data-ap-learners-modal-close>Закрыть</button>
            </div>
            <p class="ap-muted" id="ap-learners-reset-text"></p>
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-learners-modal-close>Отмена</button>
                <button type="button" class="btn btn-primary" id="ap-learners-reset-confirm" style="background:#b91c1c;border-color:#b91c1c">Сбросить</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/learners-course.js') }}"></script>
@endsection
