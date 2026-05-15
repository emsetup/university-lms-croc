<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AdminQuizController;
use App\Http\Controllers\AdminTheoryController;
use App\Http\Controllers\AdminCoursesController;
use App\Http\Controllers\AdminCourseSettingsController;
use App\Http\Controllers\AdminLearnersController;
use App\Http\Controllers\AdminPracticeImagesController;
use App\Http\Controllers\AdminDockerLibraryController;
use App\Http\Controllers\AdminCourseContentController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailLoginController;
use App\Http\Controllers\FinalLabController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OidcLoginController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PracticeLabController;
use App\Http\Controllers\TeacherCourseReportController;
use App\Models\Course;
use App\Services\PortalStaffAccess;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

// Дочерние @section рендерятся до layout: переменная нужна и на `admin.*`, `portal.*`, не только на layouts.course.
View::composer(['layouts.course', 'admin.*', 'portal.*', 'layouts.admin', 'layouts.admin-preview', 'teacher-course-report'], function ($view) {
    if (\App\Support\StaffImpersonation::isPreviewRequest(request())) {
        $view->with('portalStaffAccess', null);
        $view->with('learnerPreviewActive', true);

        return;
    }
    $id = (int) session('learner_id', 0);
    $access = $id > 0 ? PortalStaffAccess::fromLearnerId($id) : null;
    $view->with('portalStaffAccess', $access);
    $view->with('learnerPreviewActive', false);
});

View::composer('layouts.admin', function ($view) {
    $view->with('adminBreadcrumbs', \App\Support\AdminNavigation::breadcrumbs());
    $view->with('adminSidebarActive', \App\Support\AdminNavigation::sidebarActive());
    $view->with('adminCourseTab', \App\Support\AdminNavigation::courseTab());
    $view->with('adminShowCourseChrome', \App\Support\AdminNavigation::showCourseChrome());
    $view->with('adminCurrentCourse', \App\Support\AdminNavigation::currentCourse());
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
    \App\Http\Middleware\MaintenanceForUsers::class,
])->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('portal');
    Route::post('/portal/enroll/{course}', [\App\Http\Controllers\PortalEnrollController::class, 'store'])
        ->whereNumber('course')
        ->name('portal.enroll');

    Route::middleware([\App\Http\Middleware\EnsureLearner::class])->group(function () {
        Route::get('/account', AccountController::class)->name('account');
    });

    Route::middleware([\App\Http\Middleware\EnsureLearner::class, \App\Http\Middleware\EnsureCourseSelected::class])->group(function () {
    // Dashboard is course-scoped; keep legacy /dashboard as redirect.
    Route::get('/dashboard', function () {
        return redirect()->route('course.dashboard', ['course' => (int) session('course_id')]);
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
    Route::get('/certificate', CertificateController::class)->name('certificate');
    Route::post('/certificate/recipient', [CertificateController::class, 'saveRecipient'])->name('certificate.recipient');

    Route::prefix('module/{module}')->whereNumber('module')->group(function () {
        Route::get('/', [ModuleController::class, 'hub'])->name('modules.hub');
        Route::post('/briefing', [ModuleController::class, 'ackHubBriefing'])->name('modules.hub.ack');
        Route::post('/difficulties', [ModuleController::class, 'saveDifficulties'])->name('modules.difficulties');

        Route::get('/theory', [ModuleController::class, 'theory'])->name('modules.theory');
        Route::post('/theory/read', [ModuleController::class, 'markTheoryRead'])->name('modules.theory.read');

        Route::get('/theory-quiz', [ModuleController::class, 'theoryQuizShow'])->name('modules.theory-quiz');
        Route::post('/theory-quiz/start', [ModuleController::class, 'theoryQuizStart'])->name('modules.theory-quiz.start');
        Route::post('/theory-quiz/submit', [ModuleController::class, 'theoryQuizSubmit'])->name('modules.theory-quiz.submit');
        Route::get('/theory-quiz/result', [ModuleController::class, 'theoryQuizResult'])->name('modules.theory-quiz.result');

        Route::get('/practice', [ModuleController::class, 'practiceShow'])->name('modules.practice');
        Route::post('/practice/done', [ModuleController::class, 'practiceDone'])->name('modules.practice.done');

        Route::post('/practice/lab/start', [PracticeLabController::class, 'start'])->name('modules.practice.lab.start');
        Route::post('/practice/lab/check', [PracticeLabController::class, 'check'])->name('modules.practice.lab.check');
        Route::post('/practice/lab/accept', [PracticeLabController::class, 'accept'])->name('modules.practice.lab.accept');
        Route::post('/practice/lab/finish', [PracticeLabController::class, 'finish'])->name('modules.practice.lab.finish');

        Route::get('/exam', [ModuleController::class, 'examShow'])->name('modules.exam');
        Route::post('/exam/start', [ModuleController::class, 'examStart'])->name('modules.exam.start');
        Route::post('/exam/submit', [ModuleController::class, 'examSubmit'])->name('modules.exam.submit');
        Route::get('/exam/result', [ModuleController::class, 'examResult'])->name('modules.exam.result');
    });
    });
});

Route::middleware([\App\Http\Middleware\EnsureLearner::class, \App\Http\Middleware\EnsurePortalStaff::class])->group(function () {
    Route::get('/adm', [AdminPanelController::class, 'show'])->name('admin.panel');
    Route::get('/adm/sobytiya', [AdminPanelController::class, 'activity'])->name('admin.activity');
    Route::get('/adm/paleta/poisk', [AdminPanelController::class, 'commandPaletteSearch'])->name('admin.command-palette.search');

    Route::get('/adm/nastroiki', [AdminSettingsController::class, 'show'])->name('admin.settings');
    Route::post('/adm/nastroiki/zaglushka', [AdminSettingsController::class, 'updateMaintenance'])->name('admin.settings.maintenance');
    Route::post('/adm/nastroiki/zaglushka/sbros', [AdminSettingsController::class, 'resetMaintenance'])->name('admin.settings.maintenance.reset');
    Route::post('/adm/nastroiki/prosmotr', [AdminSettingsController::class, 'impersonate'])->name('admin.settings.impersonate');
    Route::get('/adm/nastroiki/poisk-obuchayushchihsya', [AdminSettingsController::class, 'learnerSearch'])->name('admin.settings.learner-search');

    Route::middleware([\App\Http\Middleware\DenyCourseTester::class])->group(function () {
        Route::get('/adm/docker', [AdminDockerLibraryController::class, 'index'])->name('admin.docker.library');
        Route::post('/adm/docker', [AdminDockerLibraryController::class, 'store'])->name('admin.docker.library.store');
        Route::post('/adm/docker/stats/refresh', [AdminDockerLibraryController::class, 'refreshStats'])->name('admin.docker.library.stats.refresh');
        Route::get('/adm/docker/pkg-search', [AdminPracticeImagesController::class, 'pkgSearch'])->name('admin.docker.library.pkg.search');
        Route::get('/adm/docker/{id}', [AdminPracticeImagesController::class, 'edit'])->whereNumber('id')->name('admin.docker.library.edit');
        Route::post('/adm/docker/{id}', [AdminPracticeImagesController::class, 'update'])->whereNumber('id')->name('admin.docker.library.update');
        Route::post('/adm/docker/{id}/build', [AdminDockerLibraryController::class, 'build'])->whereNumber('id')->name('admin.docker.library.build');
        Route::post('/adm/docker/{id}/export', [AdminPracticeImagesController::class, 'export'])->whereNumber('id')->name('admin.docker.library.export');
        Route::post('/adm/docker/{id}/udalit', [AdminDockerLibraryController::class, 'destroy'])->whereNumber('id')->name('admin.docker.library.destroy');
    });

    Route::middleware([\App\Http\Middleware\EnsureStaffAbility::class.':manage_staff'])->group(function () {
        Route::get('/adm/sotrudniki', [AdminStaffController::class, 'index'])->name('admin.staff.index');
        Route::get('/adm/sotrudniki/sozdat', [AdminStaffController::class, 'create'])->name('admin.staff.create');
        Route::post('/adm/sotrudniki', [AdminStaffController::class, 'store'])->name('admin.staff.store');
        Route::get('/adm/sotrudniki/{staff}/redaktirovat', [AdminStaffController::class, 'edit'])
            ->whereNumber('staff')
            ->name('admin.staff.edit');
        Route::post('/adm/sotrudniki/{staff}', [AdminStaffController::class, 'update'])
            ->whereNumber('staff')
            ->name('admin.staff.update');
        Route::post('/adm/sotrudniki/{staff}/udalit', [AdminStaffController::class, 'destroy'])
            ->whereNumber('staff')
            ->name('admin.staff.destroy');
    });

    Route::get('/adm/kursy', [AdminCoursesController::class, 'index'])->name('admin.courses.index');
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

            return redirect()->route('admin.quiz.index', ['adminCourse' => $s], 302);
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
        ])
        ->group(function () {
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
            Route::get('/soderzhimoe/final-lab', [AdminTheoryController::class, 'previewFinalLab'])
                ->name('admin.theory.preview-final-lab');

            Route::get('/testy', [AdminQuizController::class, 'index'])->name('admin.quiz.index');
            Route::get('/testy/modul/{module}/{kind}', [AdminQuizController::class, 'editModule'])
                ->whereNumber('module')
                ->name('admin.quiz.edit.module');
            Route::get('/testy/final-lab', [AdminQuizController::class, 'editFinal'])->name('admin.quiz.edit.final');

            Route::middleware([\App\Http\Middleware\DenyCourseTester::class])->group(function () {
                Route::get('/nastroyki', [AdminCourseSettingsController::class, 'courseSettings'])->name('admin.course.settings');
                Route::post('/nastroyki/save', [AdminCourseSettingsController::class, 'saveCourseSettings'])->name('admin.course.settings.save');
                Route::get('/moduli', function () {
                    return redirect()->route('admin.course.settings', array_merge(
                        ['adminCourse' => request()->route('adminCourse')->slug],
                        request()->query('key') !== null && request()->query('key') !== '' ? ['key' => request()->query('key')] : []
                    ), 301);
                })->name('admin.course.modules');
                Route::get('/praktiki/obraza', [AdminPracticeImagesController::class, 'index'])->name('admin.practice.images.index');
                Route::get('/praktiki/obraza/create', [AdminPracticeImagesController::class, 'create'])->name('admin.practice.images.create');
                Route::post('/praktiki/obraza', [AdminPracticeImagesController::class, 'store'])->name('admin.practice.images.store');
                Route::post('/praktiki/obraza/system/copy', [AdminPracticeImagesController::class, 'copySystem'])->name('admin.practice.images.system.copy');
                Route::post('/praktiki/obraza/stats/refresh', [AdminPracticeImagesController::class, 'refreshStats'])->name('admin.practice.images.stats.refresh');
                Route::get('/praktiki/obraza/pkg-search', [AdminPracticeImagesController::class, 'pkgSearch'])->name('admin.practice.images.pkg.search');
                Route::get('/praktiki/obraza/{id}', [AdminPracticeImagesController::class, 'edit'])->whereNumber('id')->name('admin.practice.images.edit');
                Route::post('/praktiki/obraza/{id}', [AdminPracticeImagesController::class, 'update'])->whereNumber('id')->name('admin.practice.images.update');
                Route::post('/praktiki/obraza/{id}/udalit', [AdminPracticeImagesController::class, 'destroy'])->whereNumber('id')->name('admin.practice.images.destroy');
                Route::post('/praktiki/obraza/{id}/build', [AdminPracticeImagesController::class, 'build'])->whereNumber('id')->name('admin.practice.images.build');
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
                Route::get('/obuchayushiesya', [AdminLearnersController::class, 'indexCourse'])->name('admin.learners.course');
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
