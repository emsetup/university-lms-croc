@extends('layouts.admin')

@section('title', 'Админ: содержимое курса')

@push('styles')
<style>
    .admin-theory-preview-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem;
        visibility: hidden;
        opacity: 0;
        transition: opacity 0.22s ease, visibility 0.22s ease;
        pointer-events: none;
    }
    .admin-theory-preview-modal.is-open {
        visibility: visible;
        opacity: 1;
        pointer-events: auto;
    }
    .admin-theory-preview-modal .course-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.48);
        backdrop-filter: blur(3px);
    }
    .admin-theory-preview-modal .course-modal__panel {
        position: relative;
        z-index: 1;
        max-width: min(980px, 98vw);
        width: 100%;
        max-height: 94vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        background: #fff;
        border-radius: 16px;
        padding: 1rem 1.1rem 0.85rem;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.2);
    }
    .admin-theory-preview-modal .course-modal__close {
        position: absolute;
        top: 0.55rem;
        right: 0.55rem;
        z-index: 2;
        border: none;
        background: transparent;
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
        color: #64748b;
        padding: 0.25rem 0.45rem;
        border-radius: 8px;
    }
    .admin-theory-preview-modal .course-modal__close:hover {
        background: rgba(15, 23, 42, 0.06);
        color: #0f172a;
    }
    .admin-theory-preview-modal__title { margin: 0 0 0.25rem; font-size: 1.15rem; padding-right: 2rem; }
    .admin-theory-preview-modal__hint { margin: 0 0 0.65rem; }
    .admin-theory-preview-modal__iframe {
        flex: 1;
        width: 100%;
        min-height: min(72vh, 720px);
        border: 0;
        border-radius: 10px;
        background: #f8fafc;
    }
    .admin-theory-preview-modal__footer {
        margin-top: 0.65rem;
        padding-top: 0.55rem;
        border-top: 1px solid #e5e7eb;
    }

    .admin-theory-content-page {
        width: 100%;
        max-width: none;
        margin: 0;
    }

    .admin-theory-content-page .admin-theory-content-table.admin-table {
        min-width: 0;
    }

    .admin-theory-content-page .admin-theory-content-table.admin-table thead th {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #9ca3af;
        background: #fff;
        border-bottom: 1px solid var(--border, #e5e7eb);
    }

    .admin-theory-content-page .admin-theory-content-table.admin-table tbody tr:nth-child(even) td {
        background: #fafafa;
    }

    .admin-theory-content-page .admin-theory-content-table.admin-table tbody tr:hover td {
        background: #f0fdf4;
    }

    .admin-theory-content-page .content-table td {
        vertical-align: middle;
        padding: 12px 16px;
        white-space: nowrap;
    }

    .admin-theory-content-page .content-table .module-name {
        white-space: normal;
        max-width: 280px;
        font-weight: 500;
    }

    .admin-theory-content-page .docker-tag {
        font-family: ui-monospace, "JetBrains Mono", SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 12px;
        background: #f3f4f6;
        padding: 2px 6px;
        border-radius: 4px;
        color: #374151;
        display: inline-block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }

    .admin-theory-content-page .docker-meta {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 2px;
        white-space: normal;
    }

    .admin-theory-content-page .content-icon-cell {
        text-align: center;
    }

    .admin-theory-content-page .content-icon-cell > span svg {
        width: 16px;
        height: 16px;
        display: block;
        margin: 0 auto 2px;
    }

    .admin-theory-content-page .content-icon-cell .cell-meta {
        font-size: 11px;
        color: #9ca3af;
        white-space: normal;
        max-width: 140px;
        margin-left: auto;
        margin-right: auto;
    }

    .admin-theory-content-page .content-icon-ok {
        color: #16a34a;
    }

    .admin-theory-content-page .content-icon-muted {
        color: #9ca3af;
    }

    .admin-theory-content-page .btn-admin-content-preview {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .admin-theory-content-page .btn-admin-content-preview .ap-icon {
        flex-shrink: 0;
    }

    .admin-theory-content-page .btn-admin-content-preview:hover:not(:disabled) {
        border-color: #22c55e !important;
    }

    .admin-theory-content-page .content-docker-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        margin-top: 10px;
    }

    .admin-theory-content-page .content-docker-unset {
        font-style: italic;
        color: #9ca3af;
        font-size: 13px;
    }

    .admin-theory-content-page .admin-inline-form {
        display: inline;
        margin: 0;
    }

    .admin-theory-final-lab {
        margin-top: 16px;
    }

    .admin-theory-final-lab__body {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .admin-theory-final-lab__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
</style>
@endpush

@section('content')
    @php
        $isReadOnly = (bool) ($isReadOnly ?? false);
        $adminKey = (string) ($adminKey ?? request()->query('key', ''));
        $rp = array_merge(\App\Support\AdminNavigation::adminCourseRouteParams(), $adminKey !== '' ? ['key' => $adminKey] : []);
    @endphp

    <div class="admin-theory-content-page">
        @if (empty($rows) || count($rows) === 0)
            <div class="empty-state" role="status">
                <p class="empty-state__title">Контент не настроен</p>
                <p class="empty-state__text">
                    Для этого курса пока нет модулей в базе данных, поэтому «Содержимое курса» не сформировано.
                    Перейдите в раздел «Модули» и создайте модули курса (и их разделы).
                </p>
                <p style="margin-top: 1rem;">
                    <a class="btn btn-primary btn-sm" href="{{ route('admin.course.settings', \App\Support\AdminNavigation::adminCourseRouteParams()) }}">Открыть модули</a>
                </p>
            </div>
        @else
            <div class="admin-card admin-card--flush">
                <div class="admin-table-wrap">
                    <table class="admin-table content-table admin-theory-content-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Модуль</th>
                                <th>Теория</th>
                                <th>Тест</th>
                                <th>Практика</th>
                                <th>Итоговый тест</th>
                                <th>Docker</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td class="mono">{{ $r['module'] }}</td>
                                    <td class="module-name">{{ $r['title'] }}</td>
                                    <td class="content-icon-cell">
                                        @php
                                            $__theoryChars = (int) ($r['theory_chars'] ?? 0);
                                        @endphp
                                        @if ($__theoryChars > 0)
                                            <span class="content-icon-ok">@include('partials.ap-icon', ['name' => 'check-circle', 'size' => 'sm'])</span>
                                            <div class="cell-meta">{{ number_format($__theoryChars, 0, ',', ' ') }} симв.</div>
                                            <div style="margin-top:8px;">
                                                <button type="button" class="btn btn-secondary btn-sm btn-admin-content-preview js-admin-content-preview" data-preview-title="Просмотр теории" data-preview-url="{{ route('admin.theory.preview-theory', array_merge($rp, ['module' => $r['module']])) }}">
                                                    @include('partials.ap-icon', ['name' => 'eye', 'size' => 'sm'])
                                                    <span>Просмотр</span>
                                                </button>
                                            </div>
                                        @else
                                            <span class="content-icon-muted">@include('partials.ap-icon', ['name' => 'minus', 'size' => 'sm'])</span>
                                            <div class="cell-meta">0 симв.</div>
                                        @endif
                                    </td>
                                    <td class="content-icon-cell">
                                        @if ($r['theory_quiz_count'] > 0)
                                            <span class="content-icon-ok">@include('partials.ap-icon', ['name' => 'check-circle', 'size' => 'sm'])</span>
                                            <div class="cell-meta">
                                                {{ $r['theory_quiz_count'] }} вопр.@if ($r['theory_quiz_match'] > 0) · {{ $r['theory_quiz_match'] }} сопост.@endif
                                            </div>
                                            <div style="margin-top:8px;">
                                                <button type="button" class="btn btn-secondary btn-sm btn-admin-content-preview js-admin-content-preview" data-preview-title="Просмотр теста по теории" data-preview-url="{{ route('admin.theory.preview-theory-quiz', array_merge($rp, ['module' => $r['module']])) }}">
                                                    @include('partials.ap-icon', ['name' => 'eye', 'size' => 'sm'])
                                                    <span>Просмотр</span>
                                                </button>
                                            </div>
                                        @else
                                            <span class="content-icon-muted">@include('partials.ap-icon', ['name' => 'minus', 'size' => 'sm'])</span>
                                        @endif
                                    </td>
                                    <td class="content-icon-cell">
                                        @if ($r['has_practice'])
                                            <span class="content-icon-ok">@include('partials.ap-icon', ['name' => 'check-circle', 'size' => 'sm'])</span>
                                            @if (($r['practice_summary'] ?? '') !== '')
                                                <div class="cell-meta">{{ $r['practice_summary'] }}</div>
                                            @endif
                                            <div style="margin-top:8px;">
                                                <button type="button" class="btn btn-secondary btn-sm btn-admin-content-preview js-admin-content-preview" data-preview-title="Просмотр практики" data-preview-url="{{ route('admin.theory.preview-practice', array_merge($rp, ['module' => $r['module']])) }}">
                                                    @include('partials.ap-icon', ['name' => 'eye', 'size' => 'sm'])
                                                    <span>Просмотр</span>
                                                </button>
                                            </div>
                                        @else
                                            <span class="content-icon-muted">@include('partials.ap-icon', ['name' => 'minus', 'size' => 'sm'])</span>
                                        @endif
                                    </td>
                                    <td class="content-icon-cell">
                                        @if ($r['exam_count'] > 0)
                                            <span class="content-icon-ok">@include('partials.ap-icon', ['name' => 'check-circle', 'size' => 'sm'])</span>
                                            <div class="cell-meta">
                                                {{ $r['exam_count'] }} вопр. · {{ $r['exam_time_min'] }} мин@if ($r['exam_match'] > 0) · {{ $r['exam_match'] }} сопост.@endif
                                            </div>
                                            <div style="margin-top:8px;">
                                                <button type="button" class="btn btn-secondary btn-sm btn-admin-content-preview js-admin-content-preview" data-preview-title="Просмотр итогового теста" data-preview-url="{{ route('admin.theory.preview-module-exam', array_merge($rp, ['module' => $r['module']])) }}">
                                                    @include('partials.ap-icon', ['name' => 'eye', 'size' => 'sm'])
                                                    <span>Просмотр</span>
                                                </button>
                                            </div>
                                        @else
                                            <span class="content-icon-muted">@include('partials.ap-icon', ['name' => 'minus', 'size' => 'sm'])</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($r['practice_lab_docker_image'])
                                            @php
                                                $ls = $adminLabStates[$r['module']] ?? null;
                                                $st = is_array($imageStatsByImage[$r['practice_lab_docker_image']] ?? null) ? $imageStatsByImage[$r['practice_lab_docker_image']] : null;
                                            @endphp
                                            <span class="docker-tag" title="{{ $r['practice_lab_docker_image'] }}">{{ $r['practice_lab_docker_image'] }}</span>
                                            @if ($st)
                                                <div class="docker-meta">
                                                    {{ $st['size_human'] ?? '—' }}@if (! empty($st['layers_count'])) · {{ (int) $st['layers_count'] }} слоёв@endif
                                                </div>
                                            @endif
                                            @if (! $isReadOnly)
                                                <div class="content-docker-actions">
                                                    @if ($ls && ! empty($ls['lab_id']))
                                                        @if (! empty($ls['terminal_url']))
                                                            <a class="btn btn-secondary btn-sm" href="{{ $ls['terminal_url'] }}" target="_blank" rel="noopener">Открыть</a>
                                                        @endif
                                                        <form method="post" action="{{ route('admin.theory.container.finish', array_merge($rp, ['module' => $r['module']])) }}" class="admin-inline-form">
                                                            @csrf
                                                            <button type="submit" class="btn btn-secondary btn-sm">Завершить</button>
                                                        </form>
                                                    @else
                                                        <form method="post" action="{{ route('admin.theory.container.start', array_merge($rp, ['module' => $r['module']])) }}" class="admin-inline-form">
                                                            @csrf
                                                            <button type="submit" class="btn btn-primary btn-sm">Запустить</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endif
                                        @else
                                            <span class="content-docker-unset">Не задан</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @php
            $__showFinalTheoryAdmin = true;
            if (($selectedCourse ?? null) && \Illuminate\Support\Facades\Schema::hasColumn('courses', 'final_lab_enabled')) {
                $__showFinalTheoryAdmin = (bool) $selectedCourse->final_lab_enabled;
            }
        @endphp
        @if ($__showFinalTheoryAdmin && ! empty($rows) && count($rows) > 0)
            <div class="admin-card admin-theory-final-lab">
                <h2 class="admin-card__title">Финальная лаборатория</h2>
                <div class="admin-theory-final-lab__body">
                    <div class="admin-theory-final-lab__docker">
                        @if (! empty($finalLabDockerImage))
                            @php
                                $fst = is_array($imageStatsByImage[$finalLabDockerImage] ?? null) ? $imageStatsByImage[$finalLabDockerImage] : null;
                            @endphp
                            <span class="docker-tag" title="{{ $finalLabDockerImage }}">{{ $finalLabDockerImage }}</span>
                            @if ($fst)
                                <div class="docker-meta">
                                    {{ $fst['size_human'] ?? '—' }}@if (! empty($fst['layers_count'])) · {{ (int) $fst['layers_count'] }} слоёв@endif
                                </div>
                            @endif
                        @else
                            <span class="content-docker-unset">Не задан</span>
                        @endif
                    </div>
                    <div class="admin-theory-final-lab__actions">
                        <button type="button" class="btn btn-secondary btn-sm btn-admin-content-preview js-admin-content-preview" data-preview-title="Просмотр финальной лабораторной" data-preview-url="{{ route('admin.theory.preview-final-lab', $rp) }}">
                            @include('partials.ap-icon', ['name' => 'eye', 'size' => 'sm'])
                            <span>Просмотр</span>
                        </button>
                        @if (! $isReadOnly && ! empty($finalLabDockerImage))
                            @if ($finalLabState && ! empty($finalLabState['lab_id']))
                                @if (! empty($finalLabState['terminal_url']))
                                    <a class="btn btn-secondary btn-sm" href="{{ $finalLabState['terminal_url'] }}" target="_blank" rel="noopener">Открыть</a>
                                @endif
                                <form method="post" action="{{ route('admin.theory.container.finish', array_merge($rp, ['module' => 10])) }}" class="admin-inline-form">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Завершить</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('admin.theory.container.start', array_merge($rp, ['module' => 10])) }}" class="admin-inline-form">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Запустить</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="course-modal admin-theory-preview-modal" id="admin-theory-preview-modal-root" aria-hidden="true">
        <div class="course-modal__backdrop" data-admin-theory-preview-close tabindex="-1"></div>
        <div class="course-modal__panel admin-theory-preview-modal__panel" id="admin-theory-preview-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-theory-preview-modal-title">
            <button type="button" class="course-modal__close" data-admin-theory-preview-close aria-label="Закрыть">&times;</button>
            <h2 id="admin-theory-preview-modal-title" class="admin-theory-preview-modal__title">Просмотр</h2>
            <p class="ap-muted small admin-theory-preview-modal__hint">Отображение как у обучающегося (Markdown, Mermaid). Прокрутка внутри окна.</p>
            <iframe class="admin-theory-preview-modal__iframe" id="admin-theory-preview-iframe" title="Предпросмотр теории"></iframe>
            <div class="admin-theory-preview-modal__footer">
                <button type="button" class="btn btn-ghost" data-admin-theory-preview-close>Закрыть</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var root = document.getElementById('admin-theory-preview-modal-root');
            var iframe = document.getElementById('admin-theory-preview-iframe');
            if (!root || !iframe) return;

            var lastFocus = null;

            function closeEls() {
                return root.querySelectorAll('[data-admin-theory-preview-close]');
            }

            function openModal(url, title) {
                lastFocus = document.activeElement;
                iframe.setAttribute('src', url);
                if (titleEl) {
                    titleEl.textContent = title || 'Просмотр';
                }
                root.classList.add('is-open');
                root.setAttribute('aria-hidden', 'false');
                document.body.classList.add('course-modal-open');
                var closeBtn = root.querySelector('.course-modal__close');
                if (closeBtn) closeBtn.focus();
            }

            function closeModal() {
                root.classList.remove('is-open');
                root.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('course-modal-open');
                iframe.removeAttribute('src');
                if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
            }

            var titleEl = document.getElementById('admin-theory-preview-modal-title');

            document.querySelectorAll('.js-admin-content-preview').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var url = btn.getAttribute('data-preview-url');
                    var title = btn.getAttribute('data-preview-title') || 'Просмотр';
                    if (url) openModal(url, title);
                });
            });
            closeEls().forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('course-modal__backdrop')) {
                        e.preventDefault();
                    }
                    closeModal();
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && root.classList.contains('is-open')) {
                    closeModal();
                }
            });
        })();
    </script>
@endsection
