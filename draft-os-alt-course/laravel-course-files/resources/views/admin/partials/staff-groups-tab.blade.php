@php
    use App\Models\PortalStaff;
    use App\Support\LearnerDisplay;
    use App\Support\PortalStaffPermissionCatalog;
    $groupRoleLabels = [
        PortalStaff::ROLE_PORTAL_ADMIN => 'Администратор портала',
        PortalStaff::ROLE_COURSE_MODERATOR => 'Модератор',
        PortalStaff::ROLE_COURSE_CREATOR => 'Создатель курсов',
        PortalStaff::ROLE_COURSE_EDITOR => 'Редактор курсов',
        PortalStaff::ROLE_INSTRUCTOR => 'Преподаватель курса',
        PortalStaff::ROLE_COURSE_TESTER => 'Тестировщик курса',
    ];
@endphp

<div class="ap-staff-groups">
    @if ($groups->isEmpty())
        <div class="ap-card ap-staff-groups__empty">
            <p class="ap-muted" style="margin:0">Пока нет групп. Создайте первую — объедините сотрудников и выдайте им общие права (например, доступ к курсу или разделу Docker).</p>
        </div>
    @else
        <div class="ap-staff-groups__grid">
            @foreach ($groups as $group)
                @php
                    $permKeys = $group->permissions->pluck('permission')->map(fn ($p) => (string) $p)->all();
                    $memberIds = $group->members->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                    $courseIds = $group->courses->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                    $memberPreview = $group->members->take(4)->map(function ($m) {
                        $email = (string) ($m->learner?->email ?? '');
                        $name = $m->learner ? LearnerDisplay::portalDisplayName($m->learner) : '';
                        $label = $name !== '' ? $name : $email;
                        if ($label === '') {
                            return null;
                        }

                        return [
                            'id' => (int) $m->id,
                            'label' => $label,
                        ];
                    })->filter()->values()->all();
                @endphp
                <article class="ap-staff-group-card ap-staff-group-row"
                         data-group-id="{{ (int) $group->id }}"
                         data-name="{{ e($group->name) }}"
                         data-description="{{ e((string) ($group->description ?? '')) }}"
                         data-sort="{{ (int) $group->sort }}"
                         data-role="{{ e((string) ($group->role ?? PortalStaff::ROLE_COURSE_TESTER)) }}"
                         data-permissions="{{ e(implode(',', $permKeys)) }}"
                         data-member-ids="{{ e(implode(',', $memberIds)) }}"
                         data-course-ids="{{ e(implode(',', $courseIds)) }}">
                    <div class="ap-staff-group-card__head">
                        <h2 class="ap-staff-group-card__title">{{ $group->name }}</h2>
                        <div class="ap-staff-icon-actions">
                            <button type="button" class="ap-icon-btn ap-staff-group-edit" aria-label="Изменить" title="Изменить">
                                @include('partials.ap-icon', ['name' => 'pencil', 'size' => 'md'])
                            </button>
                            <button type="button" class="ap-icon-btn ap-icon-btn--danger ap-staff-group-delete-open" aria-label="Удалить" title="Удалить">
                                @include('partials.ap-icon', ['name' => 'trash', 'size' => 'md'])
                            </button>
                        </div>
                    </div>
                    @if (trim((string) ($group->description ?? '')) !== '')
                        <p class="ap-staff-group-card__desc">{{ $group->description }}</p>
                    @endif
                    <p class="ap-staff-group-card__meta">
                        <span class="ap-staff-group-card__role">{{ $groupRoleLabels[$group->role] ?? $group->role }}</span>
                        <span class="ap-staff-group-card__dot">·</span>
                        <span>{{ (int) $group->members_count }} @choice('участник|участника|участников', (int) $group->members_count)</span>
                        @if (count($permKeys) > 0)
                            <span class="ap-staff-group-card__dot">·</span>
                            <span>+{{ count($permKeys) }} доп.</span>
                        @endif
                        @if ($group->courses->isNotEmpty())
                            <span class="ap-staff-group-card__dot">·</span>
                            <span>{{ $group->courses->count() }} @choice('курс|курса|курсов', $group->courses->count())</span>
                        @endif
                    </p>
                    @if ($permKeys !== [])
                        <ul class="ap-staff-group-card__perms">
                            @foreach (array_slice($permKeys, 0, 5) as $pk)
                                <li>{{ PortalStaffPermissionCatalog::label($pk) }}</li>
                            @endforeach
                            @if (count($permKeys) > 5)
                                <li class="ap-muted">+{{ count($permKeys) - 5 }}</li>
                            @endif
                        </ul>
                    @endif
                    @if ($memberPreview !== [])
                        <p class="ap-staff-group-card__members ap-muted">
                            @foreach ($memberPreview as $i => $member)
                                @if ($i > 0)<span class="ap-staff-group-card__dot">·</span>@endif
                                <a class="ap-staff-group-card__member-link" href="{{ route('admin.staff.show', ['staff' => $member['id']]) }}">{{ $member['label'] }}</a>
                            @endforeach
                            @if ($group->members_count > 4) … @endif
                        </p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
