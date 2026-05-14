@extends('layouts.admin')

@section('title', ($mode ?? 'edit') === 'create' ? 'Админ: создать курс' : 'Админ: курс')

@section('content')
    @php
        $isCreate = ($mode ?? 'edit') === 'create';
        $c = $course;
    @endphp
    <div class="ap-narrow-page">
        <div class="admin-card">
            <p class="u-m0"><a class="btn btn-ghost btn-sm" href="{{ route('admin.courses.index', ['key' => $adminKey]) }}">Все курсы</a></p>
            <h1 class="practice-page__title u-mt-1">{{ $isCreate ? 'Создать курс' : 'Редактировать курс' }}</h1>
            <p class="ap-muted small u-m0 u-mt-1">Курс определяет набор модулей и прогресс обучающихся. Вместо удаления используется архив: архивные курсы видны только администраторам и скрыты от обучающихся.</p>
        </div>

        @if ($errors->any())
            <div class="admin-card u-mt-1 admin-card--warn">
                <div class="admin-card__title">Ошибки формы</div>
                <ul class="form-errors-list">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="admin-card u-mt-1">
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
                        <option value="1" @if ($pub === '1') selected @endif>Опубликован</option>
                        <option value="0" @if ($pub === '0') selected @endif>Черновик</option>
                    </select>
                </div>
                <div>
                    <label class="muted small" style="display:block;margin-bottom:0.25rem">Архив</label>
                    @php
                        $arch = old('is_archived', $c?->is_archived ? '1' : '0');
                    @endphp
                    <select name="is_archived" class="btn btn-ghost" style="padding:0.45rem 0.65rem">
                        <option value="0" @if ($arch === '0') selected @endif>Нет</option>
                        <option value="1" @if ($arch === '1') selected @endif>В архиве</option>
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
        <div class="admin-card u-mt-1">
            <h2 class="admin-card__title">Статистика</h2>
            <div class="ap-muted">Участников: <strong>{{ (int) ($stats['enrolled'] ?? 0) }}</strong> · Завершили: <strong>{{ (int) ($stats['completed'] ?? 0) }}</strong></div>
            <div class="actions-row u-mt-1">
                <a class="btn btn-secondary btn-sm" href="{{ route('admin.courses.enter', ['course' => $c->id, 'key' => $adminKey, 'next' => 'content']) }}">Открыть «Содержимое»</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('admin.courses.enter', ['course' => $c->id, 'key' => $adminKey, 'next' => 'learners']) }}">Открыть «Обучающиеся»</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('admin.courses.enter', ['course' => $c->id, 'key' => $adminKey, 'next' => 'certificates']) }}">Открыть «Сертификаты»</a>
            </div>
        </div>

        <div class="admin-card u-mt-1 admin-card--archive-hint">
            <h2 class="admin-card__title">Архив</h2>
            <p class="ap-muted small u-m0 u-mt-1">Архивный курс не отображается обучающимся в портале и недоступен для начала обучения. Администраторы могут восстановить курс в любой момент.</p>
            <div class="actions-row u-mt-1">
                @if ($c->is_archived)
                    <form method="post" action="{{ route('admin.courses.unarchive', ['course' => $c->id, 'key' => $adminKey]) }}" class="admin-inline-form">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">Восстановить из архива</button>
                    </form>
                @else
                    <button type="button" class="btn btn-ghost btn-sm" id="ap-course-archive-open">Перенести в архив</button>
                @endif
            </div>
        </div>
    @endif

    @if (! $isCreate && isset($stats) && ! $c->is_archived)
        <form method="post" id="ap-course-archive-form" action="{{ route('admin.courses.archive', ['course' => $c->id, 'key' => $adminKey]) }}" class="ap-hidden-form">
            @csrf
        </form>
        <div class="ap-modal" id="ap-course-archive-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-course-archive-title">
            <div class="ap-modal__backdrop" data-ap-course-archive-close tabindex="-1"></div>
            <div class="ap-modal__panel">
                <button type="button" class="ap-modal__close" data-ap-course-archive-close aria-label="Закрыть">&times;</button>
                <h2 id="ap-course-archive-title" class="ap-modal__title">Перенести курс в архив?</h2>
                <p class="ap-muted">Курс «{{ $c->title }}» пропадёт из портала обучающихся. Администраторы смогут восстановить его позже.</p>
                <div class="ap-modal__footer">
                    <button type="button" class="btn btn-ghost" data-ap-course-archive-close>Отмена</button>
                    <button type="button" class="btn btn-primary btn-archive-confirm" id="ap-course-archive-confirm">В архив</button>
                </div>
            </div>
        </div>
        <script>
            (function () {
                var btn = document.getElementById('ap-course-archive-open');
                var modal = document.getElementById('ap-course-archive-modal');
                var form = document.getElementById('ap-course-archive-form');
                var confirmBtn = document.getElementById('ap-course-archive-confirm');
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
                    document.body.classList.remove('ap-modal-open');
                }
                if (btn) btn.addEventListener('click', openM);
                document.querySelectorAll('[data-ap-course-archive-close]').forEach(function (el) {
                    el.addEventListener('click', function (e) {
                        if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                        closeM();
                    });
                });
                if (confirmBtn && form) confirmBtn.addEventListener('click', function () { form.submit(); });
                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape' || !modal || !modal.classList.contains('is-open')) return;
                    closeM();
                    e.preventDefault();
                });
            })();
        </script>
    @endif
    </div>
@endsection

