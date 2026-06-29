@php
    /** @var \App\Models\Course|null $tabsCourse */
    /** @var string|null $adminCourseTab */
    $tabsCourse = $adminCurrentCourse ?? ($courseModel ?? null);
    $psaTabs = $portalStaffAccess ?? null;
    $cidTabs = $tabsCourse ? (int) $tabsCourse->id : 0;
    $canToolsTabs = $psaTabs && $psaTabs->canUseCourseAdminTools();
    $canViewContentTabs = $cidTabs > 0 && $psaTabs && (
        $canToolsTabs
        || $psaTabs->isCourseTester()
        || $psaTabs->canViewAnyCourseContent($cidTabs)
    );
    $canViewLearnersTabs = $psaTabs && $cidTabs > 0 && $psaTabs->canViewCourseLearnersTab($cidTabs);
    $canSettingsTabs = $cidTabs > 0 && $psaTabs && (
        $psaTabs->canEditCourseMeta($cidTabs)
        || $psaTabs->canManageCollaborators($cidTabs)
        || $psaTabs->canAccessCourseModulesTab($cidTabs)
    );
    $canCertsTabs = $cidTabs > 0 && $psaTabs && $psaTabs->canEditCourseMeta($cidTabs);
    $tpTabs = $tabsCourse ? ['adminCourse' => $tabsCourse->slug] : ($ap ?? []);
    $tabKey = $adminCourseTab ?? null;
    $showSurveysTab = $cidTabs > 0 && (
        ! empty($adminCourseHasSurveys)
        || app(\App\Services\CourseSurveyCatalogService::class)->hasSurveys($cidTabs)
    );
@endphp
@if ($tabKey !== null && $tpTabs !== [])
    <nav class="ap-course-tabs" aria-label="Разделы курса">
        @if ($canSettingsTabs)
            <a class="ap-course-tabs__a @if($tabKey === 'course_modules') ap-course-tabs__a--active @endif"
               href="{{ route('admin.course.settings', $tpTabs) }}">Модули</a>
        @endif
        @if ($canViewContentTabs)
            <a class="ap-course-tabs__a @if($tabKey === 'course_content') ap-course-tabs__a--active @endif"
               href="{{ route('admin.theory.index', $tpTabs) }}">Содержимое</a>
            <a class="ap-course-tabs__a @if($tabKey === 'course_quiz') ap-course-tabs__a--active @endif"
               href="{{ route('admin.quiz.index', $tpTabs) }}">Тесты</a>
        @endif
        @if ($canViewLearnersTabs)
            <a class="ap-course-tabs__a @if($tabKey === 'learners') ap-course-tabs__a--active @endif"
               href="{{ route('admin.learners.course', $tpTabs) }}">Обучающиеся</a>
        @endif
        @if ($showSurveysTab && $canViewLearnersTabs)
            <a class="ap-course-tabs__a @if($tabKey === 'surveys') ap-course-tabs__a--active @endif"
               href="{{ route('admin.course.surveys', $tpTabs) }}">Опросы</a>
        @endif
        @if ($canCertsTabs)
            <a class="ap-course-tabs__a @if($tabKey === 'certificates') ap-course-tabs__a--active @endif"
               href="{{ route('admin.certificates', $tpTabs) }}">Сертификаты</a>
        @endif
    </nav>
@endif
