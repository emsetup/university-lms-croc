{{-- Форма «О курсе» (вкладка ?tab=kurs). Ожидает: $course, $courseStatus, $builtImages, $finalQuestionCount, $tp, $dockerLibraryUrl, $finalEditUrl --}}
<form id="course-settings-form" method="post" action="{{ route('admin.course.settings.save', $tp) }}" class="ap-course-settings-form">
    @csrf
    <input type="hidden" name="redirect_tab" value="kurs">

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

                @if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'audience_plaque_enabled'))
                    @php
                        $audienceOn = (string) old('audience_plaque_enabled', ($course->audience_plaque_enabled ?? false) ? '1' : '0') === '1';
                    @endphp
                    <div class="ap-settings-field" style="margin-top:1.25rem">
                        <span class="ap-settings-label">Плашка «О курсе» на дашборде</span>
                        <p class="ap-settings-hint ap-muted">Зелёная строка над треком модулей: для кого материал и ссылка «Подробнее». У каждого курса — свой текст.</p>
                        <div class="ap-toggle-row">
                            <label class="ap-toggle">
                                <input type="hidden" name="audience_plaque_enabled" value="0">
                                <input type="checkbox" name="audience_plaque_enabled" value="1" class="ap-toggle__input" id="audience-plaque-enabled" @if ($audienceOn) checked @endif>
                                <span class="ap-toggle__track" aria-hidden="true"></span>
                                <span class="ap-toggle__label">Показывать плашку</span>
                            </label>
                        </div>
                        <div id="audience-plaque-fields" @if (! $audienceOn) hidden @endif>
                            <label class="ap-settings-label" for="audience-plaque-kicker" style="margin-top:0.75rem">Подпись сверху</label>
                            <input id="audience-plaque-kicker" class="ap-modal__input ap-settings-input" type="text" name="audience_plaque_kicker" maxlength="80" value="{{ old('audience_plaque_kicker', $course->audience_plaque_kicker ?? 'О курсе') }}" placeholder="О курсе">
                            <label class="ap-settings-label" for="audience-plaque-title" style="margin-top:0.75rem">Заголовок</label>
                            <input id="audience-plaque-title" class="ap-modal__input ap-settings-input" type="text" name="audience_plaque_title" maxlength="200" value="{{ old('audience_plaque_title', $course->audience_plaque_title ?? 'Для кого этот материал') }}" placeholder="Для кого этот материал">
                            <label class="ap-settings-label" for="audience-plaque-teaser" style="margin-top:0.75rem">Краткий текст на плашке</label>
                            <textarea id="audience-plaque-teaser" class="ap-modal__input ap-settings-textarea" name="audience_plaque_teaser" rows="3" maxlength="2000" placeholder="Одна-две фразы — видны на дашборде">{{ old('audience_plaque_teaser', $course->audience_plaque_teaser) }}</textarea>
                            <label class="ap-settings-label" for="audience-plaque-body" style="margin-top:0.75rem">Полное описание (модальное окно)</label>
                            <textarea id="audience-plaque-body" class="ap-modal__input ap-settings-textarea" name="audience_plaque_body" rows="8" maxlength="20000" placeholder="Markdown: **жирный**, абзацы через пустую строку">{{ old('audience_plaque_body', $course->audience_plaque_body) }}</textarea>
                            <p class="ap-muted small ap-settings-hint">Если полное описание пустое, кнопка «Подробнее» не показывается.</p>
                        </div>
                    </div>
                @endif

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
                    <p class="ap-settings-hint ap-muted">Пусто = без ограничения по времени для разделов, которые наследуют настройки курса.</p>
                </div>

                <div class="ap-settings-inline">
                    <label class="ap-settings-label" for="def-pass">Проходной балл</label>
                    <div class="ap-settings-inline__row">
                        <input id="def-pass" class="ap-modal__input ap-settings-input ap-settings-input--num" type="number" name="default_pass_percent" min="1" max="100" value="{{ old('default_pass_percent', $course->default_pass_percent) }}" placeholder="—">
                        <span class="ap-settings-suffix">%</span>
                    </div>
                </div>

                @php
                    $diffOn = true;
                    if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'difficulty_flags_enabled')) {
                        $diffOn = (string) old('difficulty_flags_enabled', ($course->difficulty_flags_enabled ?? true) ? '1' : '0') === '1';
                    }
                @endphp
                @if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'difficulty_flags_enabled'))
                    <div class="ap-settings-field" style="margin-top:1rem">
                        <span class="ap-settings-label">Блок «Сложности по этапам»</span>
                        <div class="ap-toggle-row">
                            <label class="ap-toggle">
                                <input type="checkbox" name="difficulty_flags_enabled" value="1" class="ap-toggle__input" id="difficulty-flags-enabled" @if ($diffOn) checked @endif>
                                <span class="ap-toggle__track" aria-hidden="true"></span>
                                <span class="ap-toggle__label">Показывать обучающимся</span>
                            </label>
                        </div>
                    </div>
                @endif

                @php
                    $unlockAllOn = false;
                    $showProgressOn = true;
                    if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'unlock_all_modules')) {
                        $unlockAllOn = (string) old('unlock_all_modules', ($course->unlock_all_modules ?? false) ? '1' : '0') === '1';
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'show_module_progress')) {
                        $showProgressOn = (string) old('show_module_progress', ($course->show_module_progress ?? true) ? '1' : '0') === '1';
                    }
                @endphp
                @if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'unlock_all_modules'))
                    <div class="ap-settings-field" style="margin-top:1rem">
                        <span class="ap-settings-label">Доступ к модулям</span>
                        <p class="ap-settings-hint ap-muted">По умолчанию следующий модуль открывается после попытки итогового теста предыдущего. Включите, если нужен свободный доступ к модулям и разделам внутри них — без цепочки этапов и без шкал прохождения на дашборде и в хабе модуля.</p>
                        <div class="ap-toggle-row">
                            <label class="ap-toggle">
                                <input type="checkbox" name="unlock_all_modules" value="1" class="ap-toggle__input" id="unlock-all-modules" @if ($unlockAllOn) checked @endif>
                                <span class="ap-toggle__track" aria-hidden="true"></span>
                                <span class="ap-toggle__label">Все модули доступны сразу</span>
                            </label>
                        </div>
                    </div>
                @endif
                @if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'show_module_progress'))
                    <div class="ap-settings-field" style="margin-top:1rem">
                        <span class="ap-settings-label">Прогресс на дашборде</span>
                        <p class="ap-settings-hint ap-muted">Сводка «Ваш прогресс по модулям», полоски на карточках и шкалы этапов в хабе модуля. При включённом «Все модули доступны сразу» шкалы скрываются автоматически, даже если этот переключатель включён.</p>
                        <div class="ap-toggle-row">
                            <label class="ap-toggle">
                                <input type="hidden" name="show_module_progress" value="0">
                                <input type="checkbox" name="show_module_progress" value="1" class="ap-toggle__input" id="show-module-progress" @if ($showProgressOn) checked @endif>
                                <span class="ap-toggle__track" aria-hidden="true"></span>
                                <span class="ap-toggle__label">Показывать прогресс по модулям</span>
                            </label>
                        </div>
                    </div>
                @endif
                @php
                    $showScorePercentsOn = true;
                    $showScorePointsOn = true;
                    if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'show_score_percents')) {
                        $showScorePercentsOn = (string) old('show_score_percents', ($course->show_score_percents ?? true) ? '1' : '0') === '1';
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'show_score_points')) {
                        $showScorePointsOn = (string) old('show_score_points', ($course->show_score_points ?? true) ? '1' : '0') === '1';
                    }
                @endphp
                @if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'show_score_percents') || \Illuminate\Support\Facades\Schema::hasColumn('courses', 'show_score_points') || \Illuminate\Support\Facades\Schema::hasColumn('courses', 'quiz_breakdown_mode'))
                    <div class="ap-settings-field" style="margin-top:1rem">
                        <span class="ap-settings-label">Метрики в тестах</span>
                        <p class="ap-settings-hint ap-muted">Что видят обучающиеся в тестах, на хабе модуля и в сводке. Модуль и раздел могут переопределить эти настройки. Учительские отчёты не меняются.</p>
                        @if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'show_score_percents'))
                            <div class="ap-toggle-row">
                                <label class="ap-toggle">
                                    <input type="hidden" name="show_score_percents" value="0">
                                    <input type="checkbox" name="show_score_percents" value="1" class="ap-toggle__input" id="show-score-percents" @if ($showScorePercentsOn) checked @endif>
                                    <span class="ap-toggle__track" aria-hidden="true"></span>
                                    <span class="ap-toggle__label">Показывать проценты</span>
                                </label>
                            </div>
                            <p class="ap-muted small ap-settings-hint">Итог в %, порог, штрафы в п.п., проценты на шагах модуля.</p>
                        @endif
                        @if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'show_score_points'))
                            <div class="ap-toggle-row" style="margin-top:0.5rem">
                                <label class="ap-toggle">
                                    <input type="hidden" name="show_score_points" value="0">
                                    <input type="checkbox" name="show_score_points" value="1" class="ap-toggle__input" id="show-score-points" @if ($showScorePointsOn) checked @endif>
                                    <span class="ap-toggle__track" aria-hidden="true"></span>
                                    <span class="ap-toggle__label">Показывать баллы</span>
                                </label>
                            </div>
                            <p class="ap-muted small ap-settings-hint">Плашка «Баллы», вес вопросов («N б.»), earned/max на результатах и дашборде.</p>
                        @endif
                        @if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'quiz_breakdown_mode'))
                            @php
                                $bdMode = old('quiz_breakdown_mode', $course->quiz_breakdown_mode ?? 'all');
                                $bdMode = in_array($bdMode, ['all', 'wrongs'], true) ? $bdMode : 'all';
                            @endphp
                            <label class="ap-settings-label" for="quiz-breakdown-mode" style="margin-top:0.85rem">Разбор после теста</label>
                            <select id="quiz-breakdown-mode" class="ap-modal__input" name="quiz_breakdown_mode" style="max-width:22rem">
                                <option value="all" @if ($bdMode === 'all') selected @endif>Все вопросы попытки</option>
                                <option value="wrongs" @if ($bdMode === 'wrongs') selected @endif>Только ошибки и пропуски</option>
                            </select>
                            <p class="ap-muted small ap-settings-hint">Дефолт для всех тестов и экзаменов курса. Модуль и раздел могут переопределить.</p>
                        @endif
                    </div>
                @endif
            </section>

            <section class="ap-settings-card" aria-labelledby="ap-settings-dashboard-extras-h">
                <h2 id="ap-settings-dashboard-extras-h" class="ap-settings-card__title">Итоговые этапы на дашборде</h2>
                <p class="ap-settings-hint ap-muted">Блок «Дальше по курсу» для обучающихся. Отключите лишнее, если курс состоит только из опросов или не предполагает итоговую оценку и сертификат.</p>
                <input type="hidden" name="meta_includes_dashboard_extras" value="1">
                @php
                    $assessmentOn = true;
                    $certDashboardOn = (bool) ($course->certificate_enabled ?? true);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'assessment_enabled')) {
                        $assessmentOn = (string) old('assessment_enabled', ($course->assessment_enabled ?? true) ? '1' : '0') === '1';
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'certificate_enabled')) {
                        $certDashboardOn = (string) old('certificate_enabled', ($course->certificate_enabled ?? true) ? '1' : '0') === '1';
                    }
                @endphp
                @if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'assessment_enabled'))
                    <div class="ap-toggle-row" style="margin-top:0.75rem">
                        <label class="ap-toggle">
                            <input type="hidden" name="assessment_enabled" value="0">
                            <input type="checkbox" name="assessment_enabled" value="1" class="ap-toggle__input" id="assessment-enabled" @if ($assessmentOn) checked @endif>
                            <span class="ap-toggle__track" aria-hidden="true"></span>
                            <span class="ap-toggle__label">Оценка по модулям</span>
                        </label>
                    </div>
                    <p class="ap-muted small ap-settings-hint">Сводная аналитика и карточка «Перейти к оценке» на дашборде.</p>
                @endif
                @if (\Illuminate\Support\Facades\Schema::hasColumn('courses', 'certificate_enabled'))
                    <div class="ap-toggle-row" style="margin-top:0.75rem">
                        <label class="ap-toggle">
                            <input type="hidden" name="certificate_enabled" value="0">
                            <input type="checkbox" name="certificate_enabled" value="1" class="ap-toggle__input" id="certificate-enabled-kurs" @if ($certDashboardOn) checked @endif>
                            <span class="ap-toggle__track" aria-hidden="true"></span>
                            <span class="ap-toggle__label">Итоговая страница и сертификат</span>
                        </label>
                    </div>
                    <p class="ap-muted small ap-settings-hint">Карточка на дашборде и страница сертификата. Уровни и оформление — на вкладке «Сертификат».</p>
                @endif
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

        var audienceToggle = document.getElementById('audience-plaque-enabled');
        var audienceFields = document.getElementById('audience-plaque-fields');
        function syncAudienceFields() {
            if (!audienceToggle || !audienceFields) return;
            audienceFields.hidden = !audienceToggle.checked;
        }
        if (audienceToggle) {
            audienceToggle.addEventListener('change', function () {
                syncAudienceFields();
                refreshDirty();
            });
            syncAudienceFields();
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
