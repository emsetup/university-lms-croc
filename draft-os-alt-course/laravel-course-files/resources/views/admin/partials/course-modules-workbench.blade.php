@php
    $tp = $ap ?? ['adminCourse' => $course->slug];
    $rp = array_merge($tp, isset($adminKey) && $adminKey !== '' ? ['key' => $adminKey] : []);
    $canEditCourseMeta = ! empty($canEditCourseMeta);
    $canEditCourseStructure = ! empty($canEditCourseStructure);
    $canPreviewCourse = ! empty($canPreviewCourse);
    $courseIdWorkbench = (int) $course->id;
    if ($portalStaffAccess ?? null) {
        $canEditCourseMeta = $canEditCourseMeta || $portalStaffAccess->canEditCourseMeta($courseIdWorkbench);
        $canEditCourseStructure = $canEditCourseStructure || $portalStaffAccess->canEditCourseStructure($courseIdWorkbench);
        $canPreviewCourse = $canPreviewCourse || $portalStaffAccess->canPreviewCourse($courseIdWorkbench);
    }
    $sectionTypeLabels = [
        'text' => 'Теория',
        'quiz' => 'Тест',
        'practice' => 'Практика',
        'exam' => 'Экзамен',
        'survey' => 'Опрос',
    ];
    $sectionTypeDescriptions = [
        'text' => [
            'title' => 'Теория',
            'text' => 'Текстовый материал модуля: лекция, инструкция или справка в Markdown. Обучающийся читает и отмечает просмотр. На баллы модуля не влияет, но открывает следующие этапы.',
        ],
        'quiz' => [
            'title' => 'Тест по теории',
            'text' => 'Проверка понимания прочитанного: один или несколько вариантов ответа, порог зачёта и таймер. Учитывается в итоге модуля (обычно 25% баллов).',
        ],
        'practice' => [
            'title' => 'Практика',
            'text' => 'Лабораторная работа в Docker-контейнере: задание, стенд и автопроверка. Учитывается в итоге модуля (обычно 25% баллов).',
        ],
        'exam' => [
            'title' => 'Итоговый тест',
            'text' => 'Финальная проверка модуля: вопросы по шагам или на одной странице, в том числе сопоставление и баллы за вопрос. Основной вес в модуле (обычно 50%).',
        ],
        'survey' => [
            'title' => 'Опрос',
            'text' => 'Сбор обратной связи и данных: без оценки и проверки правильности, как просмотр теории. Обучающийся должен отправить ответы, чтобы перейти к следующим разделам.',
        ],
    ];
    $defaultSectionTitles = [
        'text' => 'Теория',
        'quiz' => 'Тест по теории',
        'practice' => 'Практика',
        'exam' => 'Итоговый тест',
        'survey' => 'Опрос',
    ];
    $visibilitySvc = app(\App\Services\LearnerContentVisibilityService::class);
    $learnerSearchUrl = route('admin.course.learners.search', $rp);
    $learnerResolveUrl = route('admin.course.learners.resolve', $rp);
@endphp

<div class="ap-mod-workbench" data-ap-workbench data-ap-csrf="{{ csrf_token() }}">
    <div class="ap-mod-workbench__head">
        <div>
            <h1 class="ap-page-title ap-mod-workbench__title">Модули курса</h1>
            <p class="ap-page-lead ap-muted ap-mod-workbench__lead">Порядок модулей и разделов, пакеты контента и цепочка этапов.</p>
        </div>
        <div class="ap-mod-workbench__head-actions">
            <a class="btn btn-ghost" href="{{ route('admin.quiz.export.all', $tp) }}">Все вопросы (Excel)</a>
            <a class="btn btn-ghost" href="{{ route('admin.quiz.export.all.word', $tp) }}">Все вопросы (Word)</a>
            @if ($canEditCourseStructure)
                <button type="button" class="btn btn-primary" id="ap-open-add-module">+ Добавить модуль</button>
            @else
                <p class="ap-muted small ap-m0">Добавление модулей доступно владельцу курса или соавтору с уровнем «Управление» или «Редактирование» на <strong>весь курс</strong>.</p>
            @endif
            @if ($canEditCourseMeta)
                <button type="submit" form="ap-modules-reorder-form" class="btn btn-ghost ap-mod-workbench__save-order" id="ap-save-module-order" hidden>Сохранить порядок</button>
            @endif
        </div>
    </div>

    <form id="ap-modules-reorder-form" method="post" action="{{ route('admin.course.settings.modules.reorder', $rp) }}" class="ap-mod-workbench__reorder-form">
        @csrf
        <div id="ap-module-order-fields" class="ap-mod-workbench__hidden-fields" aria-hidden="true"></div>
    </form>

    <ul id="ap-modules-sortable" class="ap-mod-list">
        @foreach ($modules as $m)
            @php
                $idx = $loop->iteration;
                $letterDisp = $m->letter ? $m->letter : '—';
                $nSec = $m->sections->count();
            @endphp
            <li id="ap-mod-{{ $m->id }}"
                class="ap-mod-card"
                data-module-id="{{ $m->id }}"
                data-module-title="{{ e($m->title) }}"
                data-module-letter="{{ e($m->letter ?? '') }}"
                data-module-pkg="{{ $m->content_source_index ?? '' }}"
                data-module-summary="{{ e($m->summary ?? '') }}"
                data-module-show-percents="{{ \Illuminate\Support\Facades\Schema::hasColumn('course_modules', 'show_score_percents') ? (($m->getAttributes()['show_score_percents'] ?? null) === null ? 'inherit' : ((string) (int) $m->getAttributes()['show_score_percents'])) : 'inherit' }}"
                data-module-show-points="{{ \Illuminate\Support\Facades\Schema::hasColumn('course_modules', 'show_score_points') ? (($m->getAttributes()['show_score_points'] ?? null) === null ? 'inherit' : ((string) (int) $m->getAttributes()['show_score_points'])) : 'inherit' }}"
                data-update-url="{{ route('admin.course.settings.module.update', array_merge($rp, ['courseModule' => $m->id])) }}"
                data-destroy-url="{{ route('admin.course.settings.module.destroy', array_merge($rp, ['courseModule' => $m->id])) }}"
                data-section-store-url="{{ route('admin.course.module.sections.store', array_merge($rp, ['courseModule' => $m->id])) }}"
                data-section-reorder-url="{{ route('admin.course.module.sections.reorder', array_merge($rp, ['courseModule' => $m->id])) }}">
                <div class="ap-mod-card__main">
                    @if ($canEditCourseMeta)
                    <span class="ap-mod-drag-handle ap-mod-drag-handle--module" title="Перетащить" aria-hidden="true">≡</span>
                    @endif
                    <div class="ap-mod-card__body">
                        <div class="ap-mod-card__title-row">
                            <button type="button" class="ap-mod-card__toggle" data-ap-mod-toggle aria-expanded="false" aria-controls="ap-mod-body-{{ $m->id }}">
                                <span class="ap-mod-card__badges">
                                    <span class="ap-mod-card__badge ap-mod-card__badge--num">[{{ $idx }}]</span>
                                    <span class="ap-mod-card__badge ap-mod-card__badge--letter">[{{ $letterDisp }}]</span>
                                </span>
                                <span class="ap-mod-card__name">{{ $m->title }}</span>
                                <span class="ap-mod-card__chev" data-ap-chev>∨</span>
                            </button>
                            <div class="ap-mod-card__meta">
                                Пакет №{{ $m->effectiveContentIndex() }} · {{ $nSec }} {{ $nSec === 1 ? 'раздел' : ($nSec > 1 && $nSec < 5 ? 'раздела' : 'разделов') }}
                                @php $modAudience = $visibilitySvc->audienceSummaryForResource(\App\Models\ContentViewAudienceRule::RESOURCE_MODULE, (int) $m->id, $courseIdWorkbench); @endphp
                                <span class="ap-mod-card__audience-badge" data-audience-badge-for="mod-{{ $m->id }}" @if (! $modAudience) hidden @endif>{{ $modAudience }}</span>
                            </div>
                        </div>
                        <div class="ap-mod-card__actions">
                            <button type="button" class="btn btn-secondary btn-sm" data-ap-open-sections>Разделы</button>
                            @if ($canPreviewCourse)
                                @include('partials.course-preview-launch', [
                                    'previewUrl' => route('admin.course.preview.module', array_merge($tp, ['module' => $m->effectiveContentIndex()])),
                                    'previewLabel' => 'Предпросмотр',
                                    'previewClass' => 'btn btn-ghost btn-sm',
                                    'previewShowIcon' => true,
                                ])
                            @endif
                            @if ($canEditCourseMeta)
                                <button type="button"
                                        class="btn btn-ghost btn-sm ap-mod-icon-btn"
                                        title="Поделиться модулем"
                                        data-ap-share-link
                                        data-ap-share-meta-url="{{ route('admin.course.share-link.meta', array_merge($rp, ['type' => 'module', 'id' => $m->id])) }}"
                                        aria-label="Поделиться">
                                    @include('partials.ap-icon', ['name' => 'share', 'size' => 'md'])
                                </button>
                            @endif
                            @if ($canEditCourseStructure || ($portalStaffAccess ?? null)?->canEditModuleContent((int) $m->id))
                                <button type="button"
                                        class="btn btn-ghost btn-sm ap-mod-icon-btn"
                                        title="Доступ к модулю"
                                        data-ap-open-audience
                                        data-audience-title="Доступ: {{ $m->title }}"
                                        data-audience-target="mod-{{ $m->id }}"
                                        data-audience-load-url="{{ route('admin.course.module.visibility', array_merge($rp, ['courseModule' => $m->id])) }}"
                                        data-audience-save-url="{{ route('admin.course.module.visibility.update', array_merge($rp, ['courseModule' => $m->id])) }}"
                                        data-audience-search-url="{{ $learnerSearchUrl }}"
                                        data-audience-resolve-url="{{ $learnerResolveUrl }}"
                                        aria-label="Доступ">
                                    @include('partials.ap-icon', ['name' => 'lock', 'size' => 'md'])
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm ap-mod-icon-btn" title="Настройки модуля" data-ap-open-module-settings aria-label="Настройки">
                                    @include('partials.ap-icon', ['name' => 'cog', 'size' => 'md'])
                                </button>
                            @endif
                            @if ($canEditCourseMeta)
                                <button type="button" class="btn btn-ghost btn-sm ap-mod-icon-btn ap-mod-icon-btn--danger" title="Удалить модуль" data-ap-open-delete-module aria-label="Удалить">
                                    @include('partials.ap-icon', ['name' => 'trash', 'size' => 'md'])
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                <div id="ap-mod-body-{{ $m->id }}" class="ap-mod-card__accordion" hidden data-ap-mod-accordion>
                    <div class="ap-mod-card__accordion-inner">
                        <div class="ap-mod-sections-head">
                            @if ($canEditCourseStructure || ($portalStaffAccess ?? null)?->canEditModuleContent((int) $m->id))
                                <button type="button" class="btn btn-primary btn-sm" data-ap-open-add-section>+ Добавить раздел</button>
                                <button type="submit" form="ap-sections-reorder-{{ $m->id }}" class="btn btn-ghost btn-sm" data-ap-save-section-order hidden>Сохранить порядок разделов</button>
                            @endif
                        </div>
                        <form id="ap-sections-reorder-{{ $m->id }}" method="post" action="{{ route('admin.course.module.sections.reorder', array_merge($rp, ['courseModule' => $m->id])) }}" class="ap-sec-reorder-form">
                            @csrf
                            <div class="ap-sec-order-fields" data-ap-sec-order-fields aria-hidden="true"></div>
                        </form>
                        <ul class="ap-sec-list" id="ap-sec-sortable-{{ $m->id }}" data-ap-sec-sortable>
                            @foreach ($m->sections as $sec)
                                <li class="ap-sec-row" data-section-id="{{ $sec->id }}">
                                    <span class="ap-mod-drag-handle ap-mod-drag-handle--sec" title="Перетащить">≡</span>
                                    <span class="ap-mod-card__badge ap-mod-card__badge--num ap-sec-row__num">[{{ $loop->iteration }}]</span>
                                    <span class="ap-sec-chip ap-sec-chip--{{ $sec->type }}">
                                        @php
                                            $secIcon = match ($sec->type) {
                                                'quiz' => 'help-circle',
                                                'practice' => 'terminal',
                                                'exam' => 'clipboard-check',
                                                'survey' => 'pencil',
                                                default => 'file-text',
                                            };
                                        @endphp
                                        @include('partials.ap-icon', ['name' => $secIcon, 'size' => 'sm', 'class' => 'ap-sec-chip__icon'])
                                        <span>{{ $sectionTypeLabels[$sec->type] ?? $sec->type }}</span>
                                    </span>
                                    <span class="ap-sec-row__title">{{ $sec->title }}</span>
                                    @if ($sec->type === 'practice' && $m->practiceSetting?->practiceImage)
                                        <span class="ap-sec-row__docker mono" title="Docker-образ практики">{{ $m->practiceSetting->practiceImage->docker_tag }}</span>
                                    @endif
                                    <span class="ap-sec-row__meta">{{ $sec->is_enabled ? 'Включён' : 'Выключен' }}
                                        @php $secAudience = $visibilitySvc->audienceSummaryForResource(\App\Models\ContentViewAudienceRule::RESOURCE_SECTION, (int) $sec->id, $courseIdWorkbench); @endphp
                                        @if ($secAudience)<span class="ap-sec-row__audience" data-audience-badge-for="sec-{{ $sec->id }}">· {{ $secAudience }}</span>@endif
                                    </span>
                                    <div class="ap-sec-row__actions">
                                        @if ($canEditCourseMeta)
                                            <button type="button"
                                                    class="btn btn-ghost btn-sm ap-mod-icon-btn"
                                                    title="Поделиться разделом"
                                                    data-ap-share-link
                                                    data-ap-share-meta-url="{{ route('admin.course.share-link.meta', array_merge($rp, ['type' => $sec->type === 'survey' ? 'survey' : 'section', 'id' => $sec->id])) }}"
                                                    aria-label="Поделиться">
                                                @include('partials.ap-icon', ['name' => 'share', 'size' => 'sm'])
                                            </button>
                                        @endif
                                        <button type="button"
                                                class="btn btn-ghost btn-sm ap-mod-icon-btn"
                                                title="Доступ к разделу"
                                                data-ap-open-audience
                                                data-audience-title="Доступ: {{ $sec->title }}"
                                                data-audience-target="sec-{{ $sec->id }}"
                                                data-audience-load-url="{{ route('admin.course.section.visibility', array_merge($rp, ['courseModule' => $m->id, 'section' => $sec->id])) }}"
                                                data-audience-save-url="{{ route('admin.course.section.visibility.update', array_merge($rp, ['courseModule' => $m->id, 'section' => $sec->id])) }}"
                                                data-audience-search-url="{{ $learnerSearchUrl }}"
                                        data-audience-resolve-url="{{ $learnerResolveUrl }}"
                                                aria-label="Доступ">
                                            @include('partials.ap-icon', ['name' => 'lock', 'size' => 'sm'])
                                        </button>
                                        <button type="button" class="btn btn-ghost btn-sm ap-mod-icon-btn" title="Редактировать раздел" data-ap-open-section-panel
                                            data-ap-panel-data-url="{{ route('admin.course.section.panel.data', array_merge($rp, ['courseModule' => $m->id, 'section' => $sec->id])) }}"><span class="ap-mod-icon-btn__ic" aria-hidden="true">@include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])</span></button>
                                        <button type="button" class="btn btn-ghost btn-sm ap-mod-icon-btn ap-mod-icon-btn--danger" title="Удалить раздел" data-ap-open-delete-section
                                            data-destroy-url="{{ route('admin.course.module.sections.destroy', array_merge($rp, ['courseModule' => $m->id, 'section' => $sec->id])) }}"
                                            data-section-title="{{ e($sec->title) }}" aria-label="Удалить раздел">
                                            @include('partials.ap-icon', ['name' => 'trash', 'size' => 'md'])
                                        </button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($course->final_lab_enabled)
        <section class="ap-mod-final-lab" aria-labelledby="ap-final-lab-h">
            <h2 id="ap-final-lab-h" class="ap-mod-final-lab__title">Финальная лаборатория</h2>
            <p class="ap-muted ap-mod-final-lab__lead">Включена в настройках курса. Обучающиеся увидят этап после прохождения модулей.</p>
            <div class="ap-mod-final-lab__grid">
                <div>
                    @if ($course->finalLabPracticeImage)
                        <p class="ap-settings-pill ap-mod-final-lab__pill">
                            <strong>{{ $course->finalLabPracticeImage->title }}</strong>
                            <code class="ap-muted">{{ $course->finalLabPracticeImage->docker_tag }}</code>
                        </p>
                    @else
                        <p class="ap-muted">Образ не выбран — укажите в разделе «О курсе».</p>
                    @endif
                </div>
                <div class="ap-mod-final-lab__links">
                    <a class="btn btn-ghost" href="{{ route('admin.quiz.edit.final', $tp) }}">Вопросы финальной страницы</a>
                    <a class="btn btn-ghost" href="{{ route('admin.quiz.export.final', $tp) }}">Excel</a>
                    <a class="btn btn-ghost" href="{{ route('admin.quiz.export.final.word', $tp) }}">Word</a>
                    <a class="btn btn-ghost" href="{{ route('admin.theory.preview-final-lab', $tp) }}">Предпросмотр</a>
                </div>
            </div>
        </section>
    @endif
</div>

{{-- Модалка: новый модуль --}}
@if ($canEditCourseStructure)
<div id="ap-modal-add-module" class="ap-modal" role="dialog" aria-modal="true" aria-hidden="true" hidden>
    <div class="ap-modal__backdrop" data-ap-modal-close tabindex="-1"></div>
    <div class="ap-modal__panel">
        <div class="ap-modal__head">
            <h2 class="ap-modal__title">Добавить модуль</h2>
            <button type="button" class="btn btn-ghost" data-ap-modal-close>Закрыть</button>
        </div>
        <p class="ap-muted small">Разделы копируются с первого модуля; если модулей не было — создаётся стандартный набор из четырёх типов.</p>
        <form method="post" action="{{ route('admin.course.settings.module.store', $rp) }}" class="ap-modal__form">
            @csrf
            <label class="ap-settings-label" for="ap-new-mod-title">Название</label>
            <input id="ap-new-mod-title" class="ap-modal__input" type="text" name="title" required maxlength="200">
            <label class="ap-settings-label" for="ap-new-mod-letter">Буква</label>
            <input id="ap-new-mod-letter" class="ap-modal__input" type="text" name="letter" maxlength="8" style="max-width:6rem">
            <label class="ap-settings-label" for="ap-new-mod-pkg">Пакет контента №</label>
            <input id="ap-new-mod-pkg" class="ap-modal__input" type="number" name="content_source_index" min="1" max="99" style="max-width:7rem" placeholder="1">
            <label class="ap-settings-label" for="ap-new-mod-sum">Описание для обучающихся</label>
            <textarea id="ap-new-mod-sum" class="ap-modal__input ap-settings-textarea" name="summary" rows="3" maxlength="5000"></textarea>
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Добавить</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Модалка: тип раздела --}}
<div id="ap-modal-add-section" class="ap-modal" role="dialog" aria-modal="true" aria-hidden="true" hidden>
    <div class="ap-modal__backdrop" data-ap-modal-close tabindex="-1"></div>
    <div class="ap-modal__panel ap-modal__panel--type-picker">
        <div class="ap-modal__head">
            <h2 class="ap-modal__title">Тип раздела</h2>
            <button type="button" class="btn btn-ghost" data-ap-modal-close>Закрыть</button>
        </div>
        <form id="ap-form-add-section" method="post" action="#" class="ap-sec-type-picker-form">
            @csrf
            <input type="hidden" name="type" id="ap-add-sec-type" value="text">
            <div class="ap-sec-type-grid">
                <button type="button" class="ap-sec-type-card ap-sec-type-card--text" data-ap-pick-sec-type="text">
                    @include('partials.ap-icon', ['name' => 'file-text', 'size' => 'lg'])
                    <span class="ap-sec-type-card__label">Теория</span>
                </button>
                <button type="button" class="ap-sec-type-card ap-sec-type-card--quiz" data-ap-pick-sec-type="quiz">
                    @include('partials.ap-icon', ['name' => 'help-circle', 'size' => 'lg'])
                    <span class="ap-sec-type-card__label">Тест</span>
                </button>
                <button type="button" class="ap-sec-type-card ap-sec-type-card--practice" data-ap-pick-sec-type="practice">
                    @include('partials.ap-icon', ['name' => 'terminal', 'size' => 'lg'])
                    <span class="ap-sec-type-card__label">Практика</span>
                </button>
                <button type="button" class="ap-sec-type-card ap-sec-type-card--exam" data-ap-pick-sec-type="exam">
                    @include('partials.ap-icon', ['name' => 'clipboard-check', 'size' => 'lg'])
                    <span class="ap-sec-type-card__label">Экзамен</span>
                </button>
                <button type="button" class="ap-sec-type-card ap-sec-type-card--survey" data-ap-pick-sec-type="survey">
                    @include('partials.ap-icon', ['name' => 'pencil', 'size' => 'lg'])
                    <span class="ap-sec-type-card__label">Опрос</span>
                </button>
            </div>
            <div id="ap-sec-type-desc" class="ap-sec-type-desc ap-sec-type-desc--text" role="status" aria-live="polite">
                <div class="ap-sec-type-desc__head">
                    <span class="ap-sec-type-desc__badge" id="ap-sec-type-desc-badge">Теория</span>
                </div>
                <p class="ap-sec-type-desc__text" id="ap-sec-type-desc-text">{{ $sectionTypeDescriptions['text']['text'] }}</p>
            </div>
            <label class="ap-settings-label" for="ap-add-sec-title">Название раздела</label>
            <input id="ap-add-sec-title" class="ap-modal__input" type="text" name="title" required maxlength="200" value="{{ $defaultSectionTitles['text'] }}">
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Создать раздел</button>
            </div>
        </form>
    </div>
</div>

{{-- Модалка: удалить модуль --}}
<div id="ap-modal-delete-module" class="ap-modal" role="dialog" aria-modal="true" aria-hidden="true" hidden>
    <div class="ap-modal__backdrop" data-ap-modal-close tabindex="-1"></div>
    <div class="ap-modal__panel">
        <div class="ap-modal__head">
            <h2 class="ap-modal__title">Удалить модуль?</h2>
            <button type="button" class="btn btn-ghost" data-ap-modal-close>Закрыть</button>
        </div>
        <p class="ap-muted" id="ap-delete-mod-text">Модуль и все разделы будут удалены без восстановления.</p>
        <form id="ap-form-delete-module" method="post" action="#" class="ap-modal__form">
            @csrf
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary" style="background:#b91c1c;border-color:#b91c1c">Удалить</button>
            </div>
        </form>
    </div>
</div>

{{-- Модалка: удалить раздел --}}
<div id="ap-modal-delete-section" class="ap-modal" role="dialog" aria-modal="true" aria-hidden="true" hidden>
    <div class="ap-modal__backdrop" data-ap-modal-close tabindex="-1"></div>
    <div class="ap-modal__panel">
        <div class="ap-modal__head">
            <h2 class="ap-modal__title">Удалить раздел?</h2>
            <button type="button" class="btn btn-ghost" data-ap-modal-close>Закрыть</button>
        </div>
        <p class="ap-muted" id="ap-delete-sec-text"></p>
        <form id="ap-form-delete-section" method="post" action="#" class="ap-modal__form">
            @csrf
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary" style="background:#b91c1c;border-color:#b91c1c">Удалить</button>
            </div>
        </form>
    </div>
</div>

{{-- Боковая панель настроек модуля --}}
<aside id="ap-drawer-module-settings" class="ap-drawer ap-drawer--right" aria-hidden="true" aria-label="Настройки модуля">
    <div class="ap-drawer__backdrop" data-ap-drawer-close tabindex="-1"></div>
    <div class="ap-drawer__panel ap-drawer__panel--narrow ap-drawer__panel--stack" role="dialog" aria-modal="true">
        <div class="ap-drawer__head">
            <span class="ap-drawer__title">Настройки модуля</span>
            <button type="button" class="btn btn-ghost ap-drawer__close" data-ap-drawer-close>Закрыть</button>
        </div>
        <form id="ap-form-module-settings" method="post" action="#" class="ap-drawer__body ap-drawer-form">
            @csrf
            <label class="ap-settings-label" for="ap-mod-set-title">Название</label>
            <input id="ap-mod-set-title" class="ap-modal__input" type="text" name="title" required maxlength="200">
            <label class="ap-settings-label" for="ap-mod-set-letter">Буква</label>
            <input id="ap-mod-set-letter" class="ap-modal__input" type="text" name="letter" maxlength="8" style="max-width:6rem">
            <label class="ap-settings-label" for="ap-mod-set-pkg">Пакет №</label>
            <input id="ap-mod-set-pkg" class="ap-modal__input" type="number" name="content_source_index" min="1" max="99" style="max-width:7rem">
            <label class="ap-settings-label" for="ap-mod-set-sum">Описание для обучающихся</label>
            <textarea id="ap-mod-set-sum" class="ap-modal__input ap-settings-textarea" name="summary" rows="5" maxlength="5000"></textarea>
            @if (\Illuminate\Support\Facades\Schema::hasColumn('course_modules', 'show_score_percents') || \Illuminate\Support\Facades\Schema::hasColumn('course_modules', 'show_score_points'))
                <p class="ap-settings-label" style="margin-top:1rem">Метрики для обучающихся</p>
                <p class="ap-muted small" style="margin:0 0 0.5rem">По умолчанию — как в настройках курса. Можно показать или скрыть отдельно проценты и баллы.</p>
                @if (\Illuminate\Support\Facades\Schema::hasColumn('course_modules', 'show_score_percents'))
                    <label class="ap-settings-label" for="ap-mod-set-percents">Проценты</label>
                    <select id="ap-mod-set-percents" class="ap-modal__input" name="show_score_percents">
                        <option value="inherit">Наследовать от курса</option>
                        <option value="1">Показывать</option>
                        <option value="0">Скрыть</option>
                    </select>
                @endif
                @if (\Illuminate\Support\Facades\Schema::hasColumn('course_modules', 'show_score_points'))
                    <label class="ap-settings-label" for="ap-mod-set-points" style="margin-top:0.75rem">Баллы</label>
                    <select id="ap-mod-set-points" class="ap-modal__input" name="show_score_points">
                        <option value="inherit">Наследовать от курса</option>
                        <option value="1">Показывать</option>
                        <option value="0">Скрыть</option>
                    </select>
                @endif
            @endif
            <div class="ap-drawer__footer">
                <button type="button" class="btn btn-ghost" data-ap-drawer-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</aside>

{{-- Боковая панель редактирования раздела --}}
<aside id="ap-sec-edit-panel" class="ap-sec-edit-panel" aria-hidden="true" hidden>
    <div id="ap-sec-edit-panel-resize" class="ap-sec-edit-panel__resize" role="separator" aria-label="Изменить ширину панели"></div>
    <div class="ap-sec-edit-panel__inner">
        <header class="ap-sec-edit-panel__head">
            <div class="ap-sec-edit-panel__head-row">
                <span id="ap-sec-edit-panel-chip" class="ap-sec-edit-panel__chip">Теория</span>
                <h2 id="ap-sec-edit-panel-heading" class="ap-sec-edit-panel__heading">Раздел</h2>
                <div class="ap-sec-edit-panel__head-actions">
                    <a href="#" id="ap-sec-export-excel-btn" class="btn btn-ghost btn-sm" target="_blank" rel="noopener" hidden title="Скачать Excel">
                        @include('partials.ap-icon', ['name' => 'download', 'size' => 'sm'])
                        Скачать Excel
                    </a>
                    <a href="#" id="ap-sec-export-word-btn" class="btn btn-ghost btn-sm" target="_blank" rel="noopener" hidden title="Скачать Word">
                        @include('partials.ap-icon', ['name' => 'download', 'size' => 'sm'])
                        Скачать Word
                    </a>
                    <button type="button" id="ap-sec-share-btn" class="btn btn-ghost btn-sm" title="Поделиться" hidden>
                        @include('partials.ap-icon', ['name' => 'share', 'size' => 'sm'])
                        Поделиться
                    </button>
                    <button type="button" id="ap-sec-edit-panel-close" class="btn btn-ghost ap-sec-edit-panel__close" aria-label="Закрыть">@include('partials.ap-icon', ['name' => 'x', 'size' => 'sm'])</button>
                </div>
            </div>
            <p id="ap-sec-edit-panel-sub" class="ap-sec-edit-panel__sub ap-muted"></p>
            <div class="ap-sec-edit-panel__tabs" role="tablist">
                <button type="button" id="ap-sec-tab-questions" class="ap-sec-edit-panel__tab" role="tab" data-ap-sec-tab="questions" aria-selected="false" hidden>Вопросы</button>
                <button type="button" id="ap-sec-tab-content" class="ap-sec-edit-panel__tab is-active" role="tab" data-ap-sec-tab="content" aria-selected="true">Содержимое</button>
                <button type="button" id="ap-sec-tab-settings" class="ap-sec-edit-panel__tab" role="tab" data-ap-sec-tab="settings" aria-selected="false">Настройки раздела</button>
                <button type="button" id="ap-sec-tab-access" class="ap-sec-edit-panel__tab" role="tab" data-ap-sec-tab="access" aria-selected="false">Доступ</button>
                <button type="button" id="ap-sec-tab-participants" class="ap-sec-edit-panel__tab" role="tab" data-ap-sec-tab="participants" aria-selected="false">Участники</button>
            </div>
        </header>
        <div id="ap-sec-panel-meta" class="panel-meta-info" hidden aria-live="polite"></div>
        <div class="ap-sec-edit-panel__body">
            <div id="ap-sec-edit-panel-pane-access" class="ap-sec-edit-panel__pane" hidden role="tabpanel">
                <p class="ap-muted small ap-sec-access-hint">Ограничьте, кто из обучающихся увидит этот раздел. Для опросов удобно назначить конкретных людей.</p>
                <div id="ap-sec-access-picker-root"></div>
            </div>
            <div id="ap-sec-edit-panel-pane-participants" class="ap-sec-edit-panel__pane" hidden role="tabpanel">
                <div class="ap-sec-participants" id="ap-sec-participants-root">
                    <p class="ap-muted small" id="ap-sec-participants-hint">Кто имеет доступ к разделу и кто уже прошёл его.</p>
                    <p class="ap-sec-participants__actions">
                        <a class="btn btn-ghost btn-sm" href="#" id="ap-sec-participants-page-link" target="_blank" rel="noopener">Открыть на странице</a>
                        <a class="btn btn-ghost btn-sm" href="#" id="ap-sec-participants-csv-link" target="_blank" rel="noopener" hidden>Сводная / CSV</a>
                    </p>
                    <div id="ap-sec-participants-counters" class="ap-sec-participants__counters" hidden></div>
                    <div id="ap-sec-participants-list" class="ap-sec-participants__list">
                        <p class="ap-muted">Откройте вкладку, чтобы загрузить список.</p>
                    </div>
                    <div id="ap-sec-participants-detail" class="ap-sec-participants__detail" hidden></div>
                </div>
            </div>
            <div id="ap-sec-edit-panel-pane-settings" class="ap-sec-edit-panel__pane" hidden role="tabpanel">
                <label class="ap-settings-label" for="ap-sec-set-title">Название</label>
                <input id="ap-sec-set-title" class="ap-modal__input" type="text" maxlength="200">
                <label class="ap-settings-label" for="ap-sec-set-type">Тип</label>
                <select id="ap-sec-set-type" class="ap-modal__input">
                    <option value="text">Теория</option>
                    <option value="quiz">Тест</option>
                    <option value="practice">Практика</option>
                    <option value="exam">Экзамен</option>
                    <option value="survey">Опрос</option>
                </select>
                <div class="ap-sec-edit-panel__toggle-row">
                    <span class="ap-settings-label" style="margin:0">Активен</span>
                    <label class="ap-sec-edit-panel__switch">
                        <input type="checkbox" id="ap-sec-set-enabled">
                        <span class="ap-sec-edit-panel__switch-ui" aria-hidden="true"></span>
                    </label>
                </div>
                <fieldset class="ap-sec-edit-panel__inherit ap-sec-settings-quiz-only" id="ap-sec-settings-quiz-fields">
                    <legend class="ap-settings-label">Попытки</legend>
                    <label class="ap-sec-edit-panel__radio"><input type="radio" name="ap-sec-inherit-att" value="inherit"> Наследовать от курса (<span id="ap-sec-hint-att"></span>)</label>
                    <label class="ap-sec-edit-panel__radio"><input type="radio" name="ap-sec-inherit-att" value="own"> Задать своё</label>
                    <input id="ap-sec-own-att" class="ap-modal__input ap-sec-edit-panel__own-input" type="number" min="1" max="99" placeholder="число попыток" hidden>
                </fieldset>
                <fieldset class="ap-sec-edit-panel__inherit ap-sec-settings-quiz-only">
                    <legend class="ap-settings-label">Время</legend>
                    <label class="ap-sec-edit-panel__radio"><input type="radio" name="ap-sec-inherit-time" value="inherit"> Наследовать от курса (<span id="ap-sec-hint-time"></span>)</label>
                    <label class="ap-sec-edit-panel__radio"><input type="radio" name="ap-sec-inherit-time" value="own"> Задать своё</label>
                    <input id="ap-sec-own-time" class="ap-modal__input ap-sec-edit-panel__own-input" type="number" min="0" max="10080" placeholder="пусто = без лимита" hidden>
                </fieldset>
                <fieldset class="ap-sec-edit-panel__inherit ap-sec-settings-quiz-only">
                    <legend class="ap-settings-label">Проходной балл</legend>
                    <label class="ap-sec-edit-panel__radio"><input type="radio" name="ap-sec-inherit-pass" value="inherit"> Наследовать от курса (<span id="ap-sec-hint-pass"></span>)</label>
                    <label class="ap-sec-edit-panel__radio"><input type="radio" name="ap-sec-inherit-pass" value="own"> Задать своё</label>
                    <input id="ap-sec-own-pass" class="ap-modal__input ap-sec-edit-panel__own-input" type="number" min="1" max="100" placeholder="%" hidden>
                </fieldset>
                <div class="ap-sec-settings-quiz-only" id="ap-sec-breakdown-fields">
                    <div class="ap-sec-edit-panel__toggle-row">
                        <span class="ap-settings-label" style="margin:0">Разбор без ограничения по времени</span>
                        <label class="ap-sec-edit-panel__switch">
                            <input type="checkbox" id="ap-sec-breakdown-unlimited">
                            <span class="ap-sec-edit-panel__switch-ui" aria-hidden="true"></span>
                        </label>
                    </div>
                    <label class="ap-settings-label" for="ap-sec-breakdown-minutes">Минут видимости разбора (0 = скрыть)</label>
                    <input id="ap-sec-breakdown-minutes" class="ap-modal__input" type="number" min="0" max="10080" placeholder="например 15">
                    <p class="ap-muted small">Для тренировочных тестов (не за баллы) можно снять лимит — обучающийся разберёт ошибки без таймера.</p>
                </div>
                <div class="ap-sec-settings-survey-only" id="ap-sec-survey-fields" hidden>
                    <div class="ap-sec-edit-panel__toggle-row">
                        <span class="ap-settings-label" style="margin:0">Анонимный опрос</span>
                        <label class="ap-sec-edit-panel__switch">
                            <input type="checkbox" id="ap-sec-set-anonymous">
                            <span class="ap-sec-edit-panel__switch-ui" aria-hidden="true"></span>
                        </label>
                    </div>
                    <p class="ap-muted small">В отчётах и CSV не показываются email и ФИО; в карточке обучающегося — только факт отправки.</p>
                    <div class="ap-sec-edit-panel__toggle-row">
                        <span class="ap-settings-label" style="margin:0">Блокирует переход к следующим разделам</span>
                        <label class="ap-sec-edit-panel__switch">
                            <input type="checkbox" id="ap-sec-set-blocks-progress" checked>
                            <span class="ap-sec-edit-panel__switch-ui" aria-hidden="true"></span>
                        </label>
                    </div>
                    <p class="ap-muted small">Если выключено — опрос необязателен для прогресса по модулю. Быстрая ссылка всё равно открывает опрос без прохождения курса.</p>
                    <p class="ap-muted small" id="ap-sec-share-hint" hidden>Чтобы поделиться разделом — кнопка <strong>Поделиться</strong> в шапке панели.</p>
                    <p class="ap-muted small" id="ap-sec-survey-responses-link-wrap" hidden><a href="#" id="ap-sec-survey-responses-link" target="_blank" rel="noopener">Ответы опроса и выгрузка CSV</a></p>
                    <p class="ap-muted small" id="ap-sec-participants-settings-link-wrap" hidden><a href="#" id="ap-sec-participants-settings-link" target="_blank" rel="noopener">Участники раздела</a></p>
                </div>
            </div>
            <div id="ap-sec-edit-panel-pane-questions" class="ap-sec-edit-panel__pane ap-sec-edit-panel__pane--questions" hidden role="tabpanel">
                <div class="panel-questions-layout">
                    <aside class="questions-sidebar">
                        <div class="questions-sidebar-header">
                            <span class="questions-sidebar-title">Список</span>
                            <span class="questions-sidebar-hint ap-muted">≡ — порядок</span>
                            <span id="ap-sec-quiz-count" class="questions-count-badge">0</span>
                        </div>
                        <p class="ap-muted small ap-sec-quiz-export-wrap" id="ap-sec-quiz-export-wrap" hidden>
                            <a href="#" id="ap-sec-quiz-export" class="btn btn-ghost btn-sm" target="_blank" rel="noopener">Скачать Excel</a>
                            <a href="#" id="ap-sec-quiz-export-word" class="btn btn-ghost btn-sm" target="_blank" rel="noopener">Скачать Word</a>
                        </p>
                        <div id="ap-sec-quiz-list" class="questions-list-scroll" role="list"></div>
                        <div class="questions-sidebar-footer">
                            <button type="button" class="btn btn-ghost btn-sm" id="ap-sec-quiz-add" style="width:100%;display:inline-flex;align-items:center;justify-content:center;gap:0.35rem">
                                @include('partials.ap-icon', ['name' => 'plus', 'size' => 'sm'])
                                Вопрос
                            </button>
                        </div>
                    </aside>
                    <div class="question-edit-area">
                        <div class="question-edit-scroll" id="ap-sec-q-editor-scroll">
                            <p id="ap-sec-q-empty" class="ap-muted">Выберите вопрос слева или добавьте новый.</p>
                            <div id="ap-sec-q-editor" hidden>
                                <div class="question-edit-header">
                                    <span id="ap-sec-q-editor-title" class="question-edit-title">Вопрос #1</span>
                                    <div class="question-editor-actions">
                                        <button type="button" class="btn btn-ghost btn-sm" id="ap-sec-q-dup" title="Дублировать">Дублировать</button>
                                        <button type="button" class="btn btn-ghost btn-sm ap-mod-icon-btn--danger" id="ap-sec-q-del" title="Удалить">Удалить</button>
                                    </div>
                                </div>
                                <label class="ap-settings-label" for="ap-sec-q-type">Тип вопроса</label>
                                <select id="ap-sec-q-type" class="ap-modal__input">
                                    <option value="single">Один ответ</option>
                                    <option value="multi">Несколько ответов</option>
                                    <option value="match">Сопоставление (drag)</option>
                                    <option value="open_text" id="ap-sec-q-type-open">Открытый ответ</option>
                                    <option value="multi_other" id="ap-sec-q-type-mixed">Смешанный</option>
                                </select>
                                <label class="ap-settings-label" id="ap-sec-q-points-label" for="ap-sec-q-points" hidden>Баллы (points)</label>
                                <input id="ap-sec-q-points" class="ap-modal__input" type="number" min="0" step="1" placeholder="например, 5" hidden>
                                <label class="ap-settings-label" for="ap-sec-q-text">Текст вопроса</label>
                                <div style="display:flex;gap:0.35rem;align-items:flex-start;margin-bottom:0.35rem">
                                    <button type="button" class="btn btn-ghost btn-sm" data-ap-media-insert-target="ap-sec-q-text" title="Вставить картинку" aria-label="Вставить картинку">
                                        <span class="ap-media-insert-btn__inner">@include('partials.icons.media-image')</span>
                                    </button>
                                </div>
                                <textarea id="ap-sec-q-text" class="question-text-input" rows="6"></textarea>
                                <div id="ap-sec-q-answers-wrap">
                                    <div class="ap-sec-q-section-head">
                                        <span class="ap-settings-label" style="margin:0">Варианты ответов</span>
                                        <button type="button" class="btn btn-ghost btn-sm" id="ap-sec-q-add-option">+ Добавить</button>
                                    </div>
                                    <p id="ap-sec-q-c-hint" class="ap-muted small"></p>
                                    <div id="ap-sec-q-answers" class="answer-list"></div>
                                </div>
                                <div id="ap-sec-q-open-wrap" hidden>
                                    <label class="ap-settings-label" for="ap-sec-q-placeholder">Подсказка в поле «Свой вариант» / открытый ответ</label>
                                    <input id="ap-sec-q-placeholder" class="ap-modal__input" type="text" maxlength="200" placeholder="Например: Опишите кратко…">
                                    <label class="ap-settings-label" for="ap-sec-q-maxlen">Макс. длина своего / открытого ответа</label>
                                    <input id="ap-sec-q-maxlen" class="ap-modal__input" type="number" min="1" max="50000" placeholder="8000">
                                </div>
                                <div id="ap-sec-q-match-wrap" hidden>
                                    <div class="ap-sec-q-section-head">
                                        <span class="ap-settings-label" style="margin:0">Пары для сопоставления</span>
                                        <button type="button" class="btn btn-ghost btn-sm" id="ap-sec-q-add-pair">+ Пара</button>
                                    </div>
                                    <p class="ap-muted small">Элемент слева соответствует описанию справа в той же строке.</p>
                                    <div id="ap-sec-q-match" class="ap-sec-q-match-rows"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="ap-sec-edit-panel-pane-content" class="ap-sec-edit-panel__pane" role="tabpanel">
                <div id="ap-sec-edit-legacy" class="ap-sec-edit-panel__legacy ap-muted" hidden>Курс в legacy-режиме: откройте классические редакторы через «Содержимое».</div>
                <div id="ap-sec-edit-content-theory" hidden>
                    <p class="cmde-editor-hint">Заголовки: <strong>Заголовок</strong> / <strong>Подзаголовок</strong>, выравнивание — <strong>≡ Центр</strong> / <strong>≡ | Центр</strong>. Плашки: <strong>Важно</strong>, <strong>Подсказка</strong>, <strong>Примечание</strong>, <strong>Зачем</strong>. Preview — как у обучающегося.</p>
                    <textarea id="ap-sec-theory-md" class="ap-modal__input ap-settings-textarea ap-sec-edit-panel__editor" rows="14"></textarea>
                    <div class="ap-sec-edit-panel__theory-foot">
                        <span id="ap-sec-theory-chars" class="ap-muted small">0 символов</span>
                        <a href="#" id="ap-sec-theory-export" class="btn btn-ghost btn-sm" hidden target="_blank" rel="noopener">Скачать Word</a>
                        <span id="ap-sec-theory-saved" class="ap-sec-edit-panel__saved small" hidden style="display:inline-flex;align-items:center;gap:0.25rem">Сохранено @include('partials.ap-icon', ['name' => 'check', 'size' => 'sm'])</span>
                    </div>
                </div>
                <div id="ap-sec-edit-content-practice" hidden>
                    <h3 class="ap-sec-edit-panel__h3">Задание</h3>
                    <p class="cmde-editor-hint">Заголовки с полоской, плашки, таблицы и картинки — кнопки панели редактора.</p>
                    <textarea id="ap-sec-practice-md" class="ap-modal__input ap-settings-textarea" rows="8"></textarea>
                    <h3 class="ap-sec-edit-panel__h3">Docker-образ</h3>
                    <div id="ap-sec-docker-bound" class="ap-sec-docker-card" hidden>
                        <p><strong id="ap-sec-docker-title"></strong> <code id="ap-sec-docker-tag" class="ap-muted"></code></p>
                        <p class="ap-muted small" id="ap-sec-docker-layers"></p>
                        <div class="ap-sec-docker-card__actions">
                            <button type="button" class="btn btn-ghost btn-sm" id="ap-sec-docker-unbind">Отвязать</button>
                            <button type="button" class="btn btn-primary btn-sm" id="ap-sec-docker-replace">Заменить</button>
                        </div>
                    </div>
                    <div id="ap-sec-docker-unbound" class="ap-sec-docker-unbound">
                        <button type="button" class="btn btn-ghost" id="ap-sec-docker-pick" style="display:inline-flex;align-items:center;gap:0.35rem">Выбрать из библиотеки <span aria-hidden="true">@include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm'])</span></button>
                    </div>
                </div>
            </div>
        </div>
        <footer class="ap-sec-edit-panel__footer">
            <span id="ap-sec-quiz-save-indicator" class="save-indicator ap-sec-edit-panel__saved" hidden></span>
            <div class="ap-sec-edit-panel__footer-actions">
                <button type="button" class="btn btn-ghost" id="ap-sec-edit-cancel">Отмена</button>
                <button type="button" class="btn btn-primary" id="ap-sec-edit-save">Сохранить изменения</button>
            </div>
        </footer>
    </div>
</aside>
<div id="ap-sec-docker-modal" class="ap-modal ap-sec-docker-modal" role="dialog" aria-modal="true" aria-hidden="true" hidden>
    <div class="ap-modal__backdrop" data-ap-sec-docker-modal-close tabindex="-1"></div>
    <div class="ap-modal__panel ap-modal__panel--wide">
        <div class="ap-modal__head">
            <h2 class="ap-modal__title">Образы из библиотеки</h2>
            <button type="button" class="btn btn-ghost" data-ap-sec-docker-modal-close>Закрыть</button>
        </div>
        <p class="ap-muted small">Список собранных образов (как в разделе Docker).</p>
        <ul id="ap-sec-docker-modal-list" class="ap-sec-docker-modal-list"></ul>
    </div>
</div>

@include('admin.partials.content-audience-modal', ['learnerSearchUrl' => $learnerSearchUrl])

@include('partials.course-markdown-editor-assets', [
    'cmdeCourseId' => $courseIdWorkbench,
    'ap' => $rp,
])
<script src="{{ asset('js/content-audience-picker.js') }}?v={{ @filemtime(public_path('js/content-audience-picker.js')) ?: 1 }}"></script>
<script src="{{ asset('js/admin-content-visibility.js') }}?v={{ @filemtime(public_path('js/admin-content-visibility.js')) ?: 1 }}"></script>
<script src="{{ asset('js/section-edit-panel.js') }}?v={{ @filemtime(public_path('js/section-edit-panel.js')) ?: 1 }}"></script>

<script>
(function () {
    var root = document.querySelector('[data-ap-workbench]');
    if (!root) return;

    var defaultTitles = @json($defaultSectionTitles);
    var sectionTypeDescriptions = @json($sectionTypeDescriptions);

    function openModal(el) {
        if (!el) return;
        el.hidden = false;
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
    }
    function closeModal(el) {
        if (!el) return;
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
        el.hidden = true;
    }
    document.querySelectorAll('[data-ap-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var m = btn.closest('.ap-modal');
            if (m) closeModal(m);
        });
    });

    var addModBtn = document.getElementById('ap-open-add-module');
    var addModModal = document.getElementById('ap-modal-add-module');
    if (addModBtn && addModModal) addModBtn.addEventListener('click', function () { openModal(addModModal); });

    var addSecModal = document.getElementById('ap-modal-add-section');
    var addSecForm = document.getElementById('ap-form-add-section');
    var addSecType = document.getElementById('ap-add-sec-type');
    var addSecTitle = document.getElementById('ap-add-sec-title');
    var currentSectionStoreUrl = '';

    function updateSectionTypeDesc(typ) {
        var box = document.getElementById('ap-sec-type-desc');
        var badge = document.getElementById('ap-sec-type-desc-badge');
        var textEl = document.getElementById('ap-sec-type-desc-text');
        var info = sectionTypeDescriptions[typ] || sectionTypeDescriptions.text;
        if (box) {
            box.className = 'ap-sec-type-desc ap-sec-type-desc--' + typ;
        }
        if (badge) badge.textContent = info.title || typ;
        if (textEl) textEl.textContent = info.text || '';
    }

    function selectSectionType(typ) {
        addSecType.value = typ;
        if (addSecTitle) addSecTitle.value = defaultTitles[typ] || '';
        document.querySelectorAll('[data-ap-pick-sec-type]').forEach(function (x) {
            x.classList.toggle('is-selected', x.getAttribute('data-ap-pick-sec-type') === typ);
        });
        updateSectionTypeDesc(typ);
    }

    var delModModal = document.getElementById('ap-modal-delete-module');
    var delModForm = document.getElementById('ap-form-delete-module');
    var delModText = document.getElementById('ap-delete-mod-text');

    var delSecModal = document.getElementById('ap-modal-delete-section');
    var delSecForm = document.getElementById('ap-form-delete-section');
    var delSecText = document.getElementById('ap-delete-sec-text');

    var drawer = document.getElementById('ap-drawer-module-settings');
    var modSetForm = document.getElementById('ap-form-module-settings');

    function openDrawer() {
        if (!drawer) return;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
    }
    function closeDrawer() {
        if (!drawer) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
    }
    if (drawer) {
        drawer.querySelectorAll('[data-ap-drawer-close]').forEach(function (b) {
            b.addEventListener('click', closeDrawer);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (drawer && drawer.classList.contains('is-open')) {
            closeDrawer();
            e.preventDefault();
            return;
        }
        document.querySelectorAll('.ap-modal.is-open').forEach(function (m) {
            closeModal(m);
        });
    });

    function fillModuleOrderFields() {
        var wrap = document.getElementById('ap-module-order-fields');
        var list = document.getElementById('ap-modules-sortable');
        if (!wrap || !list) return;
        wrap.innerHTML = '';
        list.querySelectorAll('.ap-mod-card[data-module-id]').forEach(function (li) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'order[]';
            inp.value = li.getAttribute('data-module-id');
            wrap.appendChild(inp);
        });
    }

    var saveModOrderBtn = document.getElementById('ap-save-module-order');
    var modOrderForm = document.getElementById('ap-modules-reorder-form');
    var initialModuleOrder = '';

    function refreshModuleOrderState() {
        fillModuleOrderFields();
        var ids = [];
        document.querySelectorAll('#ap-modules-sortable .ap-mod-card[data-module-id]').forEach(function (li) {
            ids.push(li.getAttribute('data-module-id'));
        });
        var s = ids.join(',');
        if (saveModOrderBtn) saveModOrderBtn.hidden = (s === initialModuleOrder || ids.length === 0);
    }

    function wireReorderList(list, rowSelector, handleSelector, onEnd) {
        if (!list || list.dataset.reorderReady) {
            return;
        }
        list.dataset.reorderReady = '1';
        var dragEl = null;

        function finishDrag() {
            if (dragEl) {
                dragEl.classList.remove('is-dragging');
            }
            dragEl = null;
            if (onEnd) {
                onEnd();
            }
        }

        function moveDragEl(beforeNode) {
            if (!dragEl || !beforeNode || !list.contains(beforeNode)) {
                return;
            }
            list.insertBefore(dragEl, beforeNode);
        }

        list.addEventListener('dragover', function (e) {
            if (!dragEl) {
                return;
            }
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var row = e.target.closest(rowSelector);
            if (!row || row === dragEl || !list.contains(row)) {
                return;
            }
            var rect = row.getBoundingClientRect();
            var after = (e.clientY - rect.top) > rect.height / 2;
            moveDragEl(after ? row.nextSibling : row);
        });

        list.addEventListener('drop', function (e) {
            e.preventDefault();
            finishDrag();
        });

        list.querySelectorAll(rowSelector).forEach(function (row) {
            row.removeAttribute('draggable');
            var handle = row.querySelector(handleSelector);
            if (!handle) {
                return;
            }
            handle.setAttribute('draggable', 'true');
            handle.addEventListener('dragstart', function (e) {
                dragEl = row;
                e.dataTransfer.effectAllowed = 'move';
                try {
                    e.dataTransfer.setData(
                        'text/plain',
                        row.getAttribute('data-module-id') || row.getAttribute('data-section-id') || ''
                    );
                } catch (err) { /* IE11 */ }
                if (e.dataTransfer.setDragImage) {
                    try {
                        e.dataTransfer.setDragImage(row, 48, 24);
                    } catch (err2) { /* ignore */ }
                }
                row.classList.add('is-dragging');
            });
            handle.addEventListener('dragend', finishDrag);
        });
    }

    @if ($canEditCourseMeta)
    if (document.getElementById('ap-modules-sortable')) {
        document.querySelectorAll('#ap-modules-sortable .ap-mod-card[data-module-id]').forEach(function (li) {
            initialModuleOrder += (initialModuleOrder ? ',' : '') + li.getAttribute('data-module-id');
        });
        wireReorderList(
            document.getElementById('ap-modules-sortable'),
            '.ap-mod-card[data-module-id]',
            '.ap-mod-drag-handle--module',
            refreshModuleOrderState
        );
    }
    if (modOrderForm) modOrderForm.addEventListener('submit', fillModuleOrderFields);
    @endif

    function fillSecOrderFields(ul) {
        var acc = ul.closest('.ap-mod-card__accordion');
        if (!acc) return;
        var wrap = acc.querySelector('[data-ap-sec-order-fields]');
        if (!wrap) return;
        wrap.innerHTML = '';
        ul.querySelectorAll('.ap-sec-row[data-section-id]').forEach(function (row) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'order[]';
            inp.value = row.getAttribute('data-section-id');
            wrap.appendChild(inp);
        });
        var btn = acc.querySelector('[data-ap-save-section-order]');
        var initial = ul.getAttribute('data-initial-order') || '';
        var now = [];
        ul.querySelectorAll('.ap-sec-row[data-section-id]').forEach(function (row) {
            now.push(row.getAttribute('data-section-id'));
        });
        if (btn) btn.hidden = (now.join(',') === initial || now.length === 0);
    }

    document.querySelectorAll('.ap-sec-reorder-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var id = form.id.replace('ap-sections-reorder-', '');
            var ul = document.getElementById('ap-sec-sortable-' + id);
            if (ul) fillSecOrderFields(ul);
        });
    });

    document.querySelectorAll('[data-ap-sec-sortable]').forEach(function (ul) {
        var ids = [];
        ul.querySelectorAll('.ap-sec-row[data-section-id]').forEach(function (row) {
            ids.push(row.getAttribute('data-section-id'));
        });
        ul.setAttribute('data-initial-order', ids.join(','));
        wireReorderList(
            ul,
            '.ap-sec-row[data-section-id]',
            '.ap-mod-drag-handle--sec',
            function () { fillSecOrderFields(ul); }
        );
    });

    function setExpanded(card, open) {
        var acc = card.querySelector('[data-ap-mod-accordion]');
        var btn = card.querySelector('[data-ap-mod-toggle]');
        var chev = card.querySelector('[data-ap-chev]');
        if (!acc || !btn) return;
        acc.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (chev) chev.textContent = open ? '∧' : '∨';
        if (open) card.classList.add('is-expanded'); else card.classList.remove('is-expanded');
    }

    document.querySelectorAll('.ap-mod-card').forEach(function (card) {
        var t = card.querySelector('[data-ap-mod-toggle]');
        var secBtn = card.querySelector('[data-ap-open-sections]');
        function toggle() {
            var acc = card.querySelector('[data-ap-mod-accordion]');
            var open = acc && acc.hidden;
            setExpanded(card, !!open);
        }
        if (t) t.addEventListener('click', function (e) { e.preventDefault(); toggle(); });
        if (secBtn) secBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setExpanded(card, true);
            var acc = card.querySelector('[data-ap-mod-accordion]');
            if (acc) acc.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        var setBtn = card.querySelector('[data-ap-open-module-settings]');
        if (setBtn && modSetForm) {
            setBtn.addEventListener('click', function () {
                modSetForm.action = card.getAttribute('data-update-url');
                document.getElementById('ap-mod-set-title').value = card.getAttribute('data-module-title') || '';
                document.getElementById('ap-mod-set-letter').value = card.getAttribute('data-module-letter') || '';
                document.getElementById('ap-mod-set-pkg').value = card.getAttribute('data-module-pkg') || '';
                document.getElementById('ap-mod-set-sum').value = card.getAttribute('data-module-summary') || '';
                var percSel = document.getElementById('ap-mod-set-percents');
                if (percSel) {
                    percSel.value = card.getAttribute('data-module-show-percents') || 'inherit';
                }
                var ptsSel = document.getElementById('ap-mod-set-points');
                if (ptsSel) {
                    ptsSel.value = card.getAttribute('data-module-show-points') || 'inherit';
                }
                openDrawer();
            });
        }

        var delBtn = card.querySelector('[data-ap-open-delete-module]');
        if (delBtn && delModForm) {
            delBtn.addEventListener('click', function () {
                delModForm.action = card.getAttribute('data-destroy-url');
                if (delModText) delModText.textContent = 'Удалить модуль «' + (card.getAttribute('data-module-title') || '') + '» и все разделы?';
                openModal(delModModal);
            });
        }

        var addSecBtn = card.querySelector('[data-ap-open-add-section]');
        if (addSecBtn && addSecForm) {
            addSecBtn.addEventListener('click', function () {
                currentSectionStoreUrl = card.getAttribute('data-section-store-url');
                addSecForm.action = currentSectionStoreUrl;
                selectSectionType('text');
                openModal(addSecModal);
            });
        }
    });

    if (addSecForm) {
        document.querySelectorAll('[data-ap-pick-sec-type]').forEach(function (b) {
            b.addEventListener('click', function () {
                selectSectionType(b.getAttribute('data-ap-pick-sec-type'));
            });
        });
    }

    document.querySelectorAll('[data-ap-open-delete-section]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            delSecForm.action = btn.getAttribute('data-destroy-url');
            if (delSecText) delSecText.textContent = 'Удалить раздел «' + (btn.getAttribute('data-section-title') || '') + '»?';
            openModal(delSecModal);
        });
    });

    if (location.hash && location.hash.indexOf('ap-mod-') === 1) {
        var id = location.hash.slice(1).replace('ap-mod-', '');
        var card = document.getElementById('ap-mod-' + id);
        if (card) setExpanded(card, true);
    }
})();
</script>
