@php
    /** @var \App\Services\CourseChangeLogService $changeLogService */
    $changeLogService = $changeLogService ?? app(\App\Services\CourseChangeLogService::class);
    $creator = $courseCreator ?? ['name' => '—', 'email' => '', 'staff_id' => null, 'initials' => '—'];
@endphp

<div class="ap-card ap-course-history">
    <div class="ap-course-history__head">
        <h2 class="ap-card__title">История изменений</h2>
        <p class="ap-muted ap-course-history__creator">
            Создатель курса:
            @include('partials.staff-person-chip', [
                'staffId' => $creator['staff_id'] ?? null,
                'name' => $creator['name'] ?? '—',
                'email' => $creator['email'] ?? '',
                'initials' => $creator['initials'] ?? '—',
                'canLinkToProfile' => ! empty($canViewStaffProfiles),
                'size' => 'sm',
            ])
        </p>
    </div>

    @include('admin.partials.course-change-log-feed', [
        'entries' => $changeLogEntries ?? collect(),
        'changeLogService' => $changeLogService,
        'canViewStaffProfiles' => ! empty($canViewStaffProfiles),
        'showCourseLink' => false,
        'feedId' => 'ap-course-history-feed',
        'scope' => 'course-'.(int) ($course->id ?? 0),
    ])
</div>
