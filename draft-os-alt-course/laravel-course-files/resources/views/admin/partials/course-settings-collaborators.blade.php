@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Support\Collection<int, \App\Models\PortalStaff> $collaborators */
    /** @var array<int, list<array{resource_type: string, resource_id: int|null, permission: string}>> $grantsByStaff */
    $tp = $ap ?? ['adminCourse' => $course->slug];
    $modules = $grantTree['modules'] ?? [];
    $permLabels = [
        'view' => 'Просмотр',
        'edit' => 'Редактирование',
        'manage' => 'Управление',
    ];
    $permHints = [
        'view' => 'Видит материалы в админке, без сохранения',
        'edit' => 'Может менять контент выбранной области',
        'manage' => 'Публикация, настройки курса и приглашение соавторов',
    ];
    $sectionIcons = [
        'text' => 'book-open',
        'quiz' => 'help-circle',
        'practice' => 'terminal',
        'exam' => 'award',
        'survey' => 'clipboard-check',
    ];

    if ($course->is_archived) {
        $courseStatusLabel = 'Архив';
        $courseStatusClass = 'ap-badge--archive';
        $courseContextLead = 'Курс в архиве. Соавторы могут дорабатывать материалы перед восстановлением — обучающиеся по-прежнему не видят курс в каталоге.';
    } elseif ($course->is_published) {
        $courseStatusLabel = 'Опубликован';
        $courseStatusClass = 'ap-badge--published';
        $courseContextLead = 'Курс доступен обучающимся в каталоге. Соавторы работают только в админке и видят лишь назначенные им модули и разделы — остальные сотрудники портала курс не увидят, если им не выдан доступ.';
    } else {
        $courseStatusLabel = 'Черновик';
        $courseStatusClass = 'ap-badge--draft';
        $courseContextLead = 'Курс ещё не в каталоге обучающихся. Соавторы помогают готовить материалы; другие коллеги без приглашения этот курс не увидят.';
    }

    $resolveGrantLabel = static function (array $g, array $modules): string {
        $label = match ($g['resource_type']) {
            'course' => 'Весь курс',
            'module' => 'Модуль #'.(int) $g['resource_id'],
            'section' => 'Раздел #'.(int) $g['resource_id'],
            default => $g['resource_type'],
        };
        foreach ($modules as $mod) {
            if ($g['resource_type'] === 'module' && (int) $g['resource_id'] === (int) $mod['id']) {
                $label = $mod['title'];
            }
            if ($g['resource_type'] === 'section') {
                foreach ($mod['sections'] as $sec) {
                    if ((int) $g['resource_id'] === (int) $sec['id']) {
                        $label = $mod['title'].' · '.$sec['title'];
                    }
                }
            }
        }

        return $label;
    };
@endphp

<div class="ap-collab-page"
     data-ap-collab-page
     data-search-url="{{ route('admin.course.collaborators.search', $tp) }}">

    <header class="ap-collab-hero">
        <div class="ap-collab-hero__text">
            <div class="ap-collab-hero__title-row">
                <h1 class="ap-page-title ap-collab-hero__title">Соавторы курса</h1>
                <span class="ap-badge {{ $courseStatusClass }}">{{ $courseStatusLabel }}</span>
            </div>
            <p class="ap-page-lead ap-collab-hero__lead">{{ $courseContextLead }}</p>
        </div>
        <div class="ap-collab-stats" aria-label="Сводка по соавторам">
            <div class="ap-collab-stat">
                <span class="ap-collab-stat__value">{{ $collaborators->count() }}</span>
                <span class="ap-collab-stat__label">в команде</span>
            </div>
            <div class="ap-collab-stat">
                <span class="ap-collab-stat__value">{{ (int) $collaboratorCount }} / {{ (int) $collaboratorLimit }}</span>
                <span class="ap-collab-stat__label">с правом редактирования</span>
            </div>
            <div class="ap-collab-stat">
                <span class="ap-collab-stat__value">{{ count($modules) }}</span>
                <span class="ap-collab-stat__label">модулей в курсе</span>
            </div>
        </div>
    </header>

    <aside class="ap-collab-legend ap-settings-card" aria-label="Справка по уровням доступа">
        <h2 class="ap-settings-card__title">Что означают уровни доступа</h2>
        <ul class="ap-collab-legend__list">
            <li>
                <span class="ap-collab-legend__badge ap-collab-legend__badge--view">Просмотр</span>
                <span>Открывает материалы в админке для чтения и предпросмотра. Сохранять изменения нельзя.</span>
            </li>
            <li>
                <span class="ap-collab-legend__badge ap-collab-legend__badge--edit">Редактирование</span>
                <span>Можно править теорию, тесты, практики и настройки выбранного модуля или раздела.</span>
            </li>
            <li>
                <span class="ap-collab-legend__badge ap-collab-legend__badge--manage">Управление</span>
                <span>Полный доступ к курсу: публикация, карточка курса, сертификат и приглашение других соавторов.</span>
            </li>
        </ul>
    </aside>

    <div class="ap-collab-layout">
        <section class="ap-collab-panel ap-settings-card" aria-labelledby="ap-collab-team-title">
            <div class="ap-collab-panel__head">
                <div>
                    <h2 id="ap-collab-team-title" class="ap-settings-card__title">Команда курса</h2>
                    <p class="ap-settings-sub ap-muted">Коллеги с доступом к этому курсу. Нажмите «Изменить права», чтобы обновить их зону ответственности.</p>
                </div>
            </div>

            @if ($collaborators->isNotEmpty())
                <ul class="ap-collab-team">
                    @foreach ($collaborators as $staff)
                        @php
                            $learner = $staff->learner;
                            $email = $learner ? (string) $learner->email : ('#'.$staff->id);
                            $name = $learner ? (string) ($learner->sso_display_name ?: $email) : $email;
                            $grants = $grantsByStaff[(int) $staff->id] ?? [];
                            $access = new \App\Services\PortalStaffAccess($staff);
                            $initials = \App\Support\LearnerDisplay::initials($email, $name);
                            $grantsJson = json_encode($grants, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                        @endphp
                        <li class="ap-collab-member"
                            data-ap-collab-card
                            data-email="{{ $email }}"
                            data-name="{{ $name }}"
                            data-grants="{{ $grantsJson }}">
                            <div class="ap-collab-member__avatar" aria-hidden="true">{{ $initials }}</div>
                            <div class="ap-collab-member__main">
                                <div class="ap-collab-member__head">
                                    <div>
                                        <strong class="ap-collab-member__name">{{ $name }}</strong>
                                        <div class="ap-collab-member__meta">{{ $email }} · {{ $access->roleLabel() }}</div>
                                    </div>
                                    @if (! empty($canManageCollaborators))
                                        <div class="ap-collab-member__actions">
                                            <button type="button" class="btn btn-ghost btn-sm" data-ap-collab-edit title="Подставить email и права в форму справа">Изменить права</button>
                                            <form method="post" action="{{ route('admin.course.collaborators.remove', array_merge($tp, ['portalStaff' => $staff->id])) }}" onsubmit="return confirm('Убрать {{ $name }} из соавторов курса?');">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-sm ap-collab-member__remove" title="Отозвать все права на этот курс">Убрать</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                                @if ($grants !== [])
                                    <ul class="ap-collab-member__grants">
                                        @foreach ($grants as $g)
                                            @php
                                                $perm = $g['permission'];
                                                $permClass = match ($perm) {
                                                    'manage' => 'ap-collab-chip--manage',
                                                    'edit' => 'ap-collab-chip--edit',
                                                    default => 'ap-collab-chip--view',
                                                };
                                            @endphp
                                            <li>
                                                <span class="ap-collab-chip {{ $permClass }}" title="{{ $permHints[$perm] ?? '' }}">{{ $permLabels[$perm] ?? $perm }}</span>
                                                <span>{{ $resolveGrantLabel($g, $modules) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="ap-muted ap-collab-member__empty">Нет активных прав — назначьте доступ через форму.</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="ap-collab-empty">
                    @include('partials.ap-icon', ['name' => 'users', 'size' => 'lg'])
                    <p class="ap-collab-empty__title">Пока никого нет в команде</p>
                    <p class="ap-muted ap-collab-empty__text">Добавьте коллегу справа: укажите email и отметьте модули или разделы, за которые он отвечает.</p>
                </div>
            @endif
        </section>

        @if (! empty($canManageCollaborators))
            <section class="ap-collab-panel ap-settings-card ap-collab-panel--form" aria-labelledby="ap-collab-form-title">
                <div class="ap-collab-panel__head">
                    <div>
                        <h2 id="ap-collab-form-title" class="ap-settings-card__title">Добавить соавтора</h2>
                        <p id="ap-collab-form-hint" class="ap-settings-sub ap-muted">Укажите email коллеги и выберите, какие части курса он может просматривать или редактировать.</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" id="ap-collab-form-reset" title="Очистить форму для нового приглашения">Новое приглашение</button>
                </div>

                <form method="post" action="{{ route('admin.course.collaborators.invite', $tp) }}" class="ap-collab-form" id="ap-collaborators-form">
                    @csrf

                    <div class="ap-collab-form__step">
                        <label class="ap-settings-label" for="ap-collab-email">1. Email сотрудника</label>
                        <p class="ap-settings-hint ap-muted">Начните вводить имя или почту — подсказки появятся из списка «Сотрудники».</p>
                        <input type="email" id="ap-collab-email" name="email" required class="ap-settings-input" placeholder="ivan.petrov@company.ru" autocomplete="off" list="ap-staff-suggest">
                        <datalist id="ap-staff-suggest"></datalist>
                    </div>

                    <div class="ap-collab-form__step">
                        <span class="ap-settings-label">2. Права на части курса</span>
                        <p class="ap-settings-hint ap-muted">Разверните модуль и выберите уровень для всего модуля или отдельных разделов. Достаточно одной области с правом «Редактирование».</p>
                        <p id="ap-collab-grant-summary" class="ap-collab-grant-summary is-empty">Права не выбраны</p>

                        <div class="ap-collab-tree">
                            <div class="ap-collab-module is-open">
                                <button type="button" class="ap-collab-module__head ap-collab-module-toggle" aria-expanded="true">
                                    @include('partials.ap-icon', ['name' => 'panel', 'size' => 'sm'])
                                    <span class="ap-collab-module__title">Весь курс</span>
                                    <span class="ap-collab-module__hint">Сразу для всех модулей</span>
                                </button>
                                <div class="ap-collab-module__body">
                                    @include('admin.partials.course-collaborators-perm-row', [
                                        'label' => 'Доступ ко всему курсу',
                                        'hint' => 'Включая публикацию и управление соавторами при уровне «Управление»',
                                        'resourceType' => 'course',
                                        'resourceId' => '',
                                        'options' => [
                                            ['value' => '', 'label' => 'Нет'],
                                            ['value' => 'view', 'label' => 'Просмотр'],
                                            ['value' => 'edit', 'label' => 'Редактирование'],
                                            ['value' => 'manage', 'label' => 'Управление'],
                                        ],
                                    ])
                                </div>
                            </div>

                            @foreach ($modules as $mod)
                                <div class="ap-collab-module">
                                    <button type="button" class="ap-collab-module__head ap-collab-module-toggle" aria-expanded="false">
                                        @include('partials.ap-icon', ['name' => 'book', 'size' => 'sm'])
                                        <span class="ap-collab-module__title">{{ $mod['title'] }}</span>
                                        <span class="ap-collab-module__hint">{{ count($mod['sections']) }} @choice('раздел|раздела|разделов', count($mod['sections']))</span>
                                    </button>
                                    <div class="ap-collab-module__body" hidden>
                                        @include('admin.partials.course-collaborators-perm-row', [
                                            'label' => 'Весь модуль',
                                            'hint' => 'Теория, все разделы и настройки практики модуля',
                                            'resourceType' => 'module',
                                            'resourceId' => (string) $mod['id'],
                                            'options' => [
                                                ['value' => '', 'label' => 'Нет'],
                                                ['value' => 'view', 'label' => 'Просмотр'],
                                                ['value' => 'edit', 'label' => 'Редактирование'],
                                            ],
                                        ])
                                        @foreach ($mod['sections'] as $sec)
                                            @include('admin.partials.course-collaborators-perm-row', [
                                                'label' => $sec['title'],
                                                'hint' => $sec['type_label'],
                                                'resourceType' => 'section',
                                                'resourceId' => (string) $sec['id'],
                                                'icon' => $sectionIcons[$sec['type']] ?? 'file-text',
                                                'isSection' => true,
                                                'options' => [
                                                    ['value' => '', 'label' => 'Нет'],
                                                    ['value' => 'view', 'label' => 'Просмотр'],
                                                    ['value' => 'edit', 'label' => 'Редактирование'],
                                                ],
                                            ])
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div id="ap-collab-grants-hidden" hidden aria-hidden="true"></div>

                    <div class="ap-collab-form__footer">
                        <button type="submit" class="btn btn-primary" title="Сохранить права для указанного email. Если сотрудник уже в команде — права обновятся.">
                            Сохранить права соавтора
                        </button>
                        <p class="ap-collab-form__note ap-muted">
                            Если коллеги нет в «Сотрудники», администратор портала может создать учётную запись с ролью «Соавтор курса».
                            Лимит редакторов на курс: <strong>{{ (int) $collaboratorLimit }}</strong> человек (без учёта владельца).
                        </p>
                    </div>
                </form>
            </section>
        @endif
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/admin-course-collaborators.js') }}"></script>
@endpush
