@extends('layouts.admin')

@section('title', 'Библиотека Docker-образов')

@php
    $apNav = \App\Support\AdminNavigation::adminCourseRouteParams();
@endphp

@section('content')
    <div class="ap-page ap-fade ap-docker">
        <div class="ap-docker__head">
            <div>
                <h1 class="ap-page-title">Библиотека Docker-образов</h1>
                <p class="ap-page-lead ap-docker__lead">
                    Образы из таблицы <code>practice_images</code> и настройки практик модулей курсов. Сборка и проверка — через lab-daemon.
                </p>
            </div>
            <button type="button" class="btn btn-primary ap-docker__create" id="ap-docker-open-create">
                @include('partials.ap-icon', ['name' => 'plus', 'size' => 'sm'])
                Создать образ
            </button>
        </div>

        <form method="get" action="{{ route('admin.docker.library') }}" class="ap-docker__search" role="search">
            <label class="ap-docker__search-label" for="ap-docker-q">Поиск</label>
            <input id="ap-docker-q" name="q" type="search" value="{{ $q }}" placeholder="Название или тег образа…" class="ap-modal__input ap-docker__search-input" autocomplete="off">
            <button type="submit" class="btn btn-primary">Найти</button>
            @if ($q !== '')
                <a href="{{ route('admin.docker.library') }}" class="btn btn-ghost">Сбросить</a>
            @endif
        </form>

        @if ($errors->any())
            <div class="admin-flash admin-flash--err ap-docker__flash" role="alert">
                <strong>Проверьте поля формы.</strong>
                <ul style="margin:0.35rem 0 0;padding-left:1.1rem">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $daemonConfigured)
            <div class="admin-flash admin-flash--err ap-docker__warn" role="status">
                Lab-daemon не настроен: в <code>.env</code> задайте <code>PRACTICE_LAB_DAEMON_URL</code> и <code>PRACTICE_LAB_DAEMON_SECRET</code> — кнопка «Проверить» и размеры образов будут недоступны.
            </div>
        @endif

        <div class="ap-docker__grid">
            @foreach ($items as $row)
                @php
                    $tag = (string) $row->docker_tag;
                    $st = is_array($statsByTag[$tag] ?? null) ? $statsByTag[$tag] : null;
                    $bytes = (int) ($st['size_bytes'] ?? 0);
                    $mb = $bytes > 0 ? round($bytes / 1048576, 1) : null;
                    $layers = isset($st['layers_count']) ? (int) $st['layers_count'] : null;
                    $lbs = (string) ($row->last_build_status ?? '');
                    if ($lbs === 'running') {
                        $badgeClass = 'ap-docker-badge ap-docker-badge--build';
                        $badgeLabel = 'Сборка…';
                    } elseif ($lbs === 'fail') {
                        $badgeClass = 'ap-docker-badge ap-docker-badge--err';
                        $badgeLabel = 'Ошибка';
                    } elseif ($row->is_built) {
                        $badgeClass = 'ap-docker-badge ap-docker-badge--ok';
                        $badgeLabel = 'Собран';
                    } else {
                        $badgeClass = 'ap-docker-badge ap-docker-badge--muted';
                        $badgeLabel = 'Не собран';
                    }
                    $settings = $row->modulePracticeSettings->sortBy(function ($s) {
                        $c = $s->courseModule?->course?->title ?? '';
                        $m = $s->courseModule?->title ?? '';

                        return $c.'|'.$m;
                    });
                    $used = $settings->count();
                    $finalLabCourses = \Illuminate\Support\Facades\Schema::hasColumn('courses', 'final_lab_practice_image_id')
                        ? (int) \App\Models\Course::query()->where('final_lab_practice_image_id', $row->id)->count()
                        : 0;
                    $blocked = $used + $finalLabCourses;
                    $editUrl = ! empty($apNav) ? route('admin.practice.images.edit', array_merge($apNav, ['id' => $row->id])) : null;
                @endphp
                <article class="ap-docker-card">
                    <div class="ap-docker-card__top">
                        <code class="ap-docker-card__tag">{{ trim((string) $row->title) !== '' ? $row->title.':' : '' }}{{ $tag !== '' ? $tag : '—' }}</code>
                        <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
                    </div>
                    @if (trim((string) ($row->description ?? '')) !== '')
                        <p class="ap-docker-card__desc">{{ $row->description }}</p>
                    @endif
                    <div class="ap-docker-card__meta">
                        @if ($mb !== null)
                            <span>{{ $mb }} МБ</span>
                        @else
                            <span class="ap-muted">— МБ</span>
                        @endif
                        <span class="ap-docker-card__dot" aria-hidden="true">·</span>
                        @if ($layers !== null && $layers >= 0)
                            <span>Слоёв {{ $layers }}</span>
                        @else
                            <span class="ap-muted">Слоёв —</span>
                        @endif
                    </div>

                    <div class="ap-docker-card__use">
                        @if ($blocked === 0)
                            <span class="ap-muted">Не используется в практиках</span>
                        @else
                            <details class="ap-docker-use">
                                <summary class="ap-docker-use__summary">
                                    Используется: {{ $blocked }} {{ $blocked === 1 ? 'привязка' : 'привязок' }}
                                </summary>
                                <ul class="ap-docker-use__list">
                                    @if ($finalLabCourses > 0)
                                        <li>
                                            <span class="ap-docker-use__course">Финальная лабораторная</span>
                                            <span class="ap-docker-use__sep" aria-hidden="true"><span class="ap-docker-use__chev">@include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])</span></span>
                                            <span class="ap-docker-use__module">{{ $finalLabCourses }} {{ $finalLabCourses === 1 ? 'курс' : 'курса' }}</span>
                                        </li>
                                    @endif
                                    @foreach ($settings as $ps)
                                        @php
                                            $cour = $ps->courseModule?->course;
                                            $mod = $ps->courseModule;
                                        @endphp
                                        <li>
                                            <span class="ap-docker-use__course">{{ $cour?->title ?? '—' }}</span>
                                            <span class="ap-docker-use__sep" aria-hidden="true"><span class="ap-docker-use__chev">@include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])</span></span>
                                            <span class="ap-docker-use__module">{{ $mod?->title ?? 'Модуль' }}</span>
                                            <span class="ap-docker-use__sep" aria-hidden="true"><span class="ap-docker-use__chev">@include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])</span></span>
                                            <span class="ap-docker-use__lab">Практика</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>

                    <div class="ap-docker-card__actions">
                        @if ($daemonConfigured && $tag !== '')
                            <form method="post" action="{{ route('admin.docker.library.stats.refresh') }}" class="ap-docker-card__form">
                                @csrf
                                <input type="hidden" name="tag" value="{{ $tag }}">
                                <button type="submit" class="btn btn-ghost btn-sm">Проверить</button>
                            </form>
                        @else
                            <button type="button" class="btn btn-ghost btn-sm" disabled title="Нет lab-daemon">Проверить</button>
                        @endif

                        @if ($daemonConfigured)
                            <form method="post" action="{{ route('admin.docker.library.build', ['id' => $row->id]) }}" class="ap-docker-card__form">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">Пересобрать</button>
                            </form>
                        @else
                            <button type="button" class="btn btn-ghost btn-sm" disabled title="Нет lab-daemon">Пересобрать</button>
                        @endif

                        @if ($editUrl)
                            <a class="btn btn-ghost btn-sm" href="{{ $editUrl }}">Конструктор</a>
                        @else
                            <span class="ap-docker-card__hint ap-muted" title="Выберите курс в каталоге, чтобы открыть конструктор образа с привязкой к курсу.">Конструктор</span>
                        @endif

                        @if ($blocked > 0)
                            <button type="button" class="ap-icon-btn ap-icon-btn--danger" disabled title="Образ используется в практиках или в финальной лабораторной">
                                @include('partials.ap-icon', ['name' => 'trash', 'size' => 'md'])
                            </button>
                        @else
                            <button type="button" class="ap-icon-btn ap-icon-btn--danger ap-docker-del-open" title="Удалить образ" aria-label="Удалить"
                                    data-ap-docker-del-tag="{{ e($tag) }}"
                                    data-ap-docker-del-action="{{ route('admin.docker.library.destroy', ['id' => $row->id]) }}">
                                @include('partials.ap-icon', ['name' => 'trash', 'size' => 'md'])
                            </button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if ($items->isEmpty())
            <p class="ap-muted ap-docker__empty">Образов пока нет. Создайте первый — шаблон по умолчанию: модуль 1 (Alt).</p>
        @endif

        @if ($items->hasPages())
            <div class="ap-docker__pager">
                {{ $items->links() }}
            </div>
        @endif
    </div>

    <form method="post" id="ap-docker-del-form" action="#" class="ap-docker-card__form ap-docker-card__form--trash" hidden>
        @csrf
    </form>

    <div class="ap-modal" id="ap-docker-delete-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-docker-delete-title">
        <div class="ap-modal__backdrop" data-ap-docker-del-close tabindex="-1"></div>
        <div class="ap-modal__panel">
            <button type="button" class="ap-modal__close" data-ap-docker-del-close aria-label="Закрыть">&times;</button>
            <h2 id="ap-docker-delete-title" class="ap-modal__title">Удалить образ?</h2>
            <p class="ap-muted" id="ap-docker-delete-text"></p>
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-docker-del-close>Отмена</button>
                <button type="button" class="btn btn-primary" id="ap-docker-delete-confirm" style="background:#b91c1c;border-color:#b91c1c">Удалить</button>
            </div>
        </div>
    </div>

    <div class="ap-modal" id="ap-docker-create-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-docker-create-title">
        <div class="ap-modal__backdrop" data-ap-docker-modal-close tabindex="-1"></div>
        <div class="ap-modal__panel ap-modal__panel--wide">
            <button type="button" class="ap-modal__close" data-ap-docker-modal-close aria-label="Закрыть">&times;</button>
            <h2 id="ap-docker-create-title" class="ap-modal__title">Создать образ</h2>
            <form method="post" action="{{ route('admin.docker.library.store') }}" class="ap-modal__form">
                @csrf
                <div class="ap-modal__field">
                    <label class="ap-modal__label" for="ap-docker-title">Название</label>
                    <input id="ap-docker-title" name="title" type="text" required maxlength="200" class="ap-modal__input" value="{{ old('title') }}" placeholder="Например, Лаборатория модуля 2">
                </div>
                <div class="ap-modal__field">
                    <label class="ap-modal__label" for="ap-docker-tag">Тег Docker</label>
                    <input id="ap-docker-tag" name="docker_tag" type="text" required maxlength="200" class="ap-modal__input ap-modal__input--mono" value="{{ old('docker_tag') }}" placeholder="registry/namespace/name:tag" autocomplete="off">
                </div>
                <div class="ap-modal__field">
                    <label class="ap-modal__label" for="ap-docker-desc">Описание</label>
                    <textarea id="ap-docker-desc" name="description" rows="3" maxlength="5000" class="ap-modal__input" placeholder="Необязательно">{{ old('description') }}</textarea>
                </div>
                <div class="ap-modal__footer">
                    <button type="button" class="btn btn-ghost" data-ap-docker-modal-close>Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('ap-docker-create-modal');
            var openBtn = document.getElementById('ap-docker-open-create');
            var delModal = document.getElementById('ap-docker-delete-modal');
            var delForm = document.getElementById('ap-docker-del-form');
            var delText = document.getElementById('ap-docker-delete-text');
            var delConfirm = document.getElementById('ap-docker-delete-confirm');

            function openM() {
                if (!modal) return;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('ap-modal-open');
            }
            function closeM() {
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                if (!delModal || !delModal.classList.contains('is-open')) {
                    document.body.classList.remove('ap-modal-open');
                }
            }
            function openDel() {
                if (!delModal) return;
                delModal.classList.add('is-open');
                delModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('ap-modal-open');
            }
            function closeDel() {
                if (!delModal) return;
                delModal.classList.remove('is-open');
                delModal.setAttribute('aria-hidden', 'true');
                if (!modal || !modal.classList.contains('is-open')) {
                    document.body.classList.remove('ap-modal-open');
                }
            }
            if (openBtn) openBtn.addEventListener('click', openM);
            document.querySelectorAll('[data-ap-docker-modal-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                    closeM();
                });
            });
            document.querySelectorAll('[data-ap-docker-del-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                    closeDel();
                });
            });
            document.querySelectorAll('.ap-docker-del-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var url = btn.getAttribute('data-ap-docker-del-action') || '';
                    var tag = btn.getAttribute('data-ap-docker-del-tag') || '';
                    if (!delForm || !url) return;
                    delForm.setAttribute('action', url);
                    if (delText) delText.textContent = 'Будет удалена запись образа «' + tag + '». Рецепт на диске останется, если он уже создан.';
                    openDel();
                });
            });
            if (delConfirm && delForm) {
                delConfirm.addEventListener('click', function () {
                    delForm.submit();
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (delModal && delModal.classList.contains('is-open')) {
                    closeDel();
                    e.preventDefault();
                    return;
                }
                if (modal && modal.classList.contains('is-open')) {
                    closeM();
                    e.preventDefault();
                }
            });
            @if (request()->query('create'))
            openM();
            @endif
            @if ($errors->any())
            openM();
            @endif
        })();
    </script>
@endsection
