{{-- Форма «О курсе» (вкладка ?tab=kurs). Ожидает: $course, $courseStatus, $builtImages, $finalQuestionCount, $tp, $dockerLibraryUrl, $finalEditUrl --}}
<form id="course-settings-form" method="post" action="{{ route('admin.course.settings.save', $tp) }}" class="ap-course-settings-form">
    @csrf

    <div class="ap-course-settings-grid">
        <div class="ap-settings-col">
            <section class="ap-settings-card" aria-labelledby="ap-settings-basic-h">
                <h2 id="ap-settings-basic-h" class="ap-settings-card__title">Основное</h2>

                <label class="ap-settings-label" for="course-title">Название курса</label>
                <input id="course-title" class="ap-modal__input ap-settings-input" type="text" name="title" required maxlength="200" value="{{ old('title', $course->title) }}">

                <label class="ap-settings-label" for="course-slug">Slug</label>
                <input id="course-slug" class="ap-modal__input ap-settings-input" type="text" name="slug" required maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="{{ old('slug', $course->slug) }}">
                <p class="ap-settings-hint ap-muted">Используется в URL</p>

                <label class="ap-settings-label" for="course-summary">Описание</label>
                <textarea id="course-summary" class="ap-modal__input ap-settings-textarea" name="summary" rows="5" maxlength="5000">{{ old('summary', $course->summary) }}</textarea>

                <p class="ap-settings-label" style="margin-top:1rem">Статус</p>
                <div class="ap-status-cards" role="radiogroup" aria-label="Статус публикации">
                    @php $st = old('course_status', $courseStatus); @endphp
                    <label class="ap-status-card">
                        <input type="radio" name="course_status" value="draft" class="ap-status-card__input" @if ($st === 'draft') checked @endif>
                        <span class="ap-status-card__title">Черновик</span>
                        <span class="ap-status-card__desc ap-muted">Не виден обучающимся в каталоге</span>
                    </label>
                    <label class="ap-status-card">
                        <input type="radio" name="course_status" value="published" class="ap-status-card__input" @if ($st === 'published') checked @endif>
                        <span class="ap-status-card__title">Опубликован</span>
                        <span class="ap-status-card__desc ap-muted">Доступен по правилам портала</span>
                    </label>
                    <label class="ap-status-card">
                        <input type="radio" name="course_status" value="archived" class="ap-status-card__input" @if ($st === 'archived') checked @endif>
                        <span class="ap-status-card__title">Архив</span>
                        <span class="ap-status-card__desc ap-muted">Скрыт, публикация снята</span>
                    </label>
                </div>
            </section>
        </div>

        <div class="ap-settings-col ap-settings-col--stack">
            <section class="ap-settings-card" aria-labelledby="ap-settings-defaults-h">
                <h2 id="ap-settings-defaults-h" class="ap-settings-card__title">Настройки по умолчанию</h2>
                <p class="ap-settings-sub ap-muted">Применяются ко всем разделам если не переопределены</p>

                <div class="ap-settings-inline">
                    <label class="ap-settings-label" for="def-attempts">Попытки</label>
                    <div class="ap-settings-inline__row">
                        <input id="def-attempts" class="ap-modal__input ap-settings-input ap-settings-input--num" type="number" name="default_attempt_limit" min="1" max="99" value="{{ old('default_attempt_limit', $course->default_attempt_limit) }}" placeholder="—">
                        <span class="ap-settings-suffix">раз</span>
                    </div>
                </div>

                <div class="ap-settings-inline">
                    <label class="ap-settings-label" for="def-time">Время на тест</label>
                    <div class="ap-settings-inline__row">
                        <input id="def-time" class="ap-modal__input ap-settings-input ap-settings-input--num" type="number" name="default_quiz_time_minutes" min="1" max="600" value="{{ old('default_quiz_time_minutes', $course->default_quiz_time_minutes) }}" placeholder="—">
                        <span class="ap-settings-suffix">мин</span>
                    </div>
                </div>

                <div class="ap-settings-inline">
                    <label class="ap-settings-label" for="def-pass">Проходной балл</label>
                    <div class="ap-settings-inline__row">
                        <input id="def-pass" class="ap-modal__input ap-settings-input ap-settings-input--num" type="number" name="default_pass_percent" min="1" max="100" value="{{ old('default_pass_percent', $course->default_pass_percent) }}" placeholder="—">
                        <span class="ap-settings-suffix">%</span>
                    </div>
                </div>
            </section>

            <section class="ap-settings-card" aria-labelledby="ap-settings-final-h">
                <h2 id="ap-settings-final-h" class="ap-settings-card__title">Финальная лаборатория</h2>
                @php
                    $finalLabOn = (string) old('final_lab_enabled', $course->final_lab_enabled ? '1' : '0') === '1';
                @endphp

                <div class="ap-toggle-row">
                    <label class="ap-toggle">
                        <input type="checkbox" name="final_lab_enabled" value="1" class="ap-toggle__input" id="final-lab-enabled" @if ($finalLabOn) checked @endif>
                        <span class="ap-toggle__track" aria-hidden="true"></span>
                        <span class="ap-toggle__label">Включена для курса</span>
                    </label>
                </div>

                <div id="final-lab-details" class="ap-final-lab-details" @if (! $finalLabOn) hidden @endif>
                    <div class="ap-settings-field">
                        <span class="ap-settings-label">Docker-образ</span>
                        @if ($course->finalLabPracticeImage)
                            <p class="ap-settings-pill">
                                <strong>{{ $course->finalLabPracticeImage->title }}</strong>
                                <code class="ap-muted">{{ $course->finalLabPracticeImage->docker_tag }}</code>
                            </p>
                        @else
                            <p class="ap-muted ap-settings-mb0">Образ не выбран</p>
                        @endif
                        <div class="ap-settings-actions">
                            <a class="btn btn-ghost" href="{{ $dockerLibraryUrl }}" target="_blank" rel="noopener">Выбрать из библиотеки</a>
                        </div>
                        <label class="ap-settings-label ap-muted small" for="final-lab-image">Привязать собранный образ</label>
                        <select id="final-lab-image" name="final_lab_practice_image_id" class="ap-modal__input ap-settings-input">
                            <option value="">— не выбран —</option>
                            @foreach ($builtImages as $im)
                                <option value="{{ $im->id }}" @if ((int) old('final_lab_practice_image_id', $course->final_lab_practice_image_id) === (int) $im->id) selected @endif>
                                    {{ $im->title }} ({{ $im->docker_tag }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ap-settings-field ap-settings-field--bordered">
                        <div class="ap-settings-row-between">
                            <div>
                                <span class="ap-settings-label ap-settings-mb0">Вопросы финальной страницы</span>
                                <p class="ap-muted ap-settings-mb0" style="margin-top:0.25rem">{{ $finalQuestionCount }} вопросов</p>
                            </div>
                            <button type="button" class="btn btn-primary" id="open-final-questions-drawer" style="display:inline-flex;align-items:center;gap:0.35rem">Редактировать <span aria-hidden="true">@include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])</span></button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</form>

<div id="course-settings-save-bar" class="ap-settings-save-bar" hidden>
    <div class="ap-settings-save-bar__inner">
        <span class="ap-muted">Есть несохранённые изменения</span>
        <button type="submit" form="course-settings-form" class="btn btn-primary">Сохранить настройки</button>
    </div>
</div>

<aside id="course-final-questions-drawer" class="ap-drawer" aria-hidden="true" aria-label="Редактор вопросов финальной страницы">
    <div class="ap-drawer__backdrop" data-drawer-close tabindex="-1"></div>
    <div class="ap-drawer__panel" role="dialog" aria-modal="true">
        <div class="ap-drawer__head">
            <span class="ap-drawer__title">Вопросы финальной страницы</span>
            <button type="button" class="btn btn-ghost ap-drawer__close" data-drawer-close>Закрыть</button>
        </div>
        <iframe id="course-final-questions-frame" class="ap-drawer__iframe" title="Редактор вопросов" data-src="{{ $finalEditUrl }}"></iframe>
    </div>
</aside>

<script>
    (function () {
        var form = document.getElementById('course-settings-form');
        var bar = document.getElementById('course-settings-save-bar');
        var finalToggle = document.getElementById('final-lab-enabled');
        var finalDetails = document.getElementById('final-lab-details');
        var drawer = document.getElementById('course-final-questions-drawer');
        var openBtn = document.getElementById('open-final-questions-drawer');
        var frame = document.getElementById('course-final-questions-frame');
        if (!form || !bar) return;

        function snapshot() {
            var o = {};
            var els = form.querySelectorAll('input, textarea, select');
            els.forEach(function (el) {
                if (!el.name || el.name === '_token') return;
                if (el.type === 'radio' && !el.checked) return;
                if (el.type === 'checkbox') {
                    o[el.name] = el.checked ? el.value : '0';
                    return;
                }
                o[el.name] = el.value;
            });
            return JSON.stringify(o);
        }

        var initial = snapshot();
        function refreshDirty() {
            var dirty = snapshot() !== initial;
            bar.hidden = !dirty;
            window.onbeforeunload = dirty ? function () { return ''; } : null;
        }

        form.addEventListener('input', refreshDirty);
        form.addEventListener('change', refreshDirty);
        form.addEventListener('submit', function () {
            window.onbeforeunload = null;
            initial = snapshot();
            bar.hidden = true;
        });

        function syncFinalDetails() {
            if (!finalToggle || !finalDetails) return;
            finalDetails.hidden = !finalToggle.checked;
        }
        if (finalToggle) {
            finalToggle.addEventListener('change', function () {
                syncFinalDetails();
                refreshDirty();
            });
            syncFinalDetails();
        }

        function closeDrawer() {
            if (!drawer) return;
            drawer.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
        }
        function openDrawer() {
            if (!drawer || !frame) return;
            if (!frame.getAttribute('src') || frame.getAttribute('src') === 'about:blank') {
                var u = frame.getAttribute('data-src');
                if (u) frame.setAttribute('src', u);
            }
            drawer.classList.add('is-open');
            drawer.setAttribute('aria-hidden', 'false');
        }
        if (openBtn) openBtn.addEventListener('click', openDrawer);
        if (drawer) {
            drawer.querySelectorAll('[data-drawer-close]').forEach(function (el) {
                el.addEventListener('click', closeDrawer);
            });
        }
    })();
</script>
