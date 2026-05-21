@extends('layouts.admin')

@section('title', ($isNew ? 'Мастер образа практики' : 'Образ: '.$row->title))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/practice-image-wizard.css') }}?v=11">
@endpush

@section('content')
    @php
        $piScope = ($piRouteScope ?? null) === 'docker' || (($piRouteScope ?? null) !== 'course' && empty($ap ?? [])) ? 'docker' : 'course';
        $piKey = (string) (($adminKey ?? null) ?: request()->query('key', ''));
        $piRp = array_merge($ap ?? [], $piKey !== '' ? ['key' => $piKey] : []);
        $piBack = $piScope === 'docker' ? route('admin.docker.library') : route('admin.practice.images.index', $piRp);
        $formAction = $isNew
            ? ($piScope === 'docker' ? route('admin.docker.library.store') : route('admin.practice.images.store', $piRp))
            : ($piScope === 'docker' ? route('admin.docker.library.update', ['id' => $row->id]) : route('admin.practice.images.update', array_merge($piRp, ['id' => $row->id])));
        $cloneUrl = $piScope === 'docker' ? route('admin.docker.library.clone') : route('admin.practice.images.clone', $piRp);
        $previewUrl = $piScope === 'docker' ? route('admin.docker.library.recipe.preview') : route('admin.practice.images.recipe.preview', $piRp);
        $reimportUrl = ! $isNew ? ($piScope === 'docker' ? route('admin.docker.library.reimport', ['id' => $row->id]) : route('admin.practice.images.reimport', array_merge($piRp, ['id' => $row->id]))) : '';
        $buildUrl = ! $isNew ? ($piScope === 'docker' ? route('admin.docker.library.build', ['id' => $row->id]) : route('admin.practice.images.build', array_merge($piRp, ['id' => $row->id]))) : '';
        $f = is_array($row->features ?? null) ? $row->features : [];
        $cu = is_array($f['create_user'] ?? null) ? $f['create_user'] : [];
        $pkgAddLines = old('package_add_text', is_array($row->package_add ?? null) ? implode("\n", $row->package_add) : '');
        $pkgRmLines = old('package_remove_text', is_array($row->package_remove ?? null) ? implode("\n", $row->package_remove) : '');
        $stepTotal = count($wizardSteps);
    @endphp

    <div class="piwiz-shell">
        <header class="piwiz-top">
            <div>
                <a class="piwiz-top__back" href="{{ $piBack }}">@include('partials.ap-icon', ['name' => 'arrow-left', 'size' => 'sm']) Библиотека</a>
                <h1>{{ $isNew ? 'Новый образ для практики' : $row->title }}</h1>
                <p class="piwiz-top__tagline">8 шагов · Dockerfile и скрипты соберутся автоматически</p>
            </div>
            @if (! $isNew)
                <div class="piwiz-top__actions">
                    @if ($row->last_build_status === 'ok')
                        <span class="ap-docker-badge ap-docker-badge--ok">На стенде</span>
                    @elseif ($row->last_build_status === 'fail')
                        <span class="ap-docker-badge ap-docker-badge--err">Ошибка</span>
                    @endif
                </div>
            @endif
        </header>

        @if (session('ok'))
            <div class="piwiz-flash piwiz-flash--ok" role="status">{{ session('ok') }}</div>
        @endif
        @if (session('err'))
            <div class="piwiz-flash piwiz-flash--err" role="alert">{{ session('err') }}</div>
        @endif
        @if (! $daemonConfigured)
            <div class="piwiz-flash piwiz-flash--err" role="status">Сборка недоступна: настройте lab-daemon в .env</div>
        @endif

        <div class="piwiz-progress-wrap">
            <div class="piwiz-progress-meta">
                <span>Шаг <strong id="piwiz-step-num">1</strong> из {{ $stepTotal }}</span>
                <span id="piwiz-step-pct">12%</span>
            </div>
            <div class="piwiz-progress-track" aria-hidden="true">
                <span class="piwiz-progress-fill" id="piwiz-progress-fill" style="width:12%"></span>
            </div>
            <nav class="piwiz-stepper" aria-label="Шаги" id="piwiz-stepper">
                @foreach ($wizardSteps as $i => $ws)
                    <button type="button" class="piwiz-stepper__item js-piwiz-go" data-index="{{ $i }}" data-step="{{ $ws['step'] }}">
                        <span class="piwiz-stepper__dot">{{ $i + 1 }}</span>
                        <span class="piwiz-stepper__label">{{ $ws['title'] }}</span>
                    </button>
                @endforeach
            </nav>
        </div>

        <div class="piwiz-stage">
            <header class="piwiz-stage__head">
                <span class="piwiz-stage__badge" id="piwiz-stage-badge">Шаг 1</span>
                <h2 id="piwiz-stage-title">{{ $wizardSteps[0]['headline'] ?? $wizardSteps[0]['title'] }}</h2>
                <p id="piwiz-stage-lead">{{ $wizardSteps[0]['lead'] ?? '' }}</p>
            </header>

            <form method="post" action="{{ $formAction }}" id="ap-piwiz-form" novalidate>
                @csrf
                <input type="hidden" name="init_from_template" id="pi-init-template" value="{{ $isNew ? '1' : '0' }}">
                <input type="hidden" name="base_template" id="pi-base-template" value="{{ old('base_template', $row->base_template) }}">

                {{-- 1 Шаблон --}}
                <section class="piwiz-panel js-piwiz-panel" data-panel="template" data-headline="{{ $wizardSteps[0]['headline'] }}" data-lead="{{ $wizardSteps[0]['lead'] }}">
                    <div class="piwiz-tabs" role="tablist">
                        <button type="button" class="piwiz-tab is-active js-piwiz-tab" data-tab="builtin">Готовые шаблоны</button>
                        <button type="button" class="piwiz-tab js-piwiz-tab" data-tab="catalog">Из каталога</button>
                    </div>
                    <div class="piwiz-tab-pane js-piwiz-tab-pane" data-tab-pane="builtin">
                        <div class="piwiz-tiles" id="pi-builtin-templates">
                            @foreach ($builtinTemplates as $tpl)
                                <button type="button" class="piwiz-tile js-pi-pick-template" data-template-id="{{ $tpl['id'] }}">
                                    <span class="piwiz-tile__icon">@include('partials.ap-icon', ['name' => $tpl['icon'] ?? 'panel', 'size' => 'lg'])</span>
                                    <span class="piwiz-tile__title">{{ $tpl['title'] }}</span>
                                    <span class="piwiz-tile__sub">{{ $tpl['subtitle'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="piwiz-tab-pane js-piwiz-tab-pane" data-tab-pane="catalog" hidden>
                        @if ($libraryImages->isEmpty())
                            <p class="piwiz-field__tip">В каталоге пока нет других образов.</p>
                        @else
                            <div class="piwiz-catalog">
                                @foreach ($libraryImages as $lib)
                                    <div class="piwiz-catalog__item">
                                        <div>
                                            <strong>{{ $lib->title }}</strong>
                                            <span class="piwiz-catalog__tag">{{ $lib->docker_tag }}</span>
                                        </div>
                                        <button type="button" class="piwiz-btn piwiz-btn--primary piwiz-btn--sm js-pi-clone-lib"
                                                data-id="{{ $lib->id }}"
                                                data-title="{{ e($lib->title) }} — копия"
                                                data-tag="{{ e($lib->docker_tag) }}">Копировать</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>

                {{-- 2 Имя --}}
                <section class="piwiz-panel js-piwiz-panel" data-panel="basics" hidden data-headline="{{ $wizardSteps[1]['headline'] }}" data-lead="{{ $wizardSteps[1]['lead'] }}">
                    <div class="piwiz-fields">
                        <div class="piwiz-field">
                            <label for="pi-title">Название</label>
                            <input id="pi-title" name="title" value="{{ old('title', $row->title) }}" maxlength="200" placeholder="Практика: сети" autocomplete="off">
                        </div>
                        <div class="piwiz-field">
                            <label for="pi-slug">Slug</label>
                            <input id="pi-slug" name="slug" class="mono" value="{{ old('slug', $row->slug) }}" maxlength="80" placeholder="auto">
                        </div>
                        <div class="piwiz-field piwiz-fields--2" style="grid-column:1/-1">
                            <div class="piwiz-field">
                                <label for="pi-tag">Тег Docker</label>
                                <input id="pi-tag" name="docker_tag" class="mono" value="{{ old('docker_tag', $row->docker_tag) }}" maxlength="200" placeholder="my-lab:latest" autocomplete="off">
                                <span class="piwiz-field__tip">Для systemd добавьте <code>-systemd</code> в тег</span>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- 3 ОС --}}
                <section class="piwiz-panel js-piwiz-panel" data-panel="os" hidden data-headline="{{ $wizardSteps[2]['headline'] }}" data-lead="{{ $wizardSteps[2]['lead'] }}">
                    <div class="piwiz-os">
                        @foreach ($osChoices as $os)
                            <label>
                                <input type="radio" name="base_os" value="{{ $os['value'] }}" @checked(old('base_os', $row->base_os ?? 'alt') === $os['value'])>
                                <span class="piwiz-os__card">
                                    <span class="piwiz-os__name">{{ $os['label'] }}</span>
                                    <span class="piwiz-os__mgr">{{ $os['pkg_mgr'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="piwiz-field" style="margin-top:1rem">
                        <label for="pi-base-image">Base image (FROM)</label>
                        <input id="pi-base-image" name="base_image_ref" class="mono" value="{{ old('base_image_ref', $row->base_image_ref ?? '') }}" placeholder="registry.altlinux.org/alt/alt:p10">
                        <button type="button" class="piwiz-btn piwiz-btn--ghost piwiz-btn--sm" id="pi-fill-default-image" style="margin-top:0.5rem">Подставить по умолчанию</button>
                    </div>
                </section>

                {{-- 4 Пакеты --}}
                <section class="piwiz-panel js-piwiz-panel" data-panel="packages" hidden data-headline="{{ $wizardSteps[3]['headline'] }}" data-lead="{{ $wizardSteps[3]['lead'] }}">
                    @foreach ($packageGroups as $grp)
                        <p class="piwiz-group-title">{{ $grp['title'] }}</p>
                        <div class="piwiz-chips">
                            @foreach ($grp['packages'] as $pkg)
                                <button type="button" class="piwiz-chip js-pi-pkg-chip" data-pkg="{{ $pkg }}">+ {{ $pkg }}</button>
                            @endforeach
                            <button type="button" class="piwiz-chip js-pi-pkg-group-all" data-pkgs="{{ json_encode($grp['packages']) }}">Всё</button>
                        </div>
                    @endforeach
                    <div class="piwiz-field" style="margin-top:0.75rem;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end">
                        <div style="flex:1;min-width:200px">
                            <label for="pkg-q">Поиск на стенде</label>
                            <input id="pkg-q" placeholder="vim, audit…" autocomplete="off">
                        </div>
                        <button type="button" class="piwiz-btn piwiz-btn--primary" id="pkg-search-btn">Найти</button>
                    </div>
                    <div id="pkg-results" class="piwiz-chips" style="min-height:2rem"></div>
                    <div class="piwiz-fields piwiz-fields--2" style="margin-top:0.75rem">
                        <div class="piwiz-field">
                            <label>Установить</label>
                            <textarea class="piwiz-code" id="pkg-add" name="package_add_text" rows="8" spellcheck="false">{{ $pkgAddLines }}</textarea>
                        </div>
                        <div class="piwiz-field">
                            <label>Удалить</label>
                            <textarea class="piwiz-code" id="pkg-rm" name="package_remove_text" rows="8" spellcheck="false">{{ $pkgRmLines }}</textarea>
                        </div>
                    </div>
                </section>

                {{-- 5 Среда --}}
                <section class="piwiz-panel js-piwiz-panel" data-panel="features" hidden data-headline="{{ $wizardSteps[4]['headline'] }}" data-lead="{{ $wizardSteps[4]['lead'] }}">
                    <div style="display:grid;gap:0.65rem">
                        @foreach ($featureToggles as $ft)
                            <label class="piwiz-toggle">
                                <input type="hidden" name="{{ $ft['field'] }}" value="0">
                                <input type="checkbox" name="{{ $ft['field'] }}" value="1" @checked(! empty($f[$ft['key']]))>
                                <span>
                                    <span class="piwiz-toggle__title">{{ $ft['title'] }}</span>
                                    <span class="piwiz-toggle__hint">{{ $ft['hint'] }}</span>
                                </span>
                            </label>
                        @endforeach
                        <div class="piwiz-field" style="border:1px solid var(--piwiz-border);border-radius:14px;padding:0.85rem">
                            <label class="piwiz-toggle" style="border:none;padding:0;background:transparent">
                                <input type="hidden" name="features[create_user][enabled]" value="0">
                                <input type="checkbox" name="features[create_user][enabled]" value="1" @checked(! isset($cu['enabled']) || ! empty($cu['enabled']))>
                                <span class="piwiz-toggle__title">Учебный пользователь student</span>
                            </label>
                            <div class="piwiz-fields piwiz-fields--2" style="margin-top:0.5rem">
                                <input name="features[create_user][name]" value="{{ old('features.create_user.name', (string) ($cu['name'] ?? 'student')) }}" placeholder="логин">
                                <input name="features[create_user][password]" value="{{ old('features.create_user.password', (string) ($cu['password'] ?? 'labstudy')) }}" placeholder="пароль">
                            </div>
                            <label style="margin-top:0.5rem;font-size:0.82rem">
                                <input type="hidden" name="features[create_user][sudo]" value="0">
                                <input type="checkbox" name="features[create_user][sudo]" value="1" @checked(! isset($cu['sudo']) || ! empty($cu['sudo']))> sudo без пароля
                            </label>
                        </div>
                        <div class="piwiz-field">
                            <label>Локаль</label>
                            <input name="features[locale]" value="{{ old('features.locale', (string) ($f['locale'] ?? '')) }}" placeholder="C.UTF-8">
                        </div>
                    </div>
                </section>

                {{-- 6 Startup — палитра слева, редактор справа --}}
                <section class="piwiz-panel js-piwiz-panel" data-panel="startup" hidden data-headline="{{ $wizardSteps[5]['headline'] }}" data-lead="{{ $wizardSteps[5]['lead'] }}">
                    <p class="piwiz-startup__lead">Слева — сценарии (прокрутка внутри колонки). Справа — итоговый <code>startup.sh</code>. «Собрать» заменяет скрипт, «Добавить в конец» — дописывает блоки.</p>
                    <div class="piwiz-scenario-toolbar">
                        <button type="button" class="piwiz-btn piwiz-btn--primary piwiz-btn--sm" id="pi-startup-merge">Собрать выбранное (<span id="pi-startup-count">0</span>)</button>
                        <button type="button" class="piwiz-btn piwiz-btn--ghost piwiz-btn--sm" id="pi-startup-append">Добавить в конец</button>
                        <button type="button" class="piwiz-btn piwiz-btn--ghost piwiz-btn--sm" id="pi-startup-clear-sel">Снять выбор</button>
                        <label class="piwiz-scenario-auto">
                            <input type="checkbox" id="pi-startup-auto-merge" checked>
                            Обновлять при выборе
                        </label>
                    </div>
                    <div class="piwiz-startup__layout">
                        <aside class="piwiz-startup__palette" aria-label="Сценарии startup.sh">
                            <div class="piwiz-startup__palette-head">
                                <input type="search" class="piwiz-startup__search" id="pi-startup-filter" placeholder="Поиск по названию…" autocomplete="off">
                                <div class="piwiz-startup__palette-actions">
                                    <span class="piwiz-startup__count">Выбрано: <strong id="pi-startup-count-palette">0</strong></span>
                                </div>
                            </div>
                            <ul class="piwiz-startup__order" id="pi-startup-order" hidden></ul>
                            <div class="piwiz-startup__list">
                                @foreach ($startupCategories as $catId => $catLabel)
                                    @php
                                        $catPresets = array_values(array_filter($startupPresets, static fn ($p) => ($p['category'] ?? '') === $catId));
                                    @endphp
                                    @if ($catPresets !== [])
                                        <div class="piwiz-startup__cat" data-category="{{ $catId }}">
                                            <h4 class="piwiz-startup__cat-title @if($catId === 'break') is-break @endif">{{ $catLabel }}</h4>
                                            <ul class="piwiz-startup__items">
                                                @foreach ($catPresets as $sp)
                                                    @php
                                                        $searchHay = mb_strtolower($catLabel.' '.$sp['title'].' '.($sp['description'] ?? ''));
                                                    @endphp
                                                    <li class="piwiz-startup__item-wrap" data-search="{{ e($searchHay) }}">
                                                        <label class="piwiz-startup__item @if($catId === 'break') is-break @endif">
                                                            <input type="checkbox" class="js-pi-startup-pick" value="{{ $sp['id'] }}" data-title="{{ e($sp['title']) }}" hidden>
                                                            <span class="piwiz-startup__item-mark" aria-hidden="true"></span>
                                                            <span class="piwiz-startup__item-body">
                                                                <span class="piwiz-startup__item-title">{{ $sp['title'] }}</span>
                                                                @if (! empty($sp['description']))
                                                                    <span class="piwiz-startup__item-desc">{{ $sp['description'] }}</span>
                                                                @endif
                                                            </span>
                                                        </label>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </aside>
                        <div class="piwiz-startup__editor">
                            <div class="piwiz-startup__editor-head">
                                <div>
                                    <label for="pi-startup"><strong>startup.sh</strong> — итоговый скрипт</label>
                                    <span class="piwiz-startup__editor-hint">редактируйте вручную при необходимости</span>
                                </div>
                                <button type="button" class="piwiz-btn piwiz-btn--ghost piwiz-btn--sm" id="pi-startup-reset-editor">Очистить</button>
                            </div>
                            <textarea class="piwiz-code piwiz-code--editor" name="startup_script_text" id="pi-startup" rows="16" spellcheck="false">{{ old('startup_script_text', $row->startup_script_text ?? '') }}</textarea>
                        </div>
                    </div>
                </section>

                {{-- 7 Check — конфигуратор автопроверки --}}
                <section class="piwiz-panel js-piwiz-panel" data-panel="check" hidden data-headline="{{ $wizardSteps[6]['headline'] }}" data-lead="{{ $wizardSteps[6]['lead'] }}">
                    <div class="piwiz-tabs" role="tablist">
                        <button type="button" class="piwiz-tab is-active js-piwiz-check-tab" data-check-tab="tasks">Конструктор заданий</button>
                        <button type="button" class="piwiz-tab js-piwiz-check-tab" data-check-tab="packs">Пакеты и шаблоны</button>
                    </div>
                    <div class="piwiz-check-pane js-piwiz-check-pane" data-check-pane="tasks">
                        <p class="piwiz-check__lead">Выберите тип проверки в строке — поля подстроятся сами. «Примеры» подскажут готовые значения. Bash можно раскрыть под строкой.</p>

                        <div class="piwiz-check__shortcuts">
                            <div class="piwiz-check__shortcut-group">
                                <span class="piwiz-check__shortcut-title">Шаблоны</span>
                                <div class="piwiz-check__shortcut-chips">
                                    @foreach ($checkExampleGrids as $ex)
                                        <button type="button" class="piwiz-check__chip js-pi-check-example">{{ $ex['title'] }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="piwiz-check__shortcut-group">
                                <span class="piwiz-check__shortcut-title">+ задание</span>
                                <div class="piwiz-check__shortcut-chips">
                                    <button type="button" class="piwiz-check__chip js-pi-check-quick" data-type="file_exists">Файл</button>
                                    <button type="button" class="piwiz-check__chip js-pi-check-quick" data-type="file_contains">Строка</button>
                                    <button type="button" class="piwiz-check__chip js-pi-check-quick" data-type="service_active">Служба</button>
                                    <button type="button" class="piwiz-check__chip js-pi-check-quick" data-type="package_installed">Пакет</button>
                                    <button type="button" class="piwiz-check__chip js-pi-check-quick" data-type="command">Команда</button>
                                </div>
                            </div>
                        </div>

                        <div class="piwiz-check__toolbar-card">
                            <div class="piwiz-check__toolbar-row">
                                <label class="piwiz-check__mini">Заданий <input type="number" id="pi-check-task-num" min="1" max="20" value="4"></label>
                                <label class="piwiz-check__mini">MAX <input type="number" id="pi-check-max" min="1" max="1000" value="100"></label>
                                <button type="button" class="piwiz-check__link-btn" id="pi-check-build-grid">Сетка ~/N.txt</button>
                                <button type="button" class="piwiz-check__link-btn" id="pi-check-split-points">Поровну</button>
                                <button type="button" class="piwiz-check__link-btn" id="pi-check-add-row">+ строка</button>
                            </div>
                            <button type="button" class="piwiz-btn piwiz-btn--primary" id="pi-check-generate">Сгенерировать check.sh</button>
                        </div>
                        <p class="piwiz-check__gen-status" id="pi-check-gen-status" hidden role="status"></p>

                        <div class="piwiz-task-list" id="pi-check-task-list">
                            <table class="piwiz-task-table" id="pi-check-task-table">
                                <thead class="piwiz-task-table__head">
                                    <tr>
                                        <th>№</th>
                                        <th>Баллы</th>
                                        <th colspan="4">Настройка проверки</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="pi-check-task-body"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="piwiz-check-pane js-piwiz-check-pane" data-check-pane="packs" hidden>
                        <p class="piwiz-scenario-hint">Пакеты → строки в конструктор. Функции hint/ok/fail — в скрипт. Готовый скрипт — заменяет редактор.</p>
                        <div class="piwiz-scenario-toolbar">
                            <button type="button" class="piwiz-btn piwiz-btn--primary piwiz-btn--sm" id="pi-check-apply-packs">Добавить пакеты</button>
                            <button type="button" class="piwiz-btn piwiz-btn--ghost piwiz-btn--sm" id="pi-check-apply-helpers">Вставить функции</button>
                        </div>
                        @foreach ($checkCategories as $catId => $catLabel)
                            @php $catItems = array_values(array_filter($checkPresets, static fn ($p) => ($p['category'] ?? '') === $catId)); @endphp
                            @if ($catItems !== [])
                                <div class="piwiz-scenario-group">
                                    <h3 class="piwiz-scenario-group__title">{{ $catLabel }}</h3>
                                    <div class="piwiz-scenario-grid">
                                        @foreach ($catItems as $cp)
                                            @if (($cp['type'] ?? '') === 'pack')
                                                <label class="piwiz-scenario-card">
                                                    <input type="checkbox" class="js-pi-check-pack" value="{{ $cp['id'] }}">
                                                    <span class="piwiz-scenario-card__inner">
                                                        <span class="piwiz-scenario-card__title">{{ $cp['title'] }}</span>
                                                        <span class="piwiz-scenario-card__desc">{{ $cp['description'] }}</span>
                                                    </span>
                                                </label>
                                            @elseif (($cp['type'] ?? '') === 'helper')
                                                <label class="piwiz-scenario-card">
                                                    <input type="checkbox" class="js-pi-check-helper" value="{{ $cp['id'] }}">
                                                    <span class="piwiz-scenario-card__inner">
                                                        <span class="piwiz-scenario-card__title">{{ $cp['title'] }}</span>
                                                        <span class="piwiz-scenario-card__desc">{{ $cp['description'] }}</span>
                                                    </span>
                                                </label>
                                            @else
                                                <button type="button" class="piwiz-scenario-card piwiz-scenario-card--btn js-pi-check-full" data-id="{{ $cp['id'] }}">
                                                    <span class="piwiz-scenario-card__inner">
                                                        <span class="piwiz-scenario-card__title">{{ $cp['title'] }}</span>
                                                        <span class="piwiz-scenario-card__desc">{{ $cp['description'] }}</span>
                                                    </span>
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="piwiz-editor-wrap">
                        <div class="piwiz-editor-wrap__head">
                            <label for="pi-check"><strong>check.sh</strong></label>
                            <button type="button" class="piwiz-btn piwiz-btn--ghost piwiz-btn--sm" id="pi-check-reset-editor">Очистить</button>
                        </div>
                        <textarea class="piwiz-code piwiz-code--full" name="check_script_text" id="pi-check" rows="18" spellcheck="false">{{ old('check_script_text', $row->check_script_text) }}</textarea>
                        <p class="piwiz-field__tip"><code>===PRACTICE_RESULT_JSON===</code> и JSON score/max обязательны.</p>
                    </div>
                </section>

                {{-- 8 Обзор --}}
                <section class="piwiz-panel js-piwiz-panel" data-panel="review" hidden id="step-review" data-headline="{{ $wizardSteps[7]['headline'] }}" data-lead="{{ $wizardSteps[7]['lead'] }}">
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.75rem">
                        <button type="button" class="piwiz-btn piwiz-btn--ghost" id="pi-refresh-preview">Обновить превью</button>
                        @if ($reimportUrl)
                            <button type="submit" class="piwiz-btn piwiz-btn--ghost" formaction="{{ $reimportUrl }}" formmethod="post">Из шаблона lab-m*</button>
                        @endif
                        @if (! $isNew && $daemonConfigured && $buildUrl)
                            <button type="submit" class="piwiz-btn piwiz-btn--primary" formaction="{{ $buildUrl }}" formmethod="post" id="piwiz-build-btn">Собрать на стенде</button>
                        @endif
                    </div>
                    <div class="piwiz-preview" id="piwiz-preview">
                        <div class="piwiz-tabs piwiz-preview-tabs" role="tablist">
                            <button type="button" class="piwiz-tab is-active js-piwiz-preview-tab" data-preview-tab="dockerfile" role="tab" aria-selected="true">Dockerfile</button>
                            <button type="button" class="piwiz-tab js-piwiz-preview-tab" data-preview-tab="startup" role="tab" aria-selected="false">startup.sh</button>
                            <button type="button" class="piwiz-tab js-piwiz-preview-tab" data-preview-tab="check" role="tab" aria-selected="false">check.sh</button>
                        </div>
                        <div class="piwiz-preview-body">
                            <div class="piwiz-preview-panel is-active js-piwiz-preview-panel" data-preview-panel="dockerfile" id="piwiz-prev-docker" role="tabpanel">
                                <pre class="piwiz-preview-code" id="pi-preview-dockerfile">—</pre>
                            </div>
                            <div class="piwiz-preview-panel js-piwiz-preview-panel" data-preview-panel="startup" id="piwiz-prev-startup" role="tabpanel" hidden>
                                <pre class="piwiz-preview-code" id="pi-preview-startup">—</pre>
                            </div>
                            <div class="piwiz-preview-panel js-piwiz-preview-panel" data-preview-panel="check" id="piwiz-prev-check" role="tabpanel" hidden>
                                <pre class="piwiz-preview-code" id="pi-preview-check">—</pre>
                            </div>
                        </div>
                    </div>
                    @if (! $isNew && $row->last_build_log)
                        <details style="margin-top:1rem"><summary style="cursor:pointer;font-weight:700;color:var(--piwiz-brand)">Лог сборки</summary>
                            <pre class="check-log-pre" style="margin-top:0.5rem;max-height:200px;overflow:auto">{{ $row->last_build_log }}</pre>
                        </details>
                    @endif
                </section>

                <div id="piwiz-wizard-err" class="piwiz-flash piwiz-flash--err" hidden role="alert"></div>

                <footer class="piwiz-foot">
                    <button type="button" class="piwiz-btn piwiz-btn--ghost js-piwiz-prev" disabled>← Назад</button>
                    <div class="piwiz-foot__right">
                        <button type="button" class="piwiz-btn piwiz-btn--primary js-piwiz-next">Далее →</button>
                        <button type="button" class="piwiz-btn piwiz-btn--primary js-piwiz-save" id="piwiz-save-btn" hidden>{{ $isNew ? 'Создать образ' : 'Сохранить' }}</button>
                    </div>
                </footer>
            </form>
        </div>

        <form method="post" action="{{ $cloneUrl }}" id="pi-clone-form" hidden>
            @csrf
            <input type="hidden" name="source_id" id="pi-clone-source">
            <input type="hidden" name="title" id="pi-clone-title">
            <input type="hidden" name="docker_tag" id="pi-clone-tag">
        </form>

        <div class="piwiz-overlay" id="piwiz-overlay" hidden aria-live="polite">
            <div class="piwiz-overlay__card piwiz-overlay__card--build">
                <p class="piwiz-overlay__title" id="piwiz-overlay-title">Создание образа</p>
                <p class="piwiz-overlay__sub" id="piwiz-overlay-sub">Подождите, операция может занять несколько минут…</p>
                <ol class="piwiz-build-steps" id="piwiz-build-steps" hidden></ol>
                <div class="piwiz-spinner" id="piwiz-overlay-spinner" aria-hidden="true"></div>
                <pre class="piwiz-build-log" id="piwiz-build-log" hidden></pre>
                <button type="button" class="piwiz-btn piwiz-btn--primary" id="piwiz-overlay-close" hidden>Закрыть</button>
            </div>
        </div>

        <div class="piwiz-modal" id="piwiz-check-modal" hidden aria-hidden="true">
            <div class="piwiz-modal__backdrop js-piwiz-check-modal-close" tabindex="-1"></div>
            <div class="piwiz-modal__panel piwiz-modal__panel--wide" role="dialog" aria-modal="true" aria-labelledby="piwiz-check-modal-title">
                <button type="button" class="piwiz-modal__x js-piwiz-check-modal-close" aria-label="Закрыть">×</button>
                <h3 class="piwiz-modal__title" id="piwiz-check-modal-title">Справка</h3>
                <p class="piwiz-modal__desc" id="piwiz-check-modal-desc"></p>
                <div class="piwiz-modal__section" id="piwiz-check-modal-examples-wrap" hidden>
                    <p class="piwiz-modal__section-title">Примеры</p>
                    <div class="piwiz-modal__chips" id="piwiz-check-modal-examples"></div>
                </div>
                <div class="piwiz-modal__section" id="piwiz-check-modal-hints-wrap" hidden>
                    <p class="piwiz-modal__section-title">Подсказки HINT</p>
                    <div class="piwiz-modal__chips" id="piwiz-check-modal-hints"></div>
                </div>
                <p class="piwiz-modal__tip" id="piwiz-check-modal-tip"></p>
                <div class="piwiz-modal__section">
                    <p class="piwiz-modal__section-title">Фрагмент check.sh</p>
                    <pre class="piwiz-modal__preview" id="piwiz-check-modal-preview"></pre>
                </div>
                <div class="piwiz-modal__footer">
                    <button type="button" class="piwiz-btn piwiz-btn--ghost js-piwiz-check-modal-close">Отмена</button>
                    <button type="button" class="piwiz-btn piwiz-btn--primary" id="piwiz-check-modal-apply">Применить</button>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/practice-image-check-wizard.js') }}?v=6"></script>
    <script>
        (function () {
            var steps = @json(array_column($wizardSteps, 'step'));
            var stepMeta = @json($wizardSteps);
            var builtins = @json($builtinTemplates);
            var startupPresets = @json($startupPresets);
            var checkPresets = @json($checkPresets);
            var checkTaskTypes = @json($checkTaskTypes);
            var checkExampleGrids = @json($checkExampleGrids);
            var checkCommonServices = @json($checkCommonServices);
            var checkServiceStates = @json($checkServiceStates);
            var osChoices = @json($osChoices);
            var previewUrl = @json($previewUrl);
            var csrf = @json(csrf_token());
            var wizardPreset = @json($wizardPreset ?? '');
            var isNewImage = @json($isNew);
            var daemonConfigured = @json($daemonConfigured);
            var buildUrl = @json($buildUrl);
            var stepTotal = steps.length;
            var cur = 0;

            var form = document.getElementById('ap-piwiz-form');
            var panels = document.querySelectorAll('.js-piwiz-panel');
            var stepBtns = document.querySelectorAll('.js-piwiz-go');
            var btnPrev = document.querySelector('.js-piwiz-prev');
            var btnNext = document.querySelector('.js-piwiz-next');
            var btnSave = document.querySelector('.js-piwiz-save');
            var fillEl = document.getElementById('piwiz-progress-fill');
            var pctEl = document.getElementById('piwiz-step-pct');
            var numEl = document.getElementById('piwiz-step-num');
            var titleEl = document.getElementById('piwiz-stage-title');
            var leadEl = document.getElementById('piwiz-stage-lead');
            var badgeEl = document.getElementById('piwiz-stage-badge');
            var overlay = document.getElementById('piwiz-overlay');
            var overlayTitle = document.getElementById('piwiz-overlay-title');
            var overlaySub = document.getElementById('piwiz-overlay-sub');
            var overlaySteps = document.getElementById('piwiz-build-steps');
            var overlaySpinner = document.getElementById('piwiz-overlay-spinner');
            var overlayLog = document.getElementById('piwiz-build-log');
            var overlayClose = document.getElementById('piwiz-overlay-close');
            var hiddenTpl = document.getElementById('pi-base-template');
            var initTpl = document.getElementById('pi-init-template');

            var startupById = {};
            startupPresets.forEach(function (p) { startupById[p.id] = p; });
            var checkById = {};
            checkPresets.forEach(function (p) { checkById[p.id] = p; });
            var checkHelpersSelected = [];
            var startupSelectionOrder = [];

            var buildPhaseTemplate = [
                { id: 'save', label: 'Сохранение настроек образа', status: 'pending' },
                { id: 'files', label: 'Создание Dockerfile и скриптов', status: 'pending' },
                { id: 'pull', label: 'Загрузка базового образа из реестра', status: 'pending' },
                { id: 'build', label: 'Сборка образа на стенде', status: 'pending' },
                { id: 'done', label: 'Готово', status: 'pending' }
            ];

            function clonePhases() {
                return buildPhaseTemplate.map(function (p) {
                    return { id: p.id, label: p.label, status: p.status };
                });
            }

            function setPhase(phases, id, status) {
                phases.forEach(function (p) {
                    if (p.id === id) p.status = status;
                });
                renderBuildPhases(phases);
            }

            function renderBuildPhases(phases) {
                if (!overlaySteps) return;
                overlaySteps.innerHTML = '';
                phases.forEach(function (p) {
                    var li = document.createElement('li');
                    li.className = 'piwiz-build-step piwiz-build-step--' + p.status;
                    li.textContent = p.label;
                    overlaySteps.appendChild(li);
                });
            }

            function showBuildOverlay(title, sub, phases) {
                if (!overlay) return;
                overlay.hidden = false;
                document.body.style.overflow = 'hidden';
                if (overlayTitle) overlayTitle.textContent = title || 'Сборка образа';
                if (overlaySub) overlaySub.textContent = sub || '';
                if (overlaySteps) {
                    overlaySteps.hidden = false;
                    renderBuildPhases(phases || clonePhases());
                }
                if (overlaySpinner) overlaySpinner.hidden = false;
                if (overlayLog) { overlayLog.hidden = true; overlayLog.textContent = ''; }
                if (overlayClose) overlayClose.hidden = true;
            }

            function finishBuildOverlay(ok, log, redirectUrl) {
                if (overlaySpinner) overlaySpinner.hidden = true;
                if (overlayLog && log) {
                    overlayLog.hidden = false;
                    overlayLog.textContent = log;
                }
                if (overlaySub) {
                    overlaySub.textContent = ok
                        ? 'Образ успешно создан и собран на стенде.'
                        : 'Сборка завершилась с ошибкой. Подробности в логе ниже.';
                }
                if (overlayClose) {
                    overlayClose.hidden = false;
                    overlayClose.onclick = function () {
                        if (ok && redirectUrl) {
                            window.location.href = redirectUrl;
                        } else {
                            overlay.hidden = true;
                            document.body.style.overflow = '';
                        }
                    };
                }
                if (ok && redirectUrl) {
                    setTimeout(function () { window.location.href = redirectUrl; }, 1800);
                }
                releaseSaveBtn();
            }

            function setLoading(on, title, sub) {
                if (!overlay) return;
                if (!on) {
                    overlay.hidden = true;
                    document.body.style.overflow = '';
                    return;
                }
                showBuildOverlay(title, sub, null);
                if (overlaySteps) overlaySteps.hidden = true;
            }

            function formPayload() {
                var fd = new FormData(form);
                var checkEl = document.getElementById('pi-check');
                if (checkEl) fd.set('check_script_text', checkEl.value || '');
                return fd;
            }

            function fetchJson(url, fd) {
                return fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                }).then(function (r) {
                    return r.text().then(function (text) {
                        var j = {};
                        try {
                            j = text ? JSON.parse(text) : {};
                        } catch (e) {
                            j = { ok: false, error: 'Ответ сервера не JSON (код ' + r.status + ')' };
                        }
                        return { status: r.status, body: j };
                    });
                });
            }

            function applyServerPhases(phases, serverPhases) {
                if (!serverPhases || !serverPhases.length) return phases;
                serverPhases.forEach(function (sp) {
                    setPhase(phases, sp.id, sp.status);
                });
                return phases;
            }

            function runBuild(url, phases, redirectFallback) {
                setPhase(phases, 'pull', 'active');
                setPhase(phases, 'build', 'active');
                return fetchJson(url, new FormData()).then(function (res) {
                    var j = res.body || {};
                    applyServerPhases(phases, j.phases);
                    if (j.ok) {
                        setPhase(phases, 'pull', 'done');
                        setPhase(phases, 'build', 'done');
                        setPhase(phases, 'done', 'done');
                    } else {
                        setPhase(phases, 'build', 'error');
                        setPhase(phases, 'done', 'error');
                    }
                    finishBuildOverlay(!!j.ok, j.log || j.error || '', j.redirect || redirectFallback);
                    return j;
                }).catch(function (err) {
                    setPhase(phases, 'build', 'error');
                    setPhase(phases, 'done', 'error');
                    finishBuildOverlay(false, String(err), redirectFallback);
                });
            }

            function releaseSaveBtn() {
                if (btnSave) btnSave.disabled = false;
            }

            function createAndBuild() {
                var phases = clonePhases();
                showBuildOverlay('Создание и сборка образа', 'Сохраняем рецепт, затем соберём на стенде…', phases);
                setPhase(phases, 'save', 'active');
                fetchJson(form.action, formPayload()).then(function (res) {
                    var j = res.body || {};
                    if (!j.ok || res.status >= 400) {
                        setPhase(phases, 'save', 'error');
                        finishBuildOverlay(false, j.error || 'Не удалось сохранить образ', null);
                        releaseSaveBtn();
                        return;
                    }
                    setPhase(phases, 'save', 'done');
                    setPhase(phases, 'files', 'done');
                    if (!j.daemon_configured || !j.build_url) {
                        setPhase(phases, 'done', 'done');
                        finishBuildOverlay(true, j.daemon_configured === false
                            ? 'Рецепт сохранён. Сборка на стенде недоступна: настройте lab-daemon в .env.'
                            : '', j.edit_url);
                        releaseSaveBtn();
                        return;
                    }
                    return runBuild(j.build_url, phases, j.edit_url);
                }).catch(function (err) {
                    setPhase(phases, 'save', 'error');
                    finishBuildOverlay(false, String(err), null);
                    releaseSaveBtn();
                });
            }

            function updateChrome() {
                var pct = Math.round(((cur + 1) / stepTotal) * 100);
                if (fillEl) fillEl.style.width = pct + '%';
                if (pctEl) pctEl.textContent = pct + '%';
                if (numEl) numEl.textContent = String(cur + 1);
                if (badgeEl) badgeEl.textContent = 'Шаг ' + (cur + 1) + ' · ' + (stepMeta[cur] ? stepMeta[cur].title : '');
                if (titleEl && stepMeta[cur]) titleEl.textContent = stepMeta[cur].headline || stepMeta[cur].title;
                if (leadEl && stepMeta[cur]) leadEl.textContent = stepMeta[cur].lead || '';
                stepBtns.forEach(function (b, i) {
                    b.classList.remove('is-current', 'is-done');
                    if (i < cur) b.classList.add('is-done');
                    if (i === cur) b.classList.add('is-current');
                });
            }

            function showStep(idx) {
                cur = Math.max(0, Math.min(idx, stepTotal - 1));
                var name = steps[cur];
                panels.forEach(function (p) {
                    p.hidden = p.getAttribute('data-panel') !== name;
                });
                if (btnPrev) btnPrev.disabled = cur === 0;
                if (btnNext) btnNext.hidden = cur >= stepTotal - 1;
                if (btnSave) btnSave.hidden = cur !== stepTotal - 1;
                updateChrome();
                if (name === 'review') refreshPreview();
                if (history.replaceState) {
                    history.replaceState(null, '', '#step-' + name);
                } else {
                    location.hash = 'step-' + name;
                }
            }

            function validateStep(idx) {
                if (steps[idx] === 'basics') {
                    var t = document.getElementById('pi-title');
                    var tag = document.getElementById('pi-tag');
                    if (!t || !t.value.trim()) { t && t.focus(); return false; }
                    if (!tag || !tag.value.trim()) { tag && tag.focus(); return false; }
                }
                return true;
            }

            var wizardErr = document.getElementById('piwiz-wizard-err');

            function showWizardError(msg) {
                if (!wizardErr) return;
                if (!msg) {
                    wizardErr.hidden = true;
                    wizardErr.textContent = '';
                    return;
                }
                wizardErr.textContent = msg;
                wizardErr.hidden = false;
                wizardErr.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }

            function validateBeforeSave() {
                showWizardError('');
                for (var j = 0; j < stepTotal; j++) {
                    if (!validateStep(j)) {
                        showStep(j);
                        var hint = (stepMeta[j] && stepMeta[j].title) ? stepMeta[j].title : ('шаг ' + (j + 1));
                        showWizardError('Заполните обязательные поля на шаге «' + hint + '».');
                        return false;
                    }
                }
                return true;
            }

            function handleSaveClick() {
                if (!validateBeforeSave()) return;
                showWizardError('');
                if (btnSave) btnSave.disabled = true;
                if (isNewImage) {
                    createAndBuild();
                    return;
                }
                setLoading(true, 'Сохраняем рецепт', 'Записываем в каталог…');
                form.submit();
            }

            stepBtns.forEach(function (b) {
                b.addEventListener('click', function () {
                    var i = parseInt(b.getAttribute('data-index'), 10);
                    if (i > cur) {
                        for (var j = cur; j < i; j++) {
                            if (!validateStep(j)) { showStep(j); return; }
                        }
                    }
                    showStep(i);
                });
            });
            if (btnPrev) btnPrev.addEventListener('click', function () { showStep(cur - 1); });
            if (btnNext) btnNext.addEventListener('click', function () {
                if (!validateStep(cur)) return;
                showStep(cur + 1);
            });

            document.querySelectorAll('.js-piwiz-tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var id = tab.getAttribute('data-tab');
                    document.querySelectorAll('.js-piwiz-tab').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
                    document.querySelectorAll('.js-piwiz-tab-pane').forEach(function (p) {
                        p.hidden = p.getAttribute('data-tab-pane') !== id;
                    });
                });
            });

            function applyBuiltin(id) {
                var t = builtins.find(function (x) { return x.id === id; });
                if (!t) return;
                if (hiddenTpl) hiddenTpl.value = t.template;
                if (initTpl && @json($isNew)) initTpl.value = id === 'blank' ? '0' : '1';
                var osR = document.querySelector('input[name="base_os"][value="' + t.os + '"]');
                if (osR) osR.checked = true;
                var tag = document.getElementById('pi-tag');
                var title = document.getElementById('pi-title');
                if (tag && !tag.value.trim()) tag.value = t.tag_hint || '';
                if (title && !title.value.trim() && t.title) title.value = t.title;
                if (t.packages && t.packages.length) {
                    var add = document.getElementById('pkg-add');
                    if (add) {
                        var lines = (add.value || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
                        t.packages.forEach(function (p) { if (lines.indexOf(p) === -1) lines.push(p); });
                        add.value = lines.join('\n') + '\n';
                    }
                }
                Object.keys(t.features || {}).forEach(function (k) {
                    if (k === 'create_user') return;
                    var cb = document.querySelector('[name="features[' + k + ']"][type="checkbox"]');
                    if (cb) cb.checked = !!t.features[k];
                });
                if (t.id === 'lab-m1' || t.id === 'lab-m3') {
                    if (startupSelectionOrder.indexOf('answer-files') === -1) {
                        startupSelectionOrder.push('answer-files');
                    }
                    updateStartupSelection();
                    mergeStartupIntoEditor('replace');
                }
            }

            document.querySelectorAll('.js-pi-pick-template').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.js-pi-pick-template').forEach(function (b) { b.classList.remove('is-picked'); });
                    btn.classList.add('is-picked');
                    applyBuiltin(btn.getAttribute('data-template-id'));
                    if (!validateStep(cur)) return;
                    showStep(1);
                });
            });

            document.querySelectorAll('.js-pi-clone-lib').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setLoading(true, 'Копируем рецепт', btn.getAttribute('data-title') || '');
                    document.getElementById('pi-clone-source').value = btn.getAttribute('data-id');
                    document.getElementById('pi-clone-title').value = btn.getAttribute('data-title');
                    document.getElementById('pi-clone-tag').value = btn.getAttribute('data-tag');
                    document.getElementById('pi-clone-form').submit();
                });
            });

            if (btnSave) {
                btnSave.addEventListener('click', handleSaveClick);
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    var sub = e.submitter;
                    if (sub && sub.id === 'piwiz-build-btn' && buildUrl) {
                        e.preventDefault();
                        var phases = clonePhases();
                        setPhase(phases, 'save', 'done');
                        showBuildOverlay('Сборка Docker-образа', 'Загрузка базового образа и сборка на стенде…', phases);
                        setPhase(phases, 'files', 'done');
                        runBuild(buildUrl, phases, window.location.href.split('#')[0] + '#step-review');
                        return;
                    }
                    if (sub && sub.classList.contains('js-piwiz-save')) {
                        e.preventDefault();
                        handleSaveClick();
                    }
                });
            }

            function appendPkg(name) {
                var add = document.getElementById('pkg-add');
                if (!add) return;
                var lines = (add.value || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
                if (lines.indexOf(name) === -1) lines.push(name);
                add.value = lines.join('\n') + '\n';
            }
            document.querySelectorAll('.js-pi-pkg-chip').forEach(function (b) {
                b.addEventListener('click', function () { appendPkg(b.getAttribute('data-pkg')); });
            });
            document.querySelectorAll('.js-pi-pkg-group-all').forEach(function (b) {
                b.addEventListener('click', function () {
                    try { JSON.parse(b.getAttribute('data-pkgs') || '[]').forEach(appendPkg); } catch (e) {}
                });
            });
            var startupEditor = document.getElementById('pi-startup');
            var startupDirty = false;

            function getSelectedStartupIds() {
                return startupSelectionOrder.slice();
            }

            function startupPickId(inp) {
                return inp && inp.value ? inp.value : '';
            }

            function syncStartupPickButtons() {
                document.querySelectorAll('.js-pi-startup-pick').forEach(function (inp) {
                    var id = startupPickId(inp);
                    var on = id !== '' && startupSelectionOrder.indexOf(id) >= 0;
                    inp.checked = on;
                    var item = inp.closest('.piwiz-startup__item');
                    if (item) item.classList.toggle('is-selected', on);
                });
            }

            function mergeStartupBlocks(ids) {
                var header = "#!/usr/bin/env bash\nset -euo pipefail\n";
                if (!ids.length) {
                    return header + "\n# Выберите сценарии выше или напишите свой код\n";
                }
                var parts = [];
                ids.forEach(function (id) {
                    var p = startupById[id];
                    if (!p) return;
                    var body = (p.body || '').trim();
                    if (!body && p.script) {
                        body = String(p.script).replace(/^#![^\n]*\n/, '').replace(/^set -euo pipefail\n?/, '').trim();
                    }
                    if (id === 'empty' && ids.length > 1) return;
                    parts.push("# --- " + (p.title || id) + " ---\n" + body);
                });
                return header + "\n\n" + parts.join("\n\n") + "\n";
            }

            function updateStartupSelection() {
                var ids = getSelectedStartupIds();
                var n = String(ids.length);
                var countEl = document.getElementById('pi-startup-count');
                var countPalette = document.getElementById('pi-startup-count-palette');
                if (countEl) countEl.textContent = n;
                if (countPalette) countPalette.textContent = n;
                syncStartupPickButtons();
                var orderEl = document.getElementById('pi-startup-order');
                if (orderEl) {
                    if (!ids.length) {
                        orderEl.hidden = true;
                        orderEl.innerHTML = '';
                    } else {
                        orderEl.hidden = false;
                        orderEl.innerHTML = '';
                        ids.forEach(function (id) {
                            var p = startupById[id];
                            var inp = document.querySelector('.js-pi-startup-pick[value="' + id.replace(/"/g, '\\"') + '"]');
                            var title = (inp && inp.getAttribute('data-title')) || (p && p.title) || id;
                            var li = document.createElement('li');
                            if (p && p.category === 'break') li.className = 'is-break';
                            li.textContent = title;
                            orderEl.appendChild(li);
                        });
                    }
                }
                return ids;
            }

            function setStartupPick(id, on, mergeEditor) {
                if (!id) return;
                var idx = startupSelectionOrder.indexOf(id);
                if (on && idx < 0) startupSelectionOrder.push(id);
                if (!on && idx >= 0) startupSelectionOrder.splice(idx, 1);
                updateStartupSelection();
                if (mergeEditor) mergeStartupIntoEditor('replace');
            }

            function mergeStartupIntoEditor(mode) {
                var ta = startupEditor;
                if (!ta) return;
                var ids = updateStartupSelection();
                var merged = mergeStartupBlocks(ids);
                if (mode === 'append' && (ta.value || '').trim()) {
                    ta.value = ta.value.replace(/\s+$/, '') + "\n\n" + merged.replace(/^#![^\n]*\n/, '').replace(/^set -euo pipefail\n?/, '');
                } else {
                    ta.value = merged;
                }
                startupDirty = false;
            }

            if (startupEditor) {
                startupEditor.addEventListener('input', function () { startupDirty = true; });
            }

            document.querySelectorAll('.js-pi-startup-pick').forEach(function (inp) {
                inp.addEventListener('change', function () {
                    setStartupPick(startupPickId(inp), inp.checked, document.getElementById('pi-startup-auto-merge')?.checked);
                });
            });

            document.getElementById('pi-startup-merge')?.addEventListener('click', function () {
                mergeStartupIntoEditor('replace');
            });
            document.getElementById('pi-startup-append')?.addEventListener('click', function () {
                mergeStartupIntoEditor('append');
            });

            var startupFilter = document.getElementById('pi-startup-filter');
            if (startupFilter) {
                startupFilter.addEventListener('input', function () {
                    var q = (startupFilter.value || '').trim().toLowerCase();
                    document.querySelectorAll('.piwiz-startup__item-wrap').forEach(function (wrap) {
                        var hay = wrap.getAttribute('data-search') || '';
                        wrap.classList.toggle('is-hidden', q !== '' && hay.indexOf(q) === -1);
                    });
                    document.querySelectorAll('.piwiz-startup__cat').forEach(function (cat) {
                        var visible = cat.querySelectorAll('.piwiz-startup__item-wrap:not(.is-hidden)').length;
                        cat.style.display = visible ? '' : 'none';
                    });
                });
            }

            document.getElementById('pi-startup-clear-sel')?.addEventListener('click', function () {
                startupSelectionOrder = [];
                updateStartupSelection();
                if (document.getElementById('pi-startup-auto-merge')?.checked) {
                    mergeStartupIntoEditor('replace');
                }
            });
            document.getElementById('pi-startup-reset-editor')?.addEventListener('click', function () {
                if (startupEditor) { startupEditor.value = "#!/usr/bin/env bash\nset -euo pipefail\n\n"; startupDirty = false; }
            });

            updateStartupSelection();
            var checkEditor = document.getElementById('pi-check');
            var taskBody = document.getElementById('pi-check-task-body');

            function escAttr(s) {
                return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }

            function escBash(s) {
                return String(s || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            }

            var checkWizard = typeof initPracticeImageCheckWizard === 'function' ? initPracticeImageCheckWizard({
                checkEditor: checkEditor,
                taskBody: taskBody,
                checkTaskTypes: checkTaskTypes,
                checkExampleGrids: checkExampleGrids,
                checkById: checkById,
                checkCommonServices: checkCommonServices,
                checkServiceStates: checkServiceStates,
                getHelpersSelected: function () { return checkHelpersSelected; },
                escAttr: escAttr,
                escBash: escBash,
            }) : null;

            document.querySelectorAll('.js-piwiz-check-tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var id = tab.getAttribute('data-check-tab');
                    document.querySelectorAll('.js-piwiz-check-tab').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
                    document.querySelectorAll('.js-piwiz-check-pane').forEach(function (p) {
                        p.hidden = p.getAttribute('data-check-pane') !== id;
                    });
                });
            });

            document.querySelectorAll('.js-piwiz-preview-tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var id = tab.getAttribute('data-preview-tab');
                    document.querySelectorAll('.js-piwiz-preview-tab').forEach(function (t) {
                        var on = t === tab;
                        t.classList.toggle('is-active', on);
                        t.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    document.querySelectorAll('.js-piwiz-preview-panel').forEach(function (p) {
                        var on = p.getAttribute('data-preview-panel') === id;
                        p.hidden = !on;
                        p.classList.toggle('is-active', on);
                    });
                });
            });

            document.getElementById('pi-check-apply-packs')?.addEventListener('click', function () {
                var tasks = checkWizard && checkWizard.readTasksFromTable ? checkWizard.readTasksFromTable() : [];
                document.querySelectorAll('.js-pi-check-pack:checked').forEach(function (cb) {
                    var pack = checkById[cb.value];
                    if (pack && pack.tasks && Array.isArray(pack.tasks)) {
                        pack.tasks.forEach(function (t) {
                            tasks.push({
                                num: tasks.length + 1,
                                points: t.points || 25,
                                type: t.type || 'file_exists',
                                file: t.file || '',
                                pattern: t.pattern || '',
                                hint: t.hint || '',
                            });
                        });
                    }
                });
                if (checkWizard && checkWizard.writeTasksToTable) checkWizard.writeTasksToTable(tasks);
                var numInp = document.getElementById('pi-check-task-num');
                if (numInp) numInp.value = String(tasks.length);
                document.querySelector('.js-piwiz-check-tab[data-check-tab="tasks"]')?.click();
            });

            document.querySelectorAll('.js-pi-check-helper').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    checkHelpersSelected = [];
                    document.querySelectorAll('.js-pi-check-helper:checked').forEach(function (x) {
                        checkHelpersSelected.push(x.value);
                    });
                });
            });

            document.getElementById('pi-check-apply-helpers')?.addEventListener('click', function () {
                checkHelpersSelected = [];
                document.querySelectorAll('.js-pi-check-helper:checked').forEach(function (x) {
                    checkHelpersSelected.push(x.value);
                });
                if (!checkEditor) return;
                var blocks = [];
                checkHelpersSelected.forEach(function (id) {
                    var p = checkById[id];
                    if (p && p.body) blocks.push(String(p.body).trim());
                });
                if (blocks.length) {
                    var head = '#!/bin/bash\nset -uo pipefail\n\n';
                    var rest = checkEditor.value.replace(/^#![^\n]*\n/, '').trim();
                    checkEditor.value = head + blocks.join('\n') + '\n\n' + rest;
                }
            });

            document.querySelectorAll('.js-pi-check-full').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var p = checkById[btn.getAttribute('data-id')];
                    if (p && p.script && checkEditor) checkEditor.value = p.script;
                });
            });

            document.getElementById('pi-fill-default-image')?.addEventListener('click', function () {
                var r = document.querySelector('input[name="base_os"]:checked');
                var inp = document.getElementById('pi-base-image');
                if (!r || !inp) return;
                var o = osChoices.find(function (x) { return x.value === r.value; });
                if (o && o.default_image) inp.value = o.default_image;
            });

            document.getElementById('pi-refresh-preview')?.addEventListener('click', refreshPreview);

            function refreshPreview() {
                if (!form || !previewUrl) return;
                var wrap = document.getElementById('piwiz-preview');
                if (wrap) wrap.classList.add('is-loading');
                var fd = new FormData(form);
                fd.append('check_script_text', (document.getElementById('pi-check') || {}).value || '');
                fetch(previewUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: fd
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (wrap) wrap.classList.remove('is-loading');
                    if (!j || !j.ok) return;
                    var d = document.getElementById('pi-preview-dockerfile');
                    var s = document.getElementById('pi-preview-startup');
                    var c = document.getElementById('pi-preview-check');
                    if (d) d.textContent = j.dockerfile || '—';
                    if (s) s.textContent = j.startup || '—';
                    if (c) c.textContent = j.check || '—';
                }).catch(function () {
                    if (wrap) wrap.classList.remove('is-loading');
                });
            }

            var pkgBtn = document.getElementById('pkg-search-btn');
            var pkgQ = document.getElementById('pkg-q');
            var pkgOut = document.getElementById('pkg-results');
            if (pkgBtn && pkgQ && pkgOut) {
                pkgBtn.addEventListener('click', function () {
                    var qq = (pkgQ.value || '').trim();
                    if (!qq) return;
                    pkgOut.innerHTML = '<span class="piwiz-field__tip">Поиск…</span>';
                    var os = (document.querySelector('input[name="base_os"]:checked') || {}).value || 'alt';
                    var base = (document.getElementById('pi-base-image') || {}).value || '';
                    var url = @json($piScope === 'docker' ? route('admin.docker.library.pkg.search') : route('admin.practice.images.pkg.search', $piRp)) + '&os=' + encodeURIComponent(os) + '&q=' + encodeURIComponent(qq) + '&base_image=' + encodeURIComponent(base) + '&limit=20';
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            pkgOut.innerHTML = '';
                            if (!j.ok) { pkgOut.textContent = j.error || 'Ошибка'; return; }
                            var lines = (j.data && j.data.lines) ? j.data.lines : [];
                            if (!lines.length) { pkgOut.textContent = 'Не найдено'; return; }
                            lines.forEach(function (ln) {
                                var name = (ln.split(' - ')[0] || ln).trim();
                                if (!name || name.indexOf('Pulling') !== -1) return;
                                var b = document.createElement('button');
                                b.type = 'button';
                                b.className = 'piwiz-chip';
                                b.textContent = '+ ' + name;
                                b.addEventListener('click', function () { appendPkg(name); });
                                pkgOut.appendChild(b);
                            });
                        });
                });
            }

            if (wizardPreset) {
                var pre = document.querySelector('.js-pi-pick-template[data-template-id="' + wizardPreset + '"]');
                if (pre) { pre.classList.add('is-picked'); applyBuiltin(wizardPreset); }
            }

            var hash = (location.hash || '').replace('#step-', '');
            if (hash && steps.indexOf(hash) !== -1) showStep(steps.indexOf(hash));
            else showStep(0);
        })();
    </script>
@endsection
