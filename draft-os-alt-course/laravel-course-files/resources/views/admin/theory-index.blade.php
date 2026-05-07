@extends('layouts.course')

@section('title', 'Админ: содержимое курса')

@section('content')
    @php($isReadOnly = (bool) ($isReadOnly ?? false))
    <div style="max-width: 1100px; margin: 0 auto">
        @if (! $isReadOnly)
            @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'theory'])
        @endif
        <div class="card">
        <h1 style="margin-top: 0">Содержимое курса (админ)</h1>
        <p class="muted small">Доступ по <code>?key=</code> — ключ преподавателя (<code>TEACHER_REPORT_TOKEN</code>), администратора (<code>COURSE_ADMIN_TOKEN</code>) или отдельный read-only ключ модератора (<code>COURSE_CONTENT_MODERATOR_TOKEN</code>).</p>
        <p class="muted small">Теория в виде <code>@snippet:module_N_theory.md</code> можно править в редакторе. Тесты и практика здесь только для <strong>просмотра</strong> (редактирование — в <code>config/course.php</code> и файлах в <code>config/snippets/</code>).</p>
        <p class="muted small">Если в панели выбран курс с модулями в БД, таблица повторяет их порядок; колонка «#» — номер <strong>пакета контента</strong> (файлы и ключи в конфиге), название строки — из настроек курса.</p>
        @if (! $isReadOnly)
            <p style="margin: 0.5rem 0 1rem">
                <a class="btn btn-primary" href="{{ route('admin.theory.zip', ['key' => $adminKey]) }}">Скачать все module_*_theory.md (ZIP)</a>
            </p>
        @endif
        @if (session('err'))
            <p class="quiz-modal-warn" style="padding:0.65rem 0.85rem;border-radius:6px;margin:0 0 1rem">{{ session('err') }}</p>
        @endif
        @if (session('ok'))
            <p style="padding:0.65rem 0.85rem;border-radius:6px;margin:0 0 1rem;background:rgba(22,101,52,0.1)">{{ session('ok') }}</p>
        @endif
        @if (empty($rows) || count($rows) === 0)
            <div class="card" style="margin:0 0 1rem;border-color:#fde68a;background:#fffbeb">
                <div style="font-weight:800;color:#92400e;margin-bottom:0.25rem">Контент не настроен</div>
                <div class="muted" style="line-height:1.5">
                    Для этого курса пока нет модулей в базе данных, поэтому «Содержимое курса» не сформировано.
                    Перейдите в <a href="{{ route('admin.course.settings', ['key' => $adminKey]) }}">«Настройки»</a> и создайте модули курса (и их разделы).
                </div>
            </div>
        @endif
        <style>
            .theory-admin-table .atc-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.35rem;
                align-items: center;
                margin: 0 0 0.5rem;
            }
            .theory-admin-table .atc-meta {
                font-size: 0.8rem;
                line-height: 1.45;
                color: var(--muted, #5c6b76);
            }
            .theory-admin-table .atc-meta strong {
                color: var(--text, #0f172a);
                font-weight: 600;
            }
        </style>
        @if (!empty($rows) && count($rows) > 0)
        <div style="overflow-x:auto">
            <table class="teacher-report-table theory-admin-table" style="width:100%;min-width:1040px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Модуль</th>
                        <th>Теория</th>
                        <th>Тест по теории</th>
                        <th>Практика</th>
                        <th>Итоговый тест</th>
                        <th>Docker-практика</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td class="teacher-report-nowrap">{{ $r['module'] }}</td>
                            <td>{{ $r['title'] }}</td>
                            <td style="vertical-align:top;font-size:0.85rem">
                                <div class="atc-actions">
                                    @if ($r['theory_chars'] > 0)
                                        <button type="button" class="btn btn-ghost js-admin-content-preview" style="padding:0.25rem 0.55rem;font-size:0.85rem" data-preview-title="Просмотр теории" data-preview-url="{{ route('admin.theory.preview-theory', ['module' => $r['module'], 'key' => $adminKey]) }}">Просмотр</button>
                                    @endif
                                    @if (! $isReadOnly && $r['editable'])
                                        <a class="btn btn-ghost" style="padding:0.25rem 0.55rem;font-size:0.85rem" href="{{ route('admin.theory.edit', ['module' => $r['module'], 'key' => $adminKey]) }}">Редактор MD</a>
                                    @else
                                        <span class="muted" style="font-size:0.85rem">{{ $isReadOnly ? 'редактирование отключено' : 'встроено в конфиг' }}</span>
                                    @endif
                                </div>
                                <div class="atc-meta">
                                    <div style="word-break:break-all">{{ \Illuminate\Support\Str::limit($r['ref'], 48) }}</div>
                                    <div style="margin-top:0.2rem">Текст теории:</div>
                                    <div><strong>{{ number_format($r['theory_chars'], 0, ',', ' ') }}</strong> симв.</div>
                                </div>
                            </td>
                            <td class="teacher-report-nowrap" style="vertical-align:top;font-size:0.9rem">
                                @if ($r['theory_quiz_count'] > 0)
                                    <div class="atc-actions">
                                        <button type="button" class="btn btn-ghost js-admin-content-preview" style="padding:0.25rem 0.55rem;font-size:0.85rem" data-preview-title="Просмотр теста по теории" data-preview-url="{{ route('admin.theory.preview-theory-quiz', ['module' => $r['module'], 'key' => $adminKey]) }}">Просмотр</button>
                                    </div>
                                    <div class="atc-meta">
                                        {{ $r['theory_quiz_count'] }} вопр.
                                        @if ($r['theory_quiz_match'] > 0)
                                            <span> ({{ $r['theory_quiz_match'] }} сопост.)</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td style="vertical-align:top;font-size:0.85rem">
                                @if ($r['has_practice'])
                                    <div class="atc-actions">
                                        <button type="button" class="btn btn-ghost js-admin-content-preview" style="padding:0.25rem 0.55rem;font-size:0.85rem" data-preview-title="Просмотр практики" data-preview-url="{{ route('admin.theory.preview-practice', ['module' => $r['module'], 'key' => $adminKey]) }}">Просмотр</button>
                                    </div>
                                    <div class="atc-meta">{{ $r['practice_summary'] }}</div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td class="teacher-report-nowrap" style="vertical-align:top;font-size:0.9rem">
                                @if ($r['exam_count'] > 0)
                                    <div class="atc-actions">
                                        <button type="button" class="btn btn-ghost js-admin-content-preview" style="padding:0.25rem 0.55rem;font-size:0.85rem" data-preview-title="Просмотр итогового теста" data-preview-url="{{ route('admin.theory.preview-module-exam', ['module' => $r['module'], 'key' => $adminKey]) }}">Просмотр</button>
                                    </div>
                                    <div class="atc-meta">
                                        {{ $r['exam_count'] }} вопр.
                                        <span>· {{ $r['exam_time_min'] }} мин</span>
                                        @if ($r['exam_match'] > 0)
                                            <span> ({{ $r['exam_match'] }} сопост.)</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td style="vertical-align:top;font-size:0.82rem;max-width:14rem">
                                @if ($r['practice_lab_docker_image'])
                                    @php($ls = $adminLabStates[$r['module']] ?? null)
                                    <code style="word-break:break-all;font-size:0.8rem">{{ $r['practice_lab_docker_image'] }}</code>
                                    @php($st = is_array($imageStatsByImage[$r['practice_lab_docker_image']] ?? null) ? $imageStatsByImage[$r['practice_lab_docker_image']] : null)
                                    @if ($st)
                                        <div class="muted small" style="margin-top:0.25rem;line-height:1.35">
                                            Размер: <strong>{{ $st['size_human'] ?? '—' }}</strong>
                                            @if (! empty($st['layers_count']))
                                                <span>· слоёв: <strong>{{ (int) $st['layers_count'] }}</strong></span>
                                            @endif
                                        </div>
                                    @endif
                                    @if (! $isReadOnly)
                                        <div class="atc-actions" style="margin-top:0.5rem">
                                            @if ($ls && ! empty($ls['lab_id']))
                                                @if (! empty($ls['terminal_url']))
                                                    <a class="btn btn-ghost" href="{{ $ls['terminal_url'] }}" target="_blank" rel="noopener" style="padding:0.25rem 0.55rem;font-size:0.82rem">Открыть</a>
                                                @endif
                                                <form method="post" action="{{ route('admin.theory.container.finish', ['module' => $r['module'], 'key' => $adminKey]) }}" style="margin:0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-ghost" style="padding:0.25rem 0.55rem;font-size:0.82rem">Завершить</button>
                                                </form>
                                            @else
                                                <form method="post" action="{{ route('admin.theory.container.start', ['module' => $r['module'], 'key' => $adminKey]) }}" style="margin:0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-ghost" style="padding:0.25rem 0.55rem;font-size:0.82rem">Запустить</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                @else
                                    <span class="muted">Для этого модуля не задан Docker-образ в <code>config/practice_lab.php</code>.</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;padding:0.75rem 0.9rem;border:1px solid var(--line,#e5e7eb);border-radius:10px;background:#fff">
            <div style="display:flex;flex-wrap:wrap;gap:0.55rem;align-items:center;justify-content:space-between">
                <div>
                    <div style="font-weight:700">Финальная лабораторная</div>
                    <div class="muted small">Практический экзамен по всему курсу (контейнер, чек-лист, итог 100 баллов).</div>
                </div>
                <div class="atc-actions" style="margin:0">
                    <button type="button" class="btn btn-ghost js-admin-content-preview" style="padding:0.25rem 0.55rem;font-size:0.85rem" data-preview-title="Просмотр финальной лабораторной" data-preview-url="{{ route('admin.theory.preview-final-lab', ['key' => $adminKey]) }}">Просмотр</button>
                    @if (! $isReadOnly && ! empty($finalLabDockerImage))
                        @if ($finalLabState && ! empty($finalLabState['lab_id']))
                            @if (! empty($finalLabState['terminal_url']))
                                <a class="btn btn-ghost" href="{{ $finalLabState['terminal_url'] }}" target="_blank" rel="noopener" style="padding:0.25rem 0.55rem;font-size:0.82rem">Открыть</a>
                            @endif
                            <form method="post" action="{{ route('admin.theory.container.finish', ['module' => 10, 'key' => $adminKey]) }}" style="margin:0">
                                @csrf
                                <button type="submit" class="btn btn-ghost" style="padding:0.25rem 0.55rem;font-size:0.82rem">Завершить</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.theory.container.start', ['module' => 10, 'key' => $adminKey]) }}" style="margin:0">
                                @csrf
                                <button type="submit" class="btn btn-ghost" style="padding:0.25rem 0.55rem;font-size:0.82rem">Запустить</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
            @if (! empty($finalLabDockerImage))
                <div class="muted small" style="margin-top:0.45rem">Образ: <code>{{ $finalLabDockerImage }}</code></div>
                @php($fst = is_array($imageStatsByImage[$finalLabDockerImage] ?? null) ? $imageStatsByImage[$finalLabDockerImage] : null)
                @if ($fst)
                    <div class="muted small" style="margin-top:0.25rem;line-height:1.35">
                        Размер: <strong>{{ $fst['size_human'] ?? '—' }}</strong>
                        @if (! empty($fst['layers_count']))
                            <span>· слоёв: <strong>{{ (int) $fst['layers_count'] }}</strong></span>
                        @endif
                    </div>
                @endif
            @endif
        </div>
        <p class="muted small" style="margin-top: 1rem">Прямой адрес списка: <code>/adm/kurs-teoriya?key=…</code> (историческое имя пути сохранено). Общая панель: <code>/adm?key=…</code>.</p>
        </div>
    </div>
        @endif

    <div class="course-modal admin-theory-preview-modal" id="admin-theory-preview-modal-root" aria-hidden="true">
        <div class="course-modal__backdrop" data-admin-theory-preview-close tabindex="-1"></div>
        <div class="course-modal__panel admin-theory-preview-modal__panel" id="admin-theory-preview-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-theory-preview-modal-title">
            <button type="button" class="course-modal__close" data-admin-theory-preview-close aria-label="Закрыть">&times;</button>
            <h2 id="admin-theory-preview-modal-title" class="admin-theory-preview-modal__title">Просмотр</h2>
            <p class="muted small admin-theory-preview-modal__hint">Отображение как у обучающегося (Markdown, Mermaid). Прокрутка внутри окна.</p>
            <iframe class="admin-theory-preview-modal__iframe" id="admin-theory-preview-iframe" title="Предпросмотр теории" style="width:100%;min-height:min(72vh,720px);border:0;border-radius:10px;background:var(--surface,#f8fafc)"></iframe>
            <div class="admin-theory-preview-modal__footer">
                <button type="button" class="btn btn-ghost" data-admin-theory-preview-close>Закрыть</button>
            </div>
        </div>
    </div>

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
            color: var(--muted, #64748b);
            padding: 0.25rem 0.45rem;
            border-radius: 8px;
        }
        .admin-theory-preview-modal .course-modal__close:hover {
            background: rgba(15, 23, 42, 0.06);
            color: var(--text, #0f172a);
        }
        .admin-theory-preview-modal__title { margin: 0 0 0.25rem; font-size: 1.15rem; padding-right: 2rem; }
        .admin-theory-preview-modal__hint { margin: 0 0 0.65rem; }
        .admin-theory-preview-modal__iframe { flex: 1; min-height: min(72vh, 720px); }
        .admin-theory-preview-modal__footer { margin-top: 0.65rem; padding-top: 0.55rem; border-top: 1px solid var(--line, #e5e7eb); }
    </style>
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
