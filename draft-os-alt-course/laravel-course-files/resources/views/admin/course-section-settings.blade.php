@extends('layouts.admin')

@section('title', 'Настройки раздела: '.$section->title)

@section('content')
    @php
        $rp = array_merge(\App\Support\AdminNavigation::adminCourseRouteParams(), ($adminKey ?? '') !== '' ? ['key' => $adminKey] : []);
    @endphp
    <div class="ap-narrow-page">
        <div class="admin-card">
            <p class="u-m0"><a class="btn btn-ghost btn-sm" href="{{ route('admin.course.module.sections', array_merge($rp, ['courseModule' => $courseModule->id])) }}">Разделы модуля</a></p>
            <h1 class="practice-page__title u-mt-1">{{ $section->title }}</h1>
            <p class="ap-muted small u-m0 u-mt-1">Тип: <strong>{{ $section->type }}</strong></p>
            <form method="post" action="{{ route('admin.course.module.sections.update', array_merge($rp, ['courseModule' => $courseModule->id, 'section' => $section->id])) }}" class="form-row u-mt-1">
                @csrf
                <div class="form-field">
                    <label class="form-label" for="sec-rename-title">Название</label>
                    <input id="sec-rename-title" type="text" name="title" value="{{ $section->title }}" maxlength="200" required class="form-input">
                </div>
                <button type="submit" class="btn btn-secondary btn-sm">Переименовать</button>
            </form>
        </div>

        <div class="admin-card">
            <h2 class="admin-card__title">Параметры</h2>
            <form method="post" action="{{ route('admin.course.module.section.settings.save', array_merge($rp, ['courseModule' => $courseModule->id, 'section' => $section->id])) }}">
                @csrf

                @if ($section->type === 'text')
                    <p class="ap-muted small">Текст теории по-прежнему в Markdown (<span class="mono">config/snippets</span> / редактор «Содержимое курса»).</p>
                    <div class="form-stack u-mt-1">
                        <label class="form-label" for="min_read_seconds">Мин. время на странице (сек), 0 = не ограничивать</label>
                        <input id="min_read_seconds" type="number" name="min_read_seconds" value="{{ (int) ($settings['min_read_seconds'] ?? 0) }}" min="0" max="86400" class="form-input form-input--md">
                        <label class="form-label" for="time_limit_minutes_text">Лимит времени на просмотр (мин), пусто = без лимита</label>
                        <input id="time_limit_minutes_text" type="number" name="time_limit_minutes" value="{{ $settings['time_limit_minutes'] ?? '' }}" min="0" max="600" placeholder="—" class="form-input form-input--md">
                    </div>
                @elseif ($section->type === 'quiz')
                    <div class="form-stack">
                        <label class="form-label" for="time_limit_minutes_quiz">Лимит времени на попытку (мин), пусто = без лимита</label>
                        <input id="time_limit_minutes_quiz" type="number" name="time_limit_minutes" value="{{ $settings['time_limit_minutes'] ?? '' }}" min="1" max="600" placeholder="—" class="form-input form-input--md">
                        <label class="form-label" for="attempt_limit_quiz">Макс. число попыток (пусто = без лимита)</label>
                        <input id="attempt_limit_quiz" type="number" name="attempt_limit" value="{{ $settings['attempt_limit'] ?? '' }}" min="1" max="50" placeholder="∞" class="form-input form-input--md">
                        <label class="form-label" for="pass_percent_quiz">Порог зачёта (%)</label>
                        <input id="pass_percent_quiz" type="number" name="pass_percent" value="{{ (int) ($settings['pass_percent'] ?? 70) }}" min="1" max="100" required class="form-input form-input--md">
                        <label class="ap-check-row u-mt-1">
                            <input type="hidden" name="shuffle" value="0">
                            <input type="checkbox" name="shuffle" value="1" @if (! empty($settings['shuffle'])) checked @endif>
                            <span class="ap-muted small">Перемешивать вопросы при каждой попытке</span>
                        </label>
                        @php
                            $bvQuiz = (int) ($settings['breakdown_visible_minutes'] ?? 15);
                            $bvQuizUnlimited = $bvQuiz < 0;
                        @endphp
                        <label class="ap-check-row u-mt-1">
                            <input type="hidden" name="breakdown_unlimited" value="0">
                            <input type="checkbox" name="breakdown_unlimited" value="1" id="breakdown_unlimited_quiz" @if ($bvQuizUnlimited) checked @endif
                                   onchange="document.getElementById('breakdown_visible_minutes_quiz').disabled=this.checked">
                            <span class="ap-muted small">Разбор ошибок без ограничения по времени</span>
                        </label>
                        <label class="form-label" for="breakdown_visible_minutes_quiz">Минут видимости разбора после попытки (0 = не показывать)</label>
                        <input id="breakdown_visible_minutes_quiz" type="number" name="breakdown_visible_minutes" value="{{ $bvQuizUnlimited ? 15 : $bvQuiz }}" min="0" max="10080" class="form-input form-input--md" @if ($bvQuizUnlimited) disabled @endif>
                        <p class="ap-muted small u-m0">Удобно для тренировочных тестов не за баллы / без пошагового режима — обучающийся может спокойно разобрать ошибки.</p>
                        @php $pen = is_array($settings['penalties'] ?? null) ? $settings['penalties'] : []; @endphp
                        <p class="form-label u-mt-1 u-m0">Штраф к сырому % (п.п.) по номеру попытки</p>
                        <div class="penalty-grid u-mt-1">
                            <div>
                                <span class="ap-muted small">2-я</span>
                                <input type="number" name="penalty_attempt_2" value="{{ $pen['2'] ?? '' }}" min="0" max="100" placeholder="10" class="form-input form-input--xs">
                            </div>
                            <div>
                                <span class="ap-muted small">3-я</span>
                                <input type="number" name="penalty_attempt_3" value="{{ $pen['3'] ?? '' }}" min="0" max="100" placeholder="—" class="form-input form-input--xs">
                            </div>
                            <div>
                                <span class="ap-muted small">4-я</span>
                                <input type="number" name="penalty_attempt_4" value="{{ $pen['4'] ?? '' }}" min="0" max="100" placeholder="—" class="form-input form-input--xs">
                            </div>
                        </div>
                    </div>
                @elseif ($section->type === 'practice')
                    <p class="ap-muted small">Содержимое практики — в Markdown и Docker-образах. Здесь — опциональные лимиты.</p>
                    <div class="form-stack u-mt-1">
                        <label class="form-label" for="attempt_limit_pr">Макс. попыток (пусто = без лимита)</label>
                        <input id="attempt_limit_pr" type="number" name="attempt_limit" value="{{ $settings['attempt_limit'] ?? '' }}" min="1" max="50" placeholder="—" class="form-input form-input--md">
                        <label class="form-label" for="time_limit_minutes_pr">Лимит времени (мин), пусто = нет</label>
                        <input id="time_limit_minutes_pr" type="number" name="time_limit_minutes" value="{{ $settings['time_limit_minutes'] ?? '' }}" min="0" max="10080" placeholder="—" class="form-input form-input--md">
                    </div>
                @elseif ($section->type === 'exam')
                    <div class="form-stack">
                        <label class="form-label" for="time_limit_minutes_ex">Лимит времени на попытку (мин), пусто = без лимита</label>
                        <input id="time_limit_minutes_ex" type="number" name="time_limit_minutes" value="{{ $settings['time_limit_minutes'] ?? '' }}" min="1" max="600" placeholder="—" class="form-input form-input--md">
                        <label class="form-label" for="attempt_limit_ex">Число попыток</label>
                        <input id="attempt_limit_ex" type="number" name="attempt_limit" value="{{ (int) ($settings['attempt_limit'] ?? 2) }}" min="1" max="20" required class="form-input form-input--md">
                        <label class="form-label" for="pass_percent_ex">Порог зачёта (%)</label>
                        <input id="pass_percent_ex" type="number" name="pass_percent" value="{{ (int) ($settings['pass_percent'] ?? 70) }}" min="1" max="100" required class="form-input form-input--md">
                        @php
                            $bvExam = (int) ($settings['breakdown_visible_minutes'] ?? 30);
                            $bvExamUnlimited = $bvExam < 0;
                        @endphp
                        <label class="ap-check-row u-mt-1">
                            <input type="hidden" name="breakdown_unlimited" value="0">
                            <input type="checkbox" name="breakdown_unlimited" value="1" id="breakdown_unlimited_ex" @if ($bvExamUnlimited) checked @endif
                                   onchange="document.getElementById('breakdown_visible_minutes').disabled=this.checked">
                            <span class="ap-muted small">Разбор ошибок без ограничения по времени</span>
                        </label>
                        <label class="form-label" for="breakdown_visible_minutes">Минут видимости разбора после попытки (0 = не показывать)</label>
                        <input id="breakdown_visible_minutes" type="number" name="breakdown_visible_minutes" value="{{ $bvExamUnlimited ? 30 : $bvExam }}" min="0" max="10080" class="form-input form-input--md" @if ($bvExamUnlimited) disabled @endif>
                        <label class="ap-check-row u-mt-1">
                            <input type="hidden" name="one_by_one" value="0">
                            <input type="checkbox" name="one_by_one" value="1" @if (($settings['one_by_one'] ?? true) !== false) checked @endif>
                            <span class="ap-muted small">Вопросы по одному (пошаговый интерфейс экзамена)</span>
                        </label>
                        @php $pen = is_array($settings['penalties'] ?? null) ? $settings['penalties'] : []; @endphp
                        <p class="form-label u-mt-1 u-m0">Штраф к сырому % (п.п.) по номеру попытки</p>
                        <div class="penalty-grid u-mt-1">
                            <div>
                                <span class="ap-muted small">2-я</span>
                                <input type="number" name="penalty_attempt_2" value="{{ $pen['2'] ?? '' }}" min="0" max="100" placeholder="10" class="form-input form-input--xs">
                            </div>
                            <div>
                                <span class="ap-muted small">3-я</span>
                                <input type="number" name="penalty_attempt_3" value="{{ $pen['3'] ?? '' }}" min="0" max="100" placeholder="—" class="form-input form-input--xs">
                            </div>
                            <div>
                                <span class="ap-muted small">4-я</span>
                                <input type="number" name="penalty_attempt_4" value="{{ $pen['4'] ?? '' }}" min="0" max="100" placeholder="—" class="form-input form-input--xs">
                            </div>
                        </div>
                    </div>
                @endif

                <div class="actions-row u-mt-1">
                    <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.course.module.sections', array_merge($rp, ['courseModule' => $courseModule->id])) }}">Отмена</a>
                </div>
            </form>
        </div>
    </div>
@endsection
