@php
    /** @var \App\Models\Course|null $adminCurrentCourse */
    /** @var string|null $adminCourseTab */
    $tabsCourse = $adminCurrentCourse ?? ($courseModel ?? null);
    $psaTabs = $portalStaffAccess ?? null;
    $canToolsTabs = $psaTabs && $psaTabs->canUseCourseAdminTools();
    $cidTabs = $tabsCourse ? (int) $tabsCourse->id : 0;
    $canViewLearnersTabs = $psaTabs && $cidTabs > 0 && $psaTabs->canViewCourseLearnerStats($cidTabs);
    $tpTabs = $tabsCourse ? ['adminCourse' => $tabsCourse->slug] : ($ap ?? []);
    $tabKey = $adminCourseTab ?? null;
    $showSurveysTab = $cidTabs > 0 && (
        ! empty($adminCourseHasSurveys)
        || app(\App\Services\CourseSurveyCatalogService::class)->hasSurveys($cidTabs)
    );
@endphp
@if ($tabKey !== null && $tpTabs !== [])
    <nav class="ap-course-tabs" aria-label="Разделы курса">
        @if ($canToolsTabs)
            <a class="ap-course-tabs__a @if($tabKey === 'course_modules') ap-course-tabs__a--active @endif"
               href="{{ route('admin.course.settings', $tpTabs) }}">Модули</a>
        @endif
        @if ($canToolsTabs || ($psaTabs && $psaTabs->isCourseTester()))
            <a class="ap-course-tabs__a @if($tabKey === 'course_content') ap-course-tabs__a--active @endif"
               href="{{ route('admin.theory.index', $tpTabs) }}">Содержимое</a>
        @endif
        @if ($canToolsTabs || $canViewLearnersTabs)
            <a class="ap-course-tabs__a @if($tabKey === 'learners') ap-course-tabs__a--active @endif"
               href="{{ route('admin.learners.course', $tpTabs) }}">Обучающиеся</a>
        @endif
        @if ($showSurveysTab && ($canToolsTabs || $canViewLearnersTabs))
            <a class="ap-course-tabs__a @if($tabKey === 'surveys') ap-course-tabs__a--active @endif"
               href="{{ route('admin.course.surveys', $tpTabs) }}">Опросы</a>
        @endif
        @if ($canToolsTabs)
            <a class="ap-course-tabs__a @if($tabKey === 'certificates') ap-course-tabs__a--active @endif"
               href="{{ route('admin.certificates', $tpTabs) }}">Сертификаты</a>
        @endif
    </nav>
@endif
