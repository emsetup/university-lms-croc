@extends('layouts.admin')

@php
    $tab = $settingsTab ?? 'moduli';
    $tabTitle = match ($tab) {
        'kurs' => 'О курсе — '.$course->title,
        'sertifikat' => 'Сертификат — '.$course->title,
        'istoriya' => 'История — '.$course->title,
        'soavtory' => 'Соавторы — '.$course->title,
        'gruppy' => 'Группы — '.$course->title,
        default => 'Модули курса — '.$course->title,
    };
@endphp
@section('title', $tabTitle)

@section('content')
    @php
        $tp = $ap ?? ['adminCourse' => $course->slug];
        $finalEditUrl = route('admin.quiz.edit.final', $tp);
        $dockerLibraryUrl = route('admin.docker.library');
        $kursUrl = route('admin.course.settings', array_merge($tp, ['tab' => 'kurs']));
        $moduliUrl = route('admin.course.settings', $tp);
        $certUrl = route('admin.course.settings', array_merge($tp, ['tab' => 'sertifikat']));
        $historyUrl = route('admin.course.settings', array_merge($tp, ['tab' => 'istoriya']));
        $soavtoryUrl = route('admin.course.settings', array_merge($tp, ['tab' => 'soavtory']));
        $gruppyUrl = route('admin.course.settings', array_merge($tp, ['tab' => 'gruppy']));
        $showCollaboratorsTab = ! empty($canManageCollaborators);
        $showGroupsTab = ! empty($canEditCourseMeta);
        $learnerSearchUrl = route('admin.course.learners.search', $tp);
    @endphp

    <div class="ap-course-settings-page">
        <nav class="ap-course-settings-subtabs" aria-label="Разделы настроек курса">
            <a href="{{ $moduliUrl }}" class="ap-course-settings-subtabs__a @if ($tab === 'moduli') is-active @endif">Модули</a>
            <a href="{{ $kursUrl }}" class="ap-course-settings-subtabs__a @if ($tab === 'kurs') is-active @endif">О курсе</a>
            <a href="{{ $certUrl }}" class="ap-course-settings-subtabs__a @if ($tab === 'sertifikat') is-active @endif">Сертификат</a>
            @if ($showCollaboratorsTab)
                <a href="{{ $soavtoryUrl }}" class="ap-course-settings-subtabs__a @if ($tab === 'soavtory') is-active @endif">Соавторы</a>
            @endif
            @if ($showGroupsTab)
                <a href="{{ $gruppyUrl }}" class="ap-course-settings-subtabs__a @if ($tab === 'gruppy') is-active @endif">Группы</a>
            @endif
            <a href="{{ $historyUrl }}" class="ap-course-settings-subtabs__a @if ($tab === 'istoriya') is-active @endif">История</a>
        </nav>

        @if ($tab === 'kurs')
            @include('admin.partials.course-settings-meta-form', [
                'course' => $course,
                'courseStatus' => $courseStatus,
                'builtImages' => $builtImages,
                'finalQuestionCount' => $finalQuestionCount,
                'tp' => $tp,
                'dockerLibraryUrl' => $dockerLibraryUrl,
                'finalEditUrl' => $finalEditUrl,
            ])
        @elseif ($tab === 'sertifikat')
            @include('admin.partials.course-settings-certificate-form', [
                'course' => $course,
                'courseStatus' => $courseStatus,
                'tp' => $tp,
            ])
        @elseif ($tab === 'istoriya')
            @include('admin.partials.course-settings-history', [
                'course' => $course,
                'changeLogEntries' => $changeLogEntries ?? collect(),
                'courseCreator' => $courseCreator ?? ['name' => '—', 'email' => '', 'staff_id' => null, 'initials' => '—'],
                'changeLogService' => $changeLogService ?? app(\App\Services\CourseChangeLogService::class),
                'canViewStaffProfiles' => ! empty($canViewStaffProfiles),
            ])
        @elseif ($tab === 'soavtory')
            @include('admin.partials.course-settings-collaborators', [
                'course' => $course,
                'collaborators' => $collaborators ?? collect(),
                'grantsByStaff' => $grantsByStaff ?? [],
                'grantTree' => $grantTree ?? ['modules' => []],
                'collaboratorLimit' => $collaboratorLimit ?? 5,
                'collaboratorCount' => $collaboratorCount ?? 0,
                'canManageCollaborators' => ! empty($canManageCollaborators),
                'ap' => $tp,
            ])
        @elseif ($tab === 'gruppy')
            @include('admin.partials.learner-groups-manager', [
                'course' => $course,
                'groups' => $courseLearnerGroups ?? collect(),
                'learners' => $courseEnrolledLearners ?? collect(),
                'groupScope' => 'course',
                'ap' => $tp,
            ])
        @else
            @include('admin.partials.course-modules-workbench', [
                'course' => $course,
                'modules' => $modules,
                'ap' => $tp,
                'adminKey' => $adminKey ?? '',
                'canEditCourseMeta' => ! empty($canEditCourseMeta),
                'canEditCourseStructure' => ! empty($canEditCourseStructure),
            ])
        @endif
    </div>
@endsection
