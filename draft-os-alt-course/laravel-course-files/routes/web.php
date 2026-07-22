<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AdminQuizController;
use App\Http\Controllers\AdminTheoryController;
use App\Http\Controllers\AdminCoursesController;
use App\Http\Controllers\AdminCourseSettingsController;
use App\Http\Controllers\AdminSurveyResponsesController;
use App\Http\Controllers\AdminLearnersController;
use App\Http\Controllers\AdminPracticeImagesController;
use App\Http\Controllers\AdminDockerLibraryController;
use App\Http\Controllers\AdminMediaLibraryController;
use App\Http\Controllers\AdminCourseContentController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminStaffGroupController;
use App\Http\Controllers\AdminIncidentLogsController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\EmailLoginController;
use App\Http\Controllers\FinalLabController;
use App\Http\Controllers\MediaServeController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OidcLoginController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PracticeLabController;
use App\Http\Controllers\TeacherCourseReportController;
use App\Models\Course;
use App\Services\PortalStaffAccess;
use App\Support\CourseStaffPreview;
use App\Support\PortalIncidentBootstrap;
use App\Support\StaffAdminPreview;
use App\Support\StaffImpersonation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

PortalIncidentBootstrap::register();

// Дочерние @section рендерятся до layout: переменная нужна и на `admin.*`, `portal.*`, не только на layouts.course.
View::composer(['layouts.course', 'admin.*', 'portal.*', 'docs.*', 'layouts.admin', 'layouts.admin-preview', 'teacher-course-report', 'teacher-learner-profile', 'teacher-learner-module'], function ($view) {
    if (StaffImpersonation::isPreviewRequest(request())) {
        $view->with('portalStaffAccess', null);
        $view->with('learnerPreviewActive', true);
        $view->with('courseStaffPreviewActive', false);

        return;
    }
    if (CourseStaffPreview::isPreviewRequest(request())) {
        $id = (int) session('learner_id', 0);
        $access = $id > 0 ? PortalStaffAccess::fromLearnerId($id) : null;
        $view->with('portalStaffAccess', $access);
        $view->with('learnerPreviewActive', false);
        $view->with('courseStaffPreviewActive', true);

        return;
    }
    if (StaffAdminPreview::isPreviewRequest(request()) && app()->bound(PortalStaffAccess::class)) {
        $view->with('portalStaffAccess', app(PortalStaffAccess::class));
        $view->with('learnerPreviewActive', false);
        $view->with('courseStaffPreviewActive', false);

        return;
    }
    $id = (int) session('learner_id', 0);
    $access = $id > 0 ? PortalStaffAccess::fromLearnerId($id) : null;
    $view->with('portalStaffAccess', $access);
    $view->with('learnerPreviewActive', false);
    $view->with('courseStaffPreviewActive', false);
});

View::composer('layouts.admin', function ($view) {
    $course = \App\Support\AdminNavigation::currentCourse();
    $hasSurveys = $course
        ? app(\App\Services\CourseSurveyCatalogService::class)->hasSurveys((int) $course->id)
        : false;
    $view->with('adminBreadcrumbs', \App\Support\AdminNavigation::breadcrumbs());
    $view->with('adminSidebarActive', \App\Support\AdminNavigation::sidebarActive());
    $view->with('adminCourseTab', \App\Support\AdminNavigation::courseTab());
    $view->with('adminShowCourseChrome', \App\Support\AdminNavigation::showCourseChrome());
    $view->with('adminCurrentCourse', $course);
    $view->with('adminCourseHasSurveys', $hasSurveys);
});

View::composer('layouts.course', function ($view) {
    if (! request()->routeIs('admin.*')) {
        return;
    }
    $view->with('adminBreadcrumbs', \App\Support\AdminNavigation::breadcrumbs());
});

View::composer('admin.*', function ($view) {
    $view->with('ap', \App\Support\AdminNavigation::adminCourseRouteParams());
});

View::composer('teacher-course-report', function ($view) {
    $view->with('ap', \App\Support\AdminNavigation::adminCourseRouteParams());
});

Route::bind('adminCourse', function (string $value) {
    return Course::query()->where('slug', $value)->firstOrFail();
});

Route::get('/login', [EmailLoginController::class, 'show'])->name('login');
Route::post('/login', [EmailLoginController::class, 'store'])->name('login.store');
Route::post('/logout', [EmailLoginController::class, 'logout'])->name('logout');

// OIDC (SSO) login is optional and does not replace the default email login.
Route::get('/oidc/login', [OidcLoginController::class, 'redirect'])->name('oidc.login');
Route::get('/oidc/callback', [OidcLoginController::class, 'callback'])->name('oidc.callback');

Route::middleware([
    \App\Http\Middleware\ApplyLearnerPreview::class,
    \App\Http\Middleware\ApplyCourseStaffPreview::class,
    \App\Http\Middleware\MaintenanceForUsers::class,
    \App\Http\Middleware\LogPortalIncidents::class,
])->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('portal');
    Route::get('/portal/prosmotr/zavershit', function () {
        return redirect()
            ->route('admin.settings')
            ->with('ok', 'Просмотр портала от лица обучающегося завершён.');
    })->name('portal.learner-preview.end');
    Route::get('/portal/predprosmotr/zavershit', [\App\Http\Controllers\CourseStaffPreviewController::class, 'end'])
        ->name('portal.course-preview.end');
    Route::post('/portal/enroll/{course}', [\App\Http\Controllers\PortalEnrollController::class, 'store'])
        ->whereNumber('course')
        ->name('portal.enroll');

    Route::middleware([\App\Http\Middleware\EnsureLearner::class])->group(function () {
        Route::get('/opros/{token}', [\App\Http\Controllers\SurveyQuickLinkController::class, 'show'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('survey.quick');
        Route::post('/opros/{token}', [\App\Http\Controllers\SurveyQuickLinkController::class, 'submit'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('survey.quick.submit');
        Route::get('/s/{token}', [\App\Http\Controllers\ShareLinkController::class, 'show'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('share.quick');
        Route::post('/portal/incident', [AdminIncidentLogsController::class, 'storeClient'])->name('portal.incident.store');
        Route::get('/account', AccountController::class)->name('account');
        Route::get('/docs', [DocumentationController::class, 'index'])->name('documentation.index');
        Route::get('/docs/{slug}', [DocumentationController::class, 'show'])
            ->where('slug', '[a-z0-9\-\/]+')
            ->name('documentation.show');
        Route::get('/media/{uuid}', [MediaServeController::class, 'show'])
            ->where('uuid', '[0-9a-fA-F-]{36}')
            ->name('media.show');
        Route::get('/media/{uuid}/thumb', [MediaServeController::class, 'thumb'])
            ->where('uuid', '[0-9a-fA-F-]{36}')
            ->name('media.thumb');
    });

    Route::middleware([
        \App\Http\Middleware\EnsureLearner::class,
        \App\Http\Middleware\EnsureCourseSelected::class,
        \App\Http\Middleware\EnsureLearnerCertificateCourse::class,
    ])->group(function () {
        Route::get('/certificate', CertificateController::class)->name('certificate');
        Route::post('/certificate/recipient', [CertificateController::class, 'saveRecipient'])->name('certificate.recipient');
    });

    Route::middleware([
        \App\Http\Middleware\EnsureLearner::class,
        \App\Http\Middleware\EnsureCourseSelected::class,
        \App\Http\Middleware\EnsureLearnerCourseActive::class,
    ])->group(function () {
    // Dashboard is course-scoped; keep legacy /dashboard as redirect.
    Route::get('/dashboard', function () {
        return redirect()->route('course.dashboard', ['course' => \App\Support\LearnerPreviewContext::courseId()]);
    })->name('dashboard');

    Route::get('/courses/{course}/dashboard', DashboardController::class)
        ->whereNumber('course')
        ->name('course.dashboard');

    Route::get('/assessment', AssessmentController::class)->name('assessment');
    Route::get('/final-lab', [FinalLabController::class, 'show'])->name('final-lab');
    Route::post('/final-lab/start', [FinalLabController::class, 'startLab'])->name('final-lab.start');
    Route::post('/final-lab/check', [FinalLabController::class, 'checkLab'])->name('final-lab.check');
    Route::post('/final-lab/accept', [FinalLabController::class, 'acceptLab'])->name('final-lab.accept');
    Route::post('/final-lab/finish', [FinalLabController::class, 'finishLab'])->name('final-lab.finish');

    $legacyModuleRedirect = static function (string $canonicalRoute, array $extra = []): \Closure {
        return static function (int $module) use ($canonicalRoute, $extra): \Illuminate\Http\RedirectResponse {
            $courseId = \App\Support\LearnerPreviewContext::courseId();
            $cm = app(\App\Services\CourseModuleService::class)->findOrFailForCourseRoute($courseId, $module);
            $seq = app(\App\Services\CourseModuleService::class)->sequenceForModule($cm);
            $params = array_merge(\App\Support\LearnerRoute::hub($courseId, $seq), $extra);
            if (isset($params['section']) && $params['section'] > 0) {
                $sec = app(\App\Services\CourseSectionService::class)
                    ->findOrFailBySequenceForModuleRoute((int) $cm->id, (int) $params['section']);
                $params['section'] = app(\App\Services\CourseSectionService::class)->sequenceForSection($sec);
            }

            return redirect()->route($canonicalRoute, $params, 301);
        };
    };

    Route::prefix('courses/{course}/module/{module}')
        ->whereNumber(['course', 'module'])
        ->group(function () {
            Route::get('/', [ModuleController::class, 'hub'])->name('course.module.hub');
            Route::post('/briefing', [ModuleController::class, 'ackHubBriefing'])->name('course.module.hub.ack');
            Route::post('/difficulties', [ModuleController::class, 'saveDifficulties'])->name('course.module.difficulties');

            Route::get('/theory', [ModuleController::class, 'theoryLegacyRedirect'])->name('course.module.theory');
            Route::post('/theory/read', [ModuleController::class, 'markTheoryRead'])->name('course.module.theory.read');

            Route::get('/theory-quiz', [ModuleController::class, 'theoryQuizLegacyRedirect'])->name('course.module.theory-quiz');
            Route::post('/theory-quiz/start', [ModuleController::class, 'theoryQuizStart'])->name('course.module.theory-quiz.start');
            Route::post('/theory-quiz/submit', [ModuleController::class, 'theoryQuizSubmit'])->name('course.module.theory-quiz.submit');
            Route::get('/theory-quiz/result', [ModuleController::class, 'theoryQuizLegacyResultRedirect'])->name('course.module.theory-quiz.result');

            Route::get('/practice', [ModuleController::class, 'practiceShow'])->name('course.module.practice');
            Route::post('/practice/done', [ModuleController::class, 'practiceDone'])->name('course.module.practice.done');

            Route::post('/practice/lab/start', [PracticeLabController::class, 'start'])->name('course.module.practice.lab.start');
            Route::post('/practice/lab/check', [PracticeLabController::class, 'check'])->name('course.module.practice.lab.check');
            Route::post('/practice/lab/accept', [PracticeLabController::class, 'accept'])->name('course.module.practice.lab.accept');
            Route::post('/practice/lab/finish', [PracticeLabController::class, 'finish'])->name('course.module.practice.lab.finish');

            Route::get('/exam', [ModuleController::class, 'examLegacyRedirect'])->name('course.module.exam');
            Route::post('/exam/start', [ModuleController::class, 'examStart'])->name('course.module.exam.start');
            Route::post('/exam/submit', [ModuleController::class, 'examSubmit'])->name('course.module.exam.submit');
            Route::get('/exam/result', [ModuleController::class, 'examLegacyResultRedirect'])->name('course.module.exam.result');

            Route::prefix('section/{section}')->whereNumber('section')->group(function () {
                Route::get('/theory', [ModuleController::class, 'theory'])->name('course.module.section.theory');
                Route::post('/theory/read', [ModuleController::class, 'markTheoryRead'])->name('course.module.section.theory.read');

                Route::get('/theory-quiz', [ModuleController::class, 'theoryQuizShow'])->name('course.module.section.theory-quiz');
                Route::post('/theory-quiz/start', [ModuleController::class, 'theoryQuizStart'])->name('course.module.section.theory-quiz.start');
                Route::post('/theory-quiz/submit', [ModuleController::class, 'theoryQuizSubmit'])->name('course.module.section.theory-quiz.submit');
                Route::get('/theory-quiz/result', [ModuleController::class, 'theoryQuizResult'])->name('course.module.section.theory-quiz.result');

                Route::get('/exam', [ModuleController::class, 'examShow'])->name('course.module.section.exam');
                Route::post('/exam/start', [ModuleController::class, 'examStart'])->name('course.module.section.exam.start');
                Route::post('/exam/submit', [ModuleController::class, 'examSubmit'])->name('course.module.section.exam.submit');
                Route::get('/exam/result', [ModuleController::class, 'examResult'])->name('course.module.section.exam.result');

                Route::get('/survey', [\App\Http\Controllers\SurveyController::class, 'show'])->name('course.module.section.survey');
                Route::post('/survey', [\App\Http\Controllers\SurveyController::class, 'submit'])->name('course.module.section.survey.submit');
            });
        });

    Route::prefix('module/{module}')->whereNumber('module')->group(function () use ($legacyModuleRedirect) {
        Route::get('/', $legacyModuleRedirect('course.module.hub'))->name('modules.hub');
        Route::post('/briefing', [ModuleController::class, 'ackHubBriefing'])->name('modules.hub.ack');
        Route::post('/difficulties', [ModuleController::class, 'saveDifficulties'])->name('modules.difficulties');

        Route::get('/theory', $legacyModuleRedirect('course.module.theory'))->name('modules.theory');
        Route::post('/theory/read', [ModuleController::class, 'markTheoryRead'])->name('modules.theory.read');

        Route::get('/theory-quiz', $legacyModuleRedirect('course.module.theory-quiz'))->name('modules.theory-quiz');
        Route::post('/theory-quiz/start', [ModuleController::class, 'theoryQuizStart'])->name('modules.theory-quiz.start');
        Route::post('/theory-quiz/submit', [ModuleController::class, 'theoryQuizSubmit'])->name('modules.theory-quiz.submit');
        Route::get('/theory-quiz/result', $legacyModuleRedirect('course.module.theory-quiz.result'))->name('modules.theory-quiz.result');

        Route::get('/practice', $legacyModuleRedirect('course.module.practice'))->name('modules.practice');
        Route::post('/practice/done', [ModuleController::class, 'practiceDone'])->name('modules.practice.done');

        Route::post('/practice/lab/start', [PracticeLabController::class, 'start'])->name('modules.practice.lab.start');
        Route::post('/practice/lab/check', [PracticeLabController::class, 'check'])->name('modules.practice.lab.check');
        Route::post('/practice/lab/accept', [PracticeLabController::class, 'accept'])->name('modules.practice.lab.accept');
        Route::post('/practice/lab/finish', [PracticeLabController::class, 'finish'])->name('modules.practice.lab.finish');

        Route::get('/exam', $legacyModuleRedirect('course.module.exam'))->name('modules.exam');
        Route::post('/exam/start', [ModuleController::class, 'examStart'])->name('modules.exam.start');
        Route::post('/exam/submit', [ModuleController::class, 'examSubmit'])->name('modules.exam.submit');
        Route::get('/exam/result', $legacyModuleRedirect('course.module.exam.result'))->name('modules.exam.result');

        Route::prefix('section/{section}')->whereNumber('section')->group(function () {
            $legacySectionRedirect = static function (string $canonicalRoute) {
                return static function (int $module, int $section) use ($canonicalRoute): \Illuminate\Http\RedirectResponse {
                    $courseId = \App\Support\LearnerPreviewContext::courseId();
                    $cm = app(\App\Services\CourseModuleService::class)->findOrFailForCourseRoute($courseId, $module);
                    $modSeq = app(\App\Services\CourseModuleService::class)->sequenceForModule($cm);
                    $sec = app(\App\Services\CourseSectionService::class)
                        ->findOrFailBySequenceForModuleRoute((int) $cm->id, $section);
                    $secSeq = app(\App\Services\CourseSectionService::class)->sequenceForSection($sec);

                    return redirect()->route(
                        $canonicalRoute,
                        \App\Support\LearnerRoute::section($courseId, $modSeq, $secSeq),
                        301
                    );
                };
            };

            Route::get('/theory', $legacySectionRedirect('course.module.section.theory'))->name('modules.section.theory');
            Route::post('/theory/read', [ModuleController::class, 'markTheoryRead'])->name('modules.section.theory.read');

            Route::get('/theory-quiz', $legacySectionRedirect('course.module.section.theory-quiz'))->name('modules.section.theory-quiz');
            Route::post('/theory-quiz/start', [ModuleController::class, 'theoryQuizStart'])->name('modules.section.theory-quiz.start');
            Route::post('/theory-quiz/submit', [ModuleController::class, 'theoryQuizSubmit'])->name('modules.section.theory-quiz.submit');
            Route::get('/theory-quiz/result', $legacySectionRedirect('course.module.section.theory-quiz.result'))->name('modules.section.theory-quiz.result');

            Route::get('/exam', $legacySectionRedirect('course.module.section.exam'))->name('modules.section.exam');
            Route::post('/exam/start', [ModuleController::class, 'examStart'])->name('modules.section.exam.start');
            Route::post('/exam/submit', [ModuleController::class, 'examSubmit'])->name('modules.section.exam.submit');
            Route::get('/exam/result', $legacySectionRedirect('course.module.section.exam.result'))->name('modules.section.exam.result');

            Route::get('/survey', static function (int $module, int $section): \Illuminate\Http\RedirectResponse {
                $courseId = \App\Support\LearnerPreviewContext::courseId();
                $cm = app(\App\Services\CourseModuleService::class)->findOrFailForCourseRoute($courseId, $module);
                $modSeq = app(\App\Services\CourseModuleService::class)->sequenceForModule($cm);
                $sec = app(\App\Services\CourseSectionService::class)
                    ->findOrFailBySequenceForModuleRoute((int) $cm->id, $section);
                $secSeq = app(\App\Services\CourseSectionService::class)->sequenceForSection($sec);

                return redirect()->route(
                    'course.module.section.survey',
                    \App\Support\LearnerRoute::section($courseId, $modSeq, $secSeq),
                    301
                );
            })->name('modules.section.survey');
            Route::post('/survey', [\App\Http\Controllers\SurveyController::class, 'submit'])->name('modules.section.survey.submit');
        });
    });
    });
});

Route::middleware([
    \App\Http\Middleware\EnsureLearner::class,
    \App\Http\Middleware\EnsurePortalStaff::class,
    \App\Http\Middleware\ApplyStaffAdminPreview::class,
    \App\Http\Middleware\DenyStaffAdminPreviewWrites::class,
    \App\Http\Middleware\LogAdminActivity::class,
])->group(function () {
    Route::get('/adm', [AdminPanelController::class, 'show'])->name('admin.panel');

    Route::middleware([\App\Http\Middleware\EnsurePortalAdmin::class])->group(function () {
        Route::get('/adm/logi', [AdminIncidentLogsController::class, 'index'])->name('admin.incidents.index');
        Route::get('/adm/logi/lenta', [AdminIncidentLogsController::class, 'feed'])->name('admin.incidents.feed');
        Route::get('/adm/logi/{incident}', [AdminIncidentLogsController::class, 'show'])
            ->whereNumber('incident')
            ->name('admin.incidents.show');

        Route::get('/adm/pochta', [\App\Http\Controllers\AdminMailLogsController::class, 'index'])->name('admin.mail.index');
        Route::get('/adm/pochta/shablony', [\App\Http\Controllers\AdminMailLogsController::class, 'templates'])->name('admin.mail.templates');
        Route::get('/adm/pochta/lenta', [\App\Http\Controllers\AdminMailLogsController::class, 'feed'])->name('admin.mail.feed');
        Route::get('/adm/pochta/{mailLog}', [\App\Http\Controllers\AdminMailLogsController::class, 'show'])
            ->whereNumber('mailLog')
            ->name('admin.mail.show');
        Route::post('/adm/pochta/{mailLog}/resend', [\App\Http\Controllers\AdminMailLogsController::class, 'resend'])
            ->whereNumber('mailLog')
            ->name('admin.mail.resend');
    });

    Route::get('/adm/sobytiya', [AdminPanelController::class, 'activity'])->name('admin.activity');
    Route::get('/adm/sobytiya/lenta', [AdminPanelController::class, 'activityFeed'])->name('admin.activity.feed');
    Route::get('/adm/paleta/poisk', [AdminPanelController::class, 'commandPaletteSearch'])->name('admin.command-palette.search');

    Route::get('/adm/nastroiki', [AdminSettingsController::class, 'show'])->name('admin.settings');
    Route::post('/adm/nastroiki/zaglushka', [AdminSettingsController::class, 'updateMaintenance'])->name('admin.settings.maintenance');
    Route::post('/adm/nastroiki/zaglushka/sbros', [AdminSettingsController::class, 'resetMaintenance'])->name('admin.settings.maintenance.reset');
    Route::post('/adm/nastroiki/prosmotr', [AdminSettingsController::class, 'impersonate'])->name('admin.settings.impersonate');
    Route::get('/adm/nastroiki/poisk-obuchayushchihsya', [AdminSettingsController::class, 'learnerSearch'])->name('admin.settings.learner-search');
    Route::post('/adm/nastroiki/prosmotr-sotrudnika', [AdminSettingsController::class, 'staffPreview'])->name('admin.settings.staff-preview');
    Route::get('/adm/nastroiki/prosmotr-sotrudnika/zavershit', [AdminSettingsController::class, 'endStaffPreview'])->name('admin.settings.staff-preview.end');
    Route::get('/adm/nastroiki/poisk-sotrudnikov', [AdminSettingsController::class, 'staffSearch'])->name('admin.settings.staff-search');

    Route::get('/adm/media', [AdminMediaLibraryController::class, 'index'])->name('admin.media.library');
    Route::get('/adm/media/api', [AdminMediaLibraryController::class, 'apiList'])->name('admin.media.api');
    Route::post('/adm/media/upload', [AdminMediaLibraryController::class, 'upload'])->name('admin.media.upload');
    Route::get('/adm/media/{uuid}/thumb', [MediaServeController::class, 'thumb'])
        ->where('uuid', '[0-9a-fA-F-]{36}')
        ->name('admin.media.thumb');
    Route::get('/adm/media/{uuid}/file', [MediaServeController::class, 'show'])
        ->where('uuid', '[0-9a-fA-F-]{36}')
        ->name('admin.media.file');
    Route::post('/adm/media/{uuid}/pin', [AdminMediaLibraryController::class, 'pin'])
        ->where('uuid', '[0-9a-fA-F-]{36}')
        ->name('admin.media.pin');
    Route::delete('/adm/media/{uuid}', [AdminMediaLibraryController::class, 'destroy'])
        ->where('uuid', '[0-9a-fA-F-]{36}')
        ->name('admin.media.destroy');

    Route::middleware([\App\Http\Middleware\DenyCourseTester::class])->group(function () {
        Route::get('/adm/docker', [AdminDockerLibraryController::class, 'index'])->name('admin.docker.library');
        Route::get('/adm/docker/create', [AdminPracticeImagesController::class, 'create'])->name('admin.docker.library.create');
        Route::post('/adm/docker', [AdminPracticeImagesController::class, 'store'])->name('admin.docker.library.store');
        Route::post('/adm/docker/clone', [AdminPracticeImagesController::class, 'cloneFrom'])->name('admin.docker.library.clone');
        Route::post('/adm/docker/recipe-preview', [AdminPracticeImagesController::class, 'recipePreview'])->name('admin.docker.library.recipe.preview');
        Route::post('/adm/docker/stats/refresh', [AdminDockerLibraryController::class, 'refreshStats'])->name('admin.docker.library.stats.refresh');
        Route::get('/adm/docker/pkg-search', [AdminPracticeImagesController::class, 'pkgSearch'])->name('admin.docker.library.pkg.search');
        Route::get('/adm/docker/{id}', [AdminPracticeImagesController::class, 'edit'])->whereNumber('id')->name('admin.docker.library.edit');
        Route::post('/adm/docker/{id}/reimport-template', [AdminPracticeImagesController::class, 'reimportTemplate'])->whereNumber('id')->name('admin.docker.library.reimport');
        Route::post('/adm/docker/{id}', [AdminPracticeImagesController::class, 'update'])->whereNumber('id')->name('admin.docker.library.update');
        Route::post('/adm/docker/{id}/build', [AdminDockerLibraryController::class, 'build'])->whereNumber('id')->name('admin.docker.library.build');
        Route::get('/adm/docker/{id}/sandbox/status', [AdminDockerLibraryController::class, 'sandboxStatus'])->whereNumber('id')->name('admin.docker.library.sandbox.status');
        Route::post('/adm/docker/{id}/sandbox/start', [AdminDockerLibraryController::class, 'sandboxStart'])->whereNumber('id')->name('admin.docker.library.sandbox.start');
        Route::post('/adm/docker/{id}/sandbox/check', [AdminDockerLibraryController::class, 'sandboxCheck'])->whereNumber('id')->name('admin.docker.library.sandbox.check');
        Route::post('/adm/docker/{id}/sandbox/stop', [AdminDockerLibraryController::class, 'sandboxStop'])->whereNumber('id')->name('admin.docker.library.sandbox.stop');
        Route::post('/adm/docker/{id}/export', [AdminPracticeImagesController::class, 'export'])->whereNumber('id')->name('admin.docker.library.export');
        Route::post('/adm/docker/{id}/udalit', [AdminDockerLibraryController::class, 'destroy'])->whereNumber('id')->name('admin.docker.library.destroy');
    });

    Route::middleware([\App\Http\Middleware\EnsureStaffAbility::class.':manage_staff'])->group(function () {
        Route::get('/adm/sotrudniki', [AdminStaffController::class, 'index'])->name('admin.staff.index');
        Route::get('/adm/sotrudniki/sozdat', [AdminStaffController::class, 'create'])->name('admin.staff.create');
        Route::post('/adm/sotrudniki', [AdminStaffController::class, 'store'])->name('admin.staff.store');
        Route::get('/adm/sotrudniki/{staff}', [AdminStaffController::class, 'show'])
            ->whereNumber('staff')
            ->name('admin.staff.show');
        Route::get('/adm/sotrudniki/{staff}/redaktirovat', [AdminStaffController::class, 'edit'])
            ->whereNumber('staff')
            ->name('admin.staff.edit');
        Route::post('/adm/sotrudniki/{staff}', [AdminStaffController::class, 'update'])
            ->whereNumber('staff')
            ->name('admin.staff.update');
        Route::post('/adm/sotrudniki/{staff}/udalit', [AdminStaffController::class, 'destroy'])
            ->whereNumber('staff')
            ->name('admin.staff.destroy');
        Route::post('/adm/sotrudniki/gruppy', [AdminStaffGroupController::class, 'store'])->name('admin.staff.groups.store');
        Route::post('/adm/sotrudniki/gruppy/{group}', [AdminStaffGroupController::class, 'update'])
            ->whereNumber('group')
            ->name('admin.staff.groups.update');
        Route::post('/adm/sotrudniki/gruppy/{group}/udalit', [AdminStaffGroupController::class, 'destroy'])
            ->whereNumber('group')
            ->name('admin.staff.groups.destroy');
    });

    Route::get('/adm/kursy', [AdminCoursesController::class, 'index'])->name('admin.courses.index');
    Route::get('/adm/kursy/stats', [AdminCoursesController::class, 'catalogStats'])->name('admin.courses.catalog-stats');
    Route::middleware([\App\Http\Middleware\EnsureStaffAbility::class.':course_catalog_create'])->group(function () {
        Route::get('/adm/kursy/sozdat', [AdminCoursesController::class, 'create'])->name('admin.courses.create');
        Route::post('/adm/kursy', [AdminCoursesController::class, 'store'])->name('admin.courses.store');
    });
    Route::get('/adm/kursy/{course}/redaktirovat', [AdminCoursesController::class, 'edit'])
        ->whereNumber('course')
        ->name('admin.courses.edit');
    Route::post('/adm/kursy/{course}', [AdminCoursesController::class, 'update'])
        ->whereNumber('course')
        ->name('admin.courses.update');
    Route::post('/adm/kursy/{course}/archive', [AdminCoursesController::class, 'archive'])
        ->whereNumber('course')
        ->name('admin.courses.archive');
    Route::post('/adm/kursy/{course}/unarchive', [AdminCoursesController::class, 'unarchive'])
        ->whereNumber('course')
        ->name('admin.courses.unarchive');
    Route::post('/adm/kursy/{course}/publish', [AdminCoursesController::class, 'publish'])
        ->whereNumber('course')
        ->name('admin.courses.publish');
    Route::post('/adm/kursy/{course}/select', [AdminCoursesController::class, 'select'])
        ->whereNumber('course')
        ->name('admin.courses.select');
    Route::get('/adm/kursy/{course}/enter', [AdminCoursesController::class, 'enter'])
        ->whereNumber('course')
        ->name('admin.courses.enter');

    /** Совместимость: старые ссылки «/adm/kurs/{slug}/enter» вместо «/adm/kursy/{id}/enter». */
    Route::get('/adm/kurs/{adminCourse:slug}/enter', function (Course $adminCourse) {
        app(PortalStaffAccess::class)->assertCanAccessCourseInAdmin((int) $adminCourse->id);

        return redirect()->route('admin.courses.enter', ['course' => $adminCourse->id]);
    })->name('admin.courses.enter.slug-legacy');

    Route::middleware([\App\Http\Middleware\EnsureStaffAbility::class.':view_portal_learners'])->group(function () {
        Route::get('/adm/lyudi', [AdminLearnersController::class, 'indexPeople'])->name('admin.learners.portal');
        Route::get('/adm/lyudi/gruppy', [\App\Http\Controllers\AdminLearnerGroupsController::class, 'portalIndex'])->name('admin.learner-groups.portal');
        Route::post('/adm/lyudi/gruppy', [\App\Http\Controllers\AdminLearnerGroupsController::class, 'portalStore'])->name('admin.learner-groups.portal.store');
        Route::get('/adm/lyudi/gruppy/search', [\App\Http\Controllers\AdminLearnerGroupsController::class, 'portalSearchLearners'])
            ->name('admin.learner-groups.portal.search');
        Route::post('/adm/lyudi/gruppy/{group}', [\App\Http\Controllers\AdminLearnerGroupsController::class, 'portalUpdate'])
            ->whereNumber('group')
            ->name('admin.learner-groups.portal.update');
        Route::post('/adm/lyudi/gruppy/{group}/udalit', [\App\Http\Controllers\AdminLearnerGroupsController::class, 'portalDestroy'])
            ->whereNumber('group')
            ->name('admin.learner-groups.portal.destroy');
        Route::get('/adm/lyudi/{learner}', [AdminLearnersController::class, 'peopleShowJson'])
            ->whereNumber('learner')
            ->name('admin.learners.people.detail');
    });
    Route::get('/adm/obuchayushiesya', static fn () => redirect('/adm/lyudi', 301));

    Route::middleware([\App\Http\Middleware\DenyCourseTester::class])->group(function () {
        Route::get('/instruktor/kurs-progress', [TeacherCourseReportController::class, 'index'])->name('teacher.course-report');
        Route::get('/instruktor/kurs-progress/learner/{learner}', [TeacherCourseReportController::class, 'learner'])
            ->whereNumber('learner')
            ->name('teacher.course-report.learner');
        Route::get('/instruktor/kurs-progress/learner/{learner}/modul/{module}', [TeacherCourseReportController::class, 'moduleShow'])
            ->whereNumber('learner')
            ->whereNumber('module')
            ->name('teacher.course-report.learner.module');
        Route::post('/instruktor/kurs-progress/learner/{learner}/modul/{module}/sbros', [TeacherCourseReportController::class, 'resetAttempt'])
            ->whereNumber('learner')
            ->whereNumber('module')
            ->name('teacher.course-report.learner.module.reset');
    });

    Route::middleware([\App\Http\Middleware\EnsureAdminCourseSelected::class])->group(function () {
        $slugOrCourses = function (): ?string {
            $ac = \App\Support\AdminNavigation::adminCourseRouteParams();

            return $ac['adminCourse'] ?? null;
        };
        Route::get('/adm/kurs-teoriya', function () use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.theory.index', ['adminCourse' => $s], 302);
        });
        Route::get('/adm/kurs-teoriya/vse-md.zip', function () use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.theory.zip', ['adminCourse' => $s], 302);
        });
        Route::get('/adm/kurs-teoriya/modul/{module}/teoriya', function (int $module) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.theory.preview-theory', ['adminCourse' => $s, 'module' => $module], 302);
        })->whereNumber('module');
        Route::get('/adm/kurs-teoriya/modul/{module}/test-teorii', function (int $module) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.theory.preview-theory-quiz', ['adminCourse' => $s, 'module' => $module], 302);
        })->whereNumber('module');
        Route::get('/adm/kurs-teoriya/modul/{module}/praktika', function (int $module) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.theory.preview-practice', ['adminCourse' => $s, 'module' => $module], 302);
        })->whereNumber('module');
        Route::get('/adm/kurs-teoriya/modul/{module}/ekzamen', function (int $module) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.theory.preview-module-exam', ['adminCourse' => $s, 'module' => $module], 302);
        })->whereNumber('module');
        Route::get('/adm/kurs-teoriya/final-lab', function () use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.theory.preview-final-lab', ['adminCourse' => $s], 302);
        });
        Route::get('/adm/voprosy', function () use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.theory.index', ['adminCourse' => $s], 302);
        });
        Route::get('/adm/voprosy/modul/{module}/{kind}', function (int $module, string $kind) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.quiz.edit.module', ['adminCourse' => $s, 'module' => $module, 'kind' => $kind], 302);
        })->whereNumber('module');
        Route::get('/adm/voprosy/final-lab', function () use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.quiz.edit.final', ['adminCourse' => $s], 302);
        });
        Route::get('/adm/kurs/nastroyki', function () use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.course.settings', ['adminCourse' => $s], 302);
        });
        Route::get('/adm/kurs/moduli', function () use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.course.settings', ['adminCourse' => $s], 302);
        });
        Route::get('/adm/praktika/obraza', static fn () => redirect()->route('admin.docker.library', [], 302));
        Route::get('/adm/praktika/obraza/create', static fn () => redirect()->route('admin.docker.library', ['create' => '1'], 302));
        Route::get('/adm/praktika/obraza/pkg-search', function () use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->to(route('admin.practice.images.pkg.search', ['adminCourse' => $s]).'?'.request()->getQueryString(), 302);
        });
        Route::get('/adm/praktika/obraza/{id}', function (int $id) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.docker.library.edit', ['id' => $id], 302);
        })->whereNumber('id');
        Route::get('/adm/kurs/nastroyki/modul/{courseModule}', function (int $courseModule) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.course.module.sections', ['adminCourse' => $s, 'courseModule' => $courseModule], 302);
        })->whereNumber('courseModule');
        Route::get('/adm/kurs/nastroyki/modul/{courseModule}/praktika', function (int $courseModule) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.course.module.practice', ['adminCourse' => $s, 'courseModule' => $courseModule], 302);
        })->whereNumber('courseModule');
        Route::get('/adm/kurs/nastroyki/modul/{courseModule}/razdel/{section}', function (int $courseModule, int $section) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.course.module.section.settings', ['adminCourse' => $s, 'courseModule' => $courseModule, 'section' => $section], 302);
        })->whereNumber('courseModule')->whereNumber('section');
        Route::get('/adm/kurs/obuchayushiesya', function () use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.learners.course', ['adminCourse' => $s], 302);
        });
        Route::get('/adm/sertifikaty', function () use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.certificates', ['adminCourse' => $s], 302);
        });
        Route::get('/adm/sertifikaty/{result}', function (\App\Models\FinalLabResult $result) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.certificates.show', ['adminCourse' => $s, 'result' => $result->id], 302);
        });
        Route::get('/adm/kurs-teoriya/modul/{module}', function (int $module) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.theory.edit', ['adminCourse' => $s, 'module' => $module], 302);
        })->whereNumber('module');
        Route::get('/adm/kurs/kontent/modul/{courseModule}', function (int $courseModule) use ($slugOrCourses) {
            $s = $slugOrCourses();
            if ($s === null) {
                return redirect()->route('admin.courses.index')->with('err', 'Сначала выберите курс.');
            }

            return redirect()->route('admin.course.module.content.edit', ['adminCourse' => $s, 'courseModule' => $courseModule], 302);
        })->whereNumber('courseModule');
    });

    Route::prefix('adm/kurs/{adminCourse:slug}')
        ->middleware([
            \App\Http\Middleware\SyncAdminCourseFromSlug::class,
            \App\Http\Middleware\EnsureAdminCourseSelected::class,
            \App\Http\Middleware\RestrictInstructorCourseAccess::class,
        ])
        ->group(function () {
            Route::get('/predprosmotr', [\App\Http\Controllers\CourseStaffPreviewController::class, 'startCourse'])
                ->name('admin.course.preview');
            Route::get('/predprosmotr/modul/{module}', [\App\Http\Controllers\CourseStaffPreviewController::class, 'startModule'])
                ->whereNumber('module')
                ->name('admin.course.preview.module');
            Route::get('/predprosmotr/modul/{module}/razdel/{section}', [\App\Http\Controllers\CourseStaffPreviewController::class, 'startSection'])
                ->whereNumber('module')
                ->whereNumber('section')
                ->name('admin.course.preview.section');

            Route::get('/soderzhimoe', [AdminTheoryController::class, 'index'])->name('admin.theory.index');
            Route::get('/soderzhimoe/vse-md.zip', [AdminTheoryController::class, 'downloadZip'])->name('admin.theory.zip');
            Route::get('/soderzhimoe/modul/{module}/teoriya', [AdminTheoryController::class, 'previewTheory'])
                ->whereNumber('module')
                ->name('admin.theory.preview-theory');
            Route::get('/soderzhimoe/modul/{module}/test-teorii', [AdminTheoryController::class, 'previewTheoryQuiz'])
                ->whereNumber('module')
                ->name('admin.theory.preview-theory-quiz');
            Route::get('/soderzhimoe/modul/{module}/praktika', [AdminTheoryController::class, 'previewPractice'])
                ->whereNumber('module')
                ->name('admin.theory.preview-practice');
            Route::get('/soderzhimoe/modul/{module}/ekzamen', [AdminTheoryController::class, 'previewModuleExam'])
                ->whereNumber('module')
                ->name('admin.theory.preview-module-exam');
            Route::get('/soderzhimoe/modul/{module}/razdel/{section}', [AdminTheoryController::class, 'previewSection'])
                ->whereNumber('module')
                ->whereNumber('section')
                ->name('admin.theory.preview-section');
            Route::get('/soderzhimoe/final-lab', [AdminTheoryController::class, 'previewFinalLab'])
                ->name('admin.theory.preview-final-lab');

            Route::get('/testy', function () {
                return redirect()->route('admin.theory.index', array_merge(
                    ['adminCourse' => request()->route('adminCourse')->slug],
                    request()->query('key') !== null && request()->query('key') !== '' ? ['key' => request()->query('key')] : []
                ), 302);
            })->name('admin.quiz.index');
            Route::get('/testy/modul/{module}/{kind}', [AdminQuizController::class, 'editModule'])
                ->whereNumber('module')
                ->name('admin.quiz.edit.module');
            Route::get('/testy/final-lab', [AdminQuizController::class, 'editFinal'])->name('admin.quiz.edit.final');

            Route::middleware([\App\Http\Middleware\DenyCourseTester::class])->group(function () {
                Route::get('/nastroyki', [AdminCourseSettingsController::class, 'courseSettings'])->name('admin.course.settings');
                Route::post('/nastroyki/save', [AdminCourseSettingsController::class, 'saveCourseSettings'])->name('admin.course.settings.save');
                Route::post('/nastroyki/soavtory/invite', [\App\Http\Controllers\AdminCourseCollaboratorsController::class, 'invite'])->name('admin.course.collaborators.invite');
                Route::post('/nastroyki/soavtory/{portalStaff}/remove', [\App\Http\Controllers\AdminCourseCollaboratorsController::class, 'remove'])->name('admin.course.collaborators.remove');
                Route::get('/nastroyki/soavtory/search', [\App\Http\Controllers\AdminCourseCollaboratorsController::class, 'searchStaff'])->name('admin.course.collaborators.search');
                Route::get('/moduli', function () {
                    return redirect()->route('admin.course.settings', array_merge(
                        ['adminCourse' => request()->route('adminCourse')->slug],
                        request()->query('key') !== null && request()->query('key') !== '' ? ['key' => request()->query('key')] : []
                    ), 301);
                })->name('admin.course.modules');
                Route::get('/praktiki/obraza', [AdminPracticeImagesController::class, 'index'])->name('admin.practice.images.index');
                Route::get('/praktiki/obraza/create', [AdminPracticeImagesController::class, 'create'])->name('admin.practice.images.create');
                Route::post('/praktiki/obraza', [AdminPracticeImagesController::class, 'store'])->name('admin.practice.images.store');
                Route::post('/praktiki/obraza/clone', [AdminPracticeImagesController::class, 'cloneFrom'])->name('admin.practice.images.clone');
                Route::post('/praktiki/obraza/recipe-preview', [AdminPracticeImagesController::class, 'recipePreview'])->name('admin.practice.images.recipe.preview');
                Route::post('/praktiki/obraza/system/copy', [AdminPracticeImagesController::class, 'copySystem'])->name('admin.practice.images.system.copy');
                Route::post('/praktiki/obraza/stats/refresh', [AdminPracticeImagesController::class, 'refreshStats'])->name('admin.practice.images.stats.refresh');
                Route::get('/praktiki/obraza/pkg-search', [AdminPracticeImagesController::class, 'pkgSearch'])->name('admin.practice.images.pkg.search');
                Route::get('/praktiki/obraza/{id}', [AdminPracticeImagesController::class, 'edit'])->whereNumber('id')->name('admin.practice.images.edit');
                Route::post('/praktiki/obraza/{id}', [AdminPracticeImagesController::class, 'update'])->whereNumber('id')->name('admin.practice.images.update');
                Route::post('/praktiki/obraza/{id}/udalit', [AdminPracticeImagesController::class, 'destroy'])->whereNumber('id')->name('admin.practice.images.destroy');
                Route::post('/praktiki/obraza/{id}/build', [AdminPracticeImagesController::class, 'build'])->whereNumber('id')->name('admin.practice.images.build');
                Route::post('/praktiki/obraza/{id}/reimport-template', [AdminPracticeImagesController::class, 'reimportTemplate'])->whereNumber('id')->name('admin.practice.images.reimport');
                Route::post('/praktiki/obraza/{id}/export', [AdminPracticeImagesController::class, 'export'])->whereNumber('id')->name('admin.practice.images.export');
                Route::post('/nastroyki/modul/dobavit', [AdminCourseSettingsController::class, 'storeModule'])->name('admin.course.settings.module.store');
                Route::post('/nastroyki/modul/{courseModule}', [AdminCourseSettingsController::class, 'updateModule'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.settings.module.update');
                Route::post('/nastroyki/modul/{courseModule}/udalit', [AdminCourseSettingsController::class, 'destroyModule'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.settings.module.destroy');
                Route::post('/nastroyki/moduli/poryadok', [AdminCourseSettingsController::class, 'reorderModules'])->name('admin.course.settings.modules.reorder');

                Route::get('/nastroyki/modul/{courseModule}', [AdminCourseSettingsController::class, 'moduleSections'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.sections');
                Route::get('/nastroyki/modul/{courseModule}/praktika', [AdminCourseSettingsController::class, 'modulePractice'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.practice');
                Route::post('/nastroyki/modul/{courseModule}/praktika/save', [AdminCourseSettingsController::class, 'saveModulePractice'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.practice.save');
                Route::post('/nastroyki/modul/{courseModule}/razdel', [AdminCourseSettingsController::class, 'storeSection'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.sections.store');
                Route::post('/nastroyki/modul/{courseModule}/razdel/{section}', [AdminCourseSettingsController::class, 'updateSection'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.module.sections.update');
                Route::post('/nastroyki/modul/{courseModule}/razdel/{section}/udalit', [AdminCourseSettingsController::class, 'destroySection'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.module.sections.destroy');
                Route::post('/nastroyki/modul/{courseModule}/poryadok', [AdminCourseSettingsController::class, 'reorderSections'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.sections.reorder');
                Route::get('/nastroyki/modul/{courseModule}/razdel/{section}', [AdminCourseSettingsController::class, 'editSettings'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.module.section.settings');
                Route::post('/nastroyki/modul/{courseModule}/razdel/{section}/save', [AdminCourseSettingsController::class, 'saveSettings'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.module.section.settings.save');
                Route::get('/nastroyki/modul/{courseModule}/razdel/{section}/panel-data', [AdminCourseSettingsController::class, 'sectionPanelData'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.section.panel.data');
                Route::post('/nastroyki/modul/{courseModule}/razdel/{section}/panel-save', [AdminCourseSettingsController::class, 'sectionPanelSave'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.section.panel.save');
                Route::post('/nastroyki/modul/{courseModule}/razdel/{section}/bystraya-ssylka', [AdminCourseSettingsController::class, 'sectionQuickLinkGenerate'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.section.quick-link.generate');
                Route::post('/nastroyki/modul/{courseModule}/razdel/{section}/bystraya-ssylka/off', [AdminCourseSettingsController::class, 'sectionQuickLinkRevoke'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.section.quick-link.revoke');
                Route::post('/nastroyki/modul/{courseModule}/razdel/{section}/opros-priglashenie', [AdminCourseSettingsController::class, 'sectionSurveyInvite'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.section.survey-invite');
                Route::post('/nastroyki/bystraya-ssylka', [AdminCourseSettingsController::class, 'courseShareLinkGenerate'])
                    ->name('admin.course.share-link.generate');
                Route::post('/nastroyki/bystraya-ssylka/off', [AdminCourseSettingsController::class, 'courseShareLinkRevoke'])
                    ->name('admin.course.share-link.revoke');
                Route::post('/nastroyki/modul/{courseModule}/bystraya-ssylka', [AdminCourseSettingsController::class, 'moduleShareLinkGenerate'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.share-link.generate');
                Route::post('/nastroyki/modul/{courseModule}/bystraya-ssylka/off', [AdminCourseSettingsController::class, 'moduleShareLinkRevoke'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.share-link.revoke');
                Route::post('/nastroyki/modul/{courseModule}/razdel/{section}/share-ssylka', [AdminCourseSettingsController::class, 'sectionShareLinkGenerate'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.section.share-link.generate');
                Route::post('/nastroyki/modul/{courseModule}/razdel/{section}/share-ssylka/off', [AdminCourseSettingsController::class, 'sectionShareLinkRevoke'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.section.share-link.revoke');
                Route::get('/nastroyki/ssylka-meta', [AdminCourseSettingsController::class, 'shareLinkMeta'])
                    ->name('admin.course.share-link.meta');

                Route::get('/nastroyki/dostup', [\App\Http\Controllers\AdminContentVisibilityController::class, 'showCourse'])
                    ->name('admin.course.visibility');
                Route::put('/nastroyki/dostup', [\App\Http\Controllers\AdminContentVisibilityController::class, 'updateCourse'])
                    ->name('admin.course.visibility.update');
                Route::get('/nastroyki/obuchayushchiesya/search', [\App\Http\Controllers\AdminContentVisibilityController::class, 'searchLearners'])
                    ->name('admin.course.learners.search');
                Route::post('/nastroyki/obuchayushchiesya/resolve', [\App\Http\Controllers\AdminContentVisibilityController::class, 'resolveLearners'])
                    ->name('admin.course.learners.resolve');
                Route::get('/nastroyki/modul/{courseModule}/dostup', [\App\Http\Controllers\AdminContentVisibilityController::class, 'showModule'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.visibility');
                Route::put('/nastroyki/modul/{courseModule}/dostup', [\App\Http\Controllers\AdminContentVisibilityController::class, 'updateModule'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.visibility.update');
                Route::get('/nastroyki/modul/{courseModule}/razdel/{section}/dostup', [\App\Http\Controllers\AdminContentVisibilityController::class, 'showSection'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.section.visibility');
                Route::put('/nastroyki/modul/{courseModule}/razdel/{section}/dostup', [\App\Http\Controllers\AdminContentVisibilityController::class, 'updateSection'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.section.visibility.update');
                Route::post('/nastroyki/gruppy', [\App\Http\Controllers\AdminLearnerGroupsController::class, 'courseStore'])
                    ->name('admin.course.learner-groups.store');
                Route::post('/nastroyki/gruppy/{group}', [\App\Http\Controllers\AdminLearnerGroupsController::class, 'courseUpdate'])
                    ->whereNumber('group')
                    ->name('admin.course.learner-groups.update');
                Route::post('/nastroyki/gruppy/{group}/udalit', [\App\Http\Controllers\AdminLearnerGroupsController::class, 'courseDestroy'])
                    ->whereNumber('group')
                    ->name('admin.course.learner-groups.destroy');
                Route::get('/nastroyki/modul/{courseModule}/razdel/{section}/otvety', [AdminSurveyResponsesController::class, 'index'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.module.section.survey-responses');
                Route::get('/nastroyki/modul/{courseModule}/razdel/{section}/otvety/export.csv', [AdminSurveyResponsesController::class, 'exportCsv'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.module.section.survey-responses.export');
                Route::get('/nastroyki/modul/{courseModule}/razdel/{section}/uchastniki', [\App\Http\Controllers\AdminSectionParticipantsController::class, 'index'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.module.section.participants');
                Route::get('/nastroyki/modul/{courseModule}/razdel/{section}/uchastniki.json', [\App\Http\Controllers\AdminSectionParticipantsController::class, 'indexJson'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->name('admin.course.module.section.participants.json');
                Route::get('/nastroyki/modul/{courseModule}/razdel/{section}/uchastniki/{learner}', [\App\Http\Controllers\AdminSectionParticipantsController::class, 'detailJson'])
                    ->whereNumber('courseModule')
                    ->whereNumber('section')
                    ->whereNumber('learner')
                    ->name('admin.course.module.section.participants.detail');
                Route::get('/oprosy', [\App\Http\Controllers\AdminCourseSurveysController::class, 'index'])->name('admin.course.surveys');
                Route::get('/oprosy/razdel/{section}/export.xls', [\App\Http\Controllers\AdminCourseSurveysController::class, 'exportWide'])
                    ->whereNumber('section')
                    ->name('admin.course.surveys.export.wide');
                Route::get('/oprosy/razdel/{section}/export-long.xls', [\App\Http\Controllers\AdminCourseSurveysController::class, 'exportLong'])
                    ->whereNumber('section')
                    ->name('admin.course.surveys.export.long');
                Route::get('/obuchayushiesya', [AdminLearnersController::class, 'indexCourse'])->name('admin.learners.course');
                Route::get('/obuchayushiesya/learner/{learner}', [AdminLearnersController::class, 'courseLearnerShow'])
                    ->whereNumber('learner')
                    ->name('admin.learners.course.learner');
                Route::get('/obuchayushiesya/detail/{learner}', [AdminLearnersController::class, 'courseLearnerDetailJson'])
                    ->whereNumber('learner')
                    ->name('admin.learners.course.detail');
                Route::get('/obuchayushiesya/learner/{learner}/modul/{courseModule}', [AdminLearnersController::class, 'courseLearnerModuleShow'])
                    ->whereNumber('learner')
                    ->whereNumber('courseModule')
                    ->name('admin.learners.course.learner.module');
                Route::post('/obuchayushiesya/learner/{learner}/modul/{courseModule}/sbros', [AdminLearnersController::class, 'courseLearnerReset'])
                    ->whereNumber('learner')
                    ->whereNumber('courseModule')
                    ->name('admin.learners.course.learner.reset');
                Route::get('/sertifikaty', [AdminPanelController::class, 'certificates'])->name('admin.certificates');
                Route::get('/sertifikaty/{result}', [AdminPanelController::class, 'certificateShow'])->name('admin.certificates.show');
                Route::post('/testy/modul/{module}/{kind}', [AdminQuizController::class, 'save'])
                    ->whereNumber('module')
                    ->name('admin.quiz.save.module');
                Route::post('/testy/final-lab', [AdminQuizController::class, 'saveFinal'])->name('admin.quiz.save.final');
                Route::post('/soderzhimoe/modul/{module}/container/start', [AdminTheoryController::class, 'startPracticeLabProbe'])
                    ->whereNumber('module')
                    ->name('admin.theory.container.start');
                Route::post('/soderzhimoe/modul/{module}/container/finish', [AdminTheoryController::class, 'finishPracticeLabProbe'])
                    ->whereNumber('module')
                    ->name('admin.theory.container.finish');
                Route::post('/soderzhimoe/markdown-preview', [AdminTheoryController::class, 'previewMarkdown'])
                    ->name('admin.theory.markdown-preview');
                Route::get('/soderzhimoe/modul/{module}', [AdminTheoryController::class, 'edit'])
                    ->whereNumber('module')
                    ->name('admin.theory.edit');
                Route::post('/soderzhimoe/modul/{module}', [AdminTheoryController::class, 'update'])
                    ->whereNumber('module')
                    ->name('admin.theory.update');

                Route::get('/kontent/modul/{courseModule}', [AdminCourseContentController::class, 'edit'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.content.edit');
                Route::post('/kontent/modul/{courseModule}', [AdminCourseContentController::class, 'update'])
                    ->whereNumber('courseModule')
                    ->name('admin.course.module.content.update');
            });
        });

    Route::middleware([\App\Http\Middleware\EnsureAdminCourseSelected::class, \App\Http\Middleware\DenyCourseTester::class])->group(function () {
        Route::post('/adm/praktika/obraza', [AdminPracticeImagesController::class, 'store']);
        Route::post('/adm/praktika/obraza/system/copy', [AdminPracticeImagesController::class, 'copySystem']);
        Route::post('/adm/praktika/obraza/stats/refresh', [AdminPracticeImagesController::class, 'refreshStats']);
        Route::post('/adm/praktika/obraza/{id}', [AdminPracticeImagesController::class, 'update'])->whereNumber('id');
        Route::post('/adm/praktika/obraza/{id}/udalit', [AdminPracticeImagesController::class, 'destroy'])->whereNumber('id');
        Route::post('/adm/praktika/obraza/{id}/build', [AdminPracticeImagesController::class, 'build'])->whereNumber('id');
        Route::post('/adm/praktika/obraza/{id}/export', [AdminPracticeImagesController::class, 'export'])->whereNumber('id');
        Route::post('/adm/kurs/nastroyki/save', [AdminCourseSettingsController::class, 'saveCourseSettings']);
        Route::post('/adm/kurs/nastroyki/modul/dobavit', [AdminCourseSettingsController::class, 'storeModule']);
        Route::post('/adm/kurs/nastroyki/modul/{courseModule}', [AdminCourseSettingsController::class, 'updateModule'])->whereNumber('courseModule');
        Route::post('/adm/kurs/nastroyki/modul/{courseModule}/udalit', [AdminCourseSettingsController::class, 'destroyModule'])->whereNumber('courseModule');
        Route::post('/adm/kurs/nastroyki/moduli/poryadok', [AdminCourseSettingsController::class, 'reorderModules']);
        Route::post('/adm/kurs/nastroyki/modul/{courseModule}/praktika/save', [AdminCourseSettingsController::class, 'saveModulePractice'])->whereNumber('courseModule');
        Route::post('/adm/kurs/nastroyki/modul/{courseModule}/razdel', [AdminCourseSettingsController::class, 'storeSection'])->whereNumber('courseModule');
        Route::post('/adm/kurs/nastroyki/modul/{courseModule}/razdel/{section}', [AdminCourseSettingsController::class, 'updateSection'])->whereNumber('courseModule')->whereNumber('section');
        Route::post('/adm/kurs/nastroyki/modul/{courseModule}/razdel/{section}/udalit', [AdminCourseSettingsController::class, 'destroySection'])->whereNumber('courseModule')->whereNumber('section');
        Route::post('/adm/kurs/nastroyki/modul/{courseModule}/poryadok', [AdminCourseSettingsController::class, 'reorderSections'])->whereNumber('courseModule');
        Route::post('/adm/kurs/nastroyki/modul/{courseModule}/razdel/{section}/save', [AdminCourseSettingsController::class, 'saveSettings'])->whereNumber('courseModule')->whereNumber('section');
        Route::get('/adm/kurs/nastroyki/modul/{courseModule}/razdel/{section}/panel-data', [AdminCourseSettingsController::class, 'sectionPanelData'])->whereNumber('courseModule')->whereNumber('section');
        Route::post('/adm/kurs/nastroyki/modul/{courseModule}/razdel/{section}/panel-save', [AdminCourseSettingsController::class, 'sectionPanelSave'])->whereNumber('courseModule')->whereNumber('section');
        Route::post('/adm/voprosy/modul/{module}/{kind}', [AdminQuizController::class, 'save'])->whereNumber('module');
        Route::post('/adm/voprosy/final-lab', [AdminQuizController::class, 'saveFinal']);
        Route::post('/adm/kurs-teoriya/modul/{module}/container/start', [AdminTheoryController::class, 'startPracticeLabProbe'])->whereNumber('module');
        Route::post('/adm/kurs-teoriya/modul/{module}/container/finish', [AdminTheoryController::class, 'finishPracticeLabProbe'])->whereNumber('module');
        Route::post('/adm/kurs-teoriya/modul/{module}', [AdminTheoryController::class, 'update'])->whereNumber('module');
        Route::post('/adm/kurs/kontent/modul/{courseModule}', [AdminCourseContentController::class, 'update'])->whereNumber('courseModule');
    });
});
