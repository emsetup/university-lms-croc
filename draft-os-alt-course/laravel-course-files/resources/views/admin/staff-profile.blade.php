@extends('layouts.admin')

@php
    /** @var array $profile */
    $staff = $profile['staff'];
    $tab = request()->query('tab', 'created');
    if (! in_array($tab, ['created', 'changes', 'activity'], true)) {
        $tab = 'created';
    }
    $profileUrl = fn (string $t) => route('admin.staff.show', ['staff' => $staff->id, 'tab' => $t]);
@endphp

@section('title', $profile['display_name'].' — Сотрудник')

@section('content')
    <div class="ap-staff-profile-page ap-fade">
        <header class="ap-staff-profile-hero ap-card">
            <div class="ap-staff-profile-hero__main">
                <span class="ap-staff-profile-hero__avatar" aria-hidden="true">{{ $profile['initials'] }}</span>
                <div class="ap-staff-profile-hero__text">
                    <h1 class="ap-staff-profile-hero__name">{{ $profile['display_name'] }}</h1>
                    @if ($profile['email'] !== '' && $profile['email'] !== $profile['display_name'])
                        <p class="ap-staff-profile-hero__email">{{ $profile['email'] }}</p>
                    @endif
                    <div class="ap-staff-profile-hero__badges">
                        <span class="{{ $profile['badge_class'] }}">{{ $profile['role_label'] }}</span>
                        @foreach ($profile['groups'] as $groupName)
                            <span class="ap-staff-group-tag">{{ $groupName }}</span>
                        @endforeach
                    </div>
                    <p class="ap-staff-profile-hero__meta ap-muted">
                        @if ($profile['last_login'] instanceof \DateTimeInterface)
                            Последний вход:
                            <time datetime="{{ $profile['last_login']->timezone(config('app.timezone'))->format('Y-m-d\TH:i') }}">
                                {{ $profile['last_login']->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                            </time>
                        @else
                            Последний вход: <span class="ap-muted">не зафиксирован</span>
                        @endif
                    </p>
                    @if (! empty($profile['access_comment']))
                        <p class="ap-staff-profile-hero__comment">
                            <span class="ap-staff-profile-hero__comment-label">Комментарий доступа:</span>
                            {{ $profile['access_comment'] }}
                        </p>
                    @endif
                </div>
            </div>
            <div class="ap-staff-profile-hero__actions">
                <a class="btn btn-ghost" href="{{ route('admin.staff.index') }}">← К списку</a>
                <a class="btn btn-primary" href="{{ route('admin.staff.edit', ['staff' => $staff->id]) }}">Редактировать</a>
            </div>
        </header>

        @if ($profile['assigned_courses'] !== [])
            <section class="ap-card ap-staff-profile-section">
                <h2 class="ap-card__title">Назначенные курсы</h2>
                <ul class="ap-staff-profile-course-list">
                    @foreach ($profile['assigned_courses'] as $c)
                        <li>
                            <a href="{{ route('admin.courses.enter', ['course' => $c['id']]) }}">{{ $c['title'] }}</a>
                            <code class="ap-staff-profile-slug">{{ $c['slug'] }}</code>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($profile['content_grants']))
            <section class="ap-card ap-staff-profile-section">
                <h2 class="ap-card__title">Гранты на разделы курсов</h2>
                <ul class="ap-staff-profile-course-list">
                    @foreach ($profile['content_grants'] as $g)
                        <li>
                            <a href="{{ $g['settings_url'] }}">{{ $g['course_title'] }}</a>
                            <span class="ap-muted">· {{ $g['resource_type'] }}@if ($g['resource_id']) #{{ $g['resource_id'] }}@endif · {{ $g['permission'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="ap-muted" style="font-size:0.85rem;margin-top:0.5rem">Изменить гранты можно в настройках курса → вкладка «Соавторы».</p>
            </section>
        @endif

        <nav class="ap-course-settings-subtabs ap-staff-profile-tabs" aria-label="Разделы профиля">
            <a href="{{ $profileUrl('created') }}" class="ap-course-settings-subtabs__a @if ($tab === 'created') is-active @endif">Создал</a>
            <a href="{{ $profileUrl('changes') }}" class="ap-course-settings-subtabs__a @if ($tab === 'changes') is-active @endif">Изменения</a>
            <a href="{{ $profileUrl('activity') }}" class="ap-course-settings-subtabs__a @if ($tab === 'activity') is-active @endif">Активность</a>
        </nav>

        @if ($tab === 'created')
            <section class="ap-staff-profile-grid">
                <div class="ap-card ap-staff-profile-section">
                    <h2 class="ap-card__title">Курсы</h2>
                    @if ($profile['created_courses'] === [])
                        <p class="ap-muted">Пока нет курсов, где указан этот сотрудник как создатель.</p>
                    @else
                        <div class="ap-staff-profile-mini-grid">
                            @foreach ($profile['created_courses'] as $c)
                                <a class="ap-staff-profile-mini-card" href="{{ route('admin.course.settings', ['adminCourse' => $c['slug']]) }}">
                                    <span class="ap-staff-profile-mini-card__title">{{ $c['title'] }}</span>
                                    <span class="ap-staff-profile-mini-card__meta">
                                        @if (! empty($c['is_archived']))
                                            <span class="ap-badge ap-badge--archive">Архив</span>
                                        @elseif (! empty($c['is_published']))
                                            <span class="ap-badge ap-badge--published">Опубликован</span>
                                        @else
                                            <span class="ap-badge ap-badge--draft">Черновик</span>
                                        @endif
                                        <code>{{ $c['slug'] }}</code>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="ap-card ap-staff-profile-section">
                    <h2 class="ap-card__title">Docker-образы</h2>
                    @if ($profile['created_images'] === [])
                        <p class="ap-muted">Нет образов с этим создателем.</p>
                    @else
                        <div class="ap-staff-profile-mini-grid ap-staff-profile-docker-grid">
                            @foreach ($profile['created_images'] as $img)
                                @php
                                    $lbs = (string) ($img['last_build_status'] ?? '');
                                    if ($lbs === 'running') {
                                        $dockerBadgeClass = 'ap-docker-badge ap-docker-badge--build';
                                        $dockerBadgeLabel = 'Сборка…';
                                    } elseif ($lbs === 'fail') {
                                        $dockerBadgeClass = 'ap-docker-badge ap-docker-badge--err';
                                        $dockerBadgeLabel = 'Ошибка';
                                    } elseif (! empty($img['is_built'])) {
                                        $dockerBadgeClass = 'ap-docker-badge ap-docker-badge--ok';
                                        $dockerBadgeLabel = 'Собран';
                                    } else {
                                        $dockerBadgeClass = 'ap-docker-badge ap-docker-badge--muted';
                                        $dockerBadgeLabel = 'Не собран';
                                    }
                                @endphp
                                <a
                                    class="ap-staff-profile-docker-card"
                                    href="{{ route('admin.docker.library.edit', ['id' => $img['id']]) }}"
                                    title="{{ $img['docker_tag'] }}"
                                >
                                    <span class="ap-staff-profile-docker-card__icon" aria-hidden="true">
                                        @include('partials.ap-icon', ['name' => 'terminal', 'size' => 'md'])
                                    </span>
                                    <span class="ap-staff-profile-docker-card__title">{{ $img['title'] }}</span>
                                    @if (! empty($img['description']))
                                        <span class="ap-staff-profile-docker-card__desc">{{ \Illuminate\Support\Str::limit($img['description'], 88) }}</span>
                                    @endif
                                    <span class="ap-staff-profile-docker-card__meta">
                                        <span class="{{ $dockerBadgeClass }}">{{ $dockerBadgeLabel }}</span>
                                        <code class="ap-staff-profile-docker-card__tag">{{ $img['docker_tag'] }}</code>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @elseif ($tab === 'changes')
            <div class="ap-card ap-staff-profile-section ap-course-history">
                <h2 class="ap-card__title">Изменения в курсах</h2>
                @include('admin.partials.course-change-log-feed', [
                    'entries' => $profile['change_logs'],
                    'changeLogService' => $changeLogService,
                    'canViewStaffProfiles' => true,
                    'showCourseLink' => true,
                    'feedId' => 'ap-staff-changes-feed',
                    'scope' => 'staff-'.(int) $staff->id,
                    'emptyMessage' => 'Записей пока нет.',
                ])
            </div>
        @else
            <div class="ap-card ap-staff-profile-section">
                <h2 class="ap-card__title">Активность в админке</h2>
                @if ($profile['admin_activity']->isEmpty())
                    <p class="ap-muted">Посещений админ-панели пока не зафиксировано (учитываются с интервалом ~15 мин).</p>
                @else
                    <ul class="ap-staff-profile-activity-list">
                        @foreach ($profile['admin_activity'] as $event)
                            @php
                                $when = $event->occurred_at?->timezone(config('app.timezone'))->format('d.m.Y H:i');
                            @endphp
                            <li class="ap-staff-profile-activity-item">
                                <time class="ap-staff-profile-activity-item__time" datetime="{{ $event->occurred_at?->toIso8601String() }}">{{ $when }}</time>
                                <code class="ap-staff-profile-activity-item__path">{{ $event->path }}</code>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>
@endsection
