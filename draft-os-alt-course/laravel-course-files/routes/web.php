<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AdminQuizController;
use App\Http\Controllers\AdminTheoryController;
use App\Http\Controllers\AdminCoursesController;
use App\Http\Controllers\AdminCourseSettingsController;
use App\Http\Controllers\AdminLearnersController;
use App\Http\Controllers\AdminPracticeImagesController;
use App\Http\Controllers\AdminCourseContentController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailLoginController;
use App\Http\Controllers\FinalLabController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PracticeLabController;
use App\Http\Controllers\TeacherCourseReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'index'])->name('portal');
Route::post('/portal/enroll/{course}', [\App\Http\Controllers\PortalEnrollController::class, 'store'])
    ->whereNumber('course')
    ->name('portal.enroll');

Route::get('/login', [EmailLoginController::class, 'show'])->name('login');
Route::post('/login', [EmailLoginController::class, 'store'])->name('login.store');
Route::post('/logout', [EmailLoginController::class, 'logout'])->name('logout');

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

Route::middleware([\App\Http\Middleware\EnsureCourseAdminToken::class])->group(function () {
    Route::get('/adm', [AdminPanelController::class, 'show'])->name('admin.panel');
    Route::get('/adm/kursy', [AdminCoursesController::class, 'index'])->name('admin.courses.index');
    Route::get('/adm/kursy/sozdat', [AdminCoursesController::class, 'create'])->name('admin.courses.create');
    Route::post('/adm/kursy', [AdminCoursesController::class, 'store'])->name('admin.courses.store');
    Route::get('/adm/kursy/{course}/redактировать', [AdminCoursesController::class, 'edit'])
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
    Route::post('/adm/kursy/{course}/select', [AdminCoursesController::class, 'select'])
        ->whereNumber('course')
        ->name('admin.courses.select');
    Route::get('/adm/kursy/{course}/enter', [AdminCoursesController::class, 'enter'])
        ->whereNumber('course')
        ->name('admin.courses.enter');
    Route::get('/adm/obuchayushiesya', [AdminLearnersController::class, 'indexPortal'])->name('admin.learners.portal');

    Route::middleware([\App\Http\Middleware\EnsureAdminCourseSelected::class])->group(function () {
    Route::get('/adm/kurs/nastroyki', [AdminCourseSettingsController::class, 'modulesIndex'])->name('admin.course.settings');
    Route::get('/adm/praktika/obraza', [AdminPracticeImagesController::class, 'index'])->name('admin.practice.images.index');
    Route::get('/adm/praktika/obraza/create', [AdminPracticeImagesController::class, 'create'])->name('admin.practice.images.create');
    Route::post('/adm/praktika/obraza', [AdminPracticeImagesController::class, 'store'])->name('admin.practice.images.store');
    Route::post('/adm/praktika/obraza/system/copy', [AdminPracticeImagesController::class, 'copySystem'])->name('admin.practice.images.system.copy');
    Route::post('/adm/praktika/obraza/stats/refresh', [AdminPracticeImagesController::class, 'refreshStats'])->name('admin.practice.images.stats.refresh');
    Route::get('/adm/praktika/obraza/pkg-search', [AdminPracticeImagesController::class, 'pkgSearch'])->name('admin.practice.images.pkg.search');
    Route::get('/adm/praktika/obraza/{id}', [AdminPracticeImagesController::class, 'edit'])->whereNumber('id')->name('admin.practice.images.edit');
    Route::post('/adm/praktika/obraza/{id}', [AdminPracticeImagesController::class, 'update'])->whereNumber('id')->name('admin.practice.images.update');
    Route::post('/adm/praktika/obraza/{id}/udalit', [AdminPracticeImagesController::class, 'destroy'])->whereNumber('id')->name('admin.practice.images.destroy');
    Route::post('/adm/praktika/obraza/{id}/build', [AdminPracticeImagesController::class, 'build'])->whereNumber('id')->name('admin.practice.images.build');
    Route::post('/adm/praktika/obraza/{id}/export', [AdminPracticeImagesController::class, 'export'])->whereNumber('id')->name('admin.practice.images.export');
    Route::post('/adm/kurs/nastroyki/modul/dobavit', [AdminCourseSettingsController::class, 'storeModule'])->name('admin.course.settings.module.store');
    Route::post('/adm/kurs/nastroyki/modul/{courseModule}', [AdminCourseSettingsController::class, 'updateModule'])
        ->whereNumber('courseModule')
        ->name('admin.course.settings.module.update');
    Route::post('/adm/kurs/nastroyki/modul/{courseModule}/udalit', [AdminCourseSettingsController::class, 'destroyModule'])
        ->whereNumber('courseModule')
        ->name('admin.course.settings.module.destroy');
    Route::post('/adm/kurs/nastroyki/moduli/poryadok', [AdminCourseSettingsController::class, 'reorderModules'])->name('admin.course.settings.modules.reorder');

    Route::get('/adm/kurs/nastroyki/modul/{courseModule}', [AdminCourseSettingsController::class, 'moduleSections'])
        ->whereNumber('courseModule')
        ->name('admin.course.module.sections');
    Route::get('/adm/kurs/nastroyki/modul/{courseModule}/praktika', [AdminCourseSettingsController::class, 'modulePractice'])
        ->whereNumber('courseModule')
        ->name('admin.course.module.practice');
    Route::post('/adm/kurs/nastroyki/modul/{courseModule}/praktika/save', [AdminCourseSettingsController::class, 'saveModulePractice'])
        ->whereNumber('courseModule')
        ->name('admin.course.module.practice.save');
    Route::post('/adm/kurs/nastroyki/modul/{courseModule}/razdel', [AdminCourseSettingsController::class, 'storeSection'])
        ->whereNumber('courseModule')
        ->name('admin.course.module.sections.store');
    Route::post('/adm/kurs/nastroyki/modul/{courseModule}/razdel/{section}', [AdminCourseSettingsController::class, 'updateSection'])
        ->whereNumber('courseModule')
        ->whereNumber('section')
        ->name('admin.course.module.sections.update');
    Route::post('/adm/kurs/nastroyki/modul/{courseModule}/razdel/{section}/udalit', [AdminCourseSettingsController::class, 'destroySection'])
        ->whereNumber('courseModule')
        ->whereNumber('section')
        ->name('admin.course.module.sections.destroy');
    Route::post('/adm/kurs/nastroyki/modul/{courseModule}/poryadok', [AdminCourseSettingsController::class, 'reorderSections'])
        ->whereNumber('courseModule')
        ->name('admin.course.module.sections.reorder');
    Route::get('/adm/kurs/nastroyki/modul/{courseModule}/razdel/{section}', [AdminCourseSettingsController::class, 'editSettings'])
        ->whereNumber('courseModule')
        ->whereNumber('section')
        ->name('admin.course.module.section.settings');
    Route::post('/adm/kurs/nastroyki/modul/{courseModule}/razdel/{section}/save', [AdminCourseSettingsController::class, 'saveSettings'])
        ->whereNumber('courseModule')
        ->whereNumber('section')
        ->name('admin.course.module.section.settings.save');
    Route::get('/adm/kurs/obuchayushiesya', [AdminLearnersController::class, 'indexCourse'])->name('admin.learners.course');
    Route::get('/adm/sertifikaty', [AdminPanelController::class, 'certificates'])->name('admin.certificates');
    Route::get('/adm/sertifikaty/{result}', [AdminPanelController::class, 'certificateShow'])->name('admin.certificates.show');
    Route::get('/adm/voprosy', [AdminQuizController::class, 'index'])->name('admin.quiz.index');
    Route::get('/adm/voprosy/modul/{module}/{kind}', [AdminQuizController::class, 'editModule'])
        ->whereNumber('module')
        ->name('admin.quiz.edit.module');
    Route::post('/adm/voprosy/modul/{module}/{kind}', [AdminQuizController::class, 'save'])
        ->whereNumber('module')
        ->name('admin.quiz.save.module');
    Route::get('/adm/voprosy/final-lab', [AdminQuizController::class, 'editFinal'])->name('admin.quiz.edit.final');
    Route::post('/adm/voprosy/final-lab', [AdminQuizController::class, 'saveFinal'])->name('admin.quiz.save.final');
    Route::get('/adm/kurs-teoriya', [AdminTheoryController::class, 'index'])->name('admin.theory.index');
    Route::get('/adm/kurs-teoriya/vse-md.zip', [AdminTheoryController::class, 'downloadZip'])->name('admin.theory.zip');
    Route::post('/adm/kurs-teoriya/modul/{module}/container/start', [AdminTheoryController::class, 'startPracticeLabProbe'])
        ->whereNumber('module')
        ->name('admin.theory.container.start');
    Route::post('/adm/kurs-teoriya/modul/{module}/container/finish', [AdminTheoryController::class, 'finishPracticeLabProbe'])
        ->whereNumber('module')
        ->name('admin.theory.container.finish');
    Route::get('/adm/kurs-teoriya/modul/{module}/teoriya', [AdminTheoryController::class, 'previewTheory'])
        ->whereNumber('module')
        ->name('admin.theory.preview-theory');
    Route::get('/adm/kurs-teoriya/modul/{module}/test-teorii', [AdminTheoryController::class, 'previewTheoryQuiz'])
        ->whereNumber('module')
        ->name('admin.theory.preview-theory-quiz');
    Route::get('/adm/kurs-teoriya/modul/{module}/praktika', [AdminTheoryController::class, 'previewPractice'])
        ->whereNumber('module')
        ->name('admin.theory.preview-practice');
    Route::get('/adm/kurs-teoriya/modul/{module}/ekzamen', [AdminTheoryController::class, 'previewModuleExam'])
        ->whereNumber('module')
        ->name('admin.theory.preview-module-exam');
    Route::get('/adm/kurs-teoriya/final-lab', [AdminTheoryController::class, 'previewFinalLab'])
        ->name('admin.theory.preview-final-lab');
    Route::get('/adm/kurs-teoriya/modul/{module}', [AdminTheoryController::class, 'edit'])
        ->whereNumber('module')
        ->name('admin.theory.edit');
    Route::post('/adm/kurs-teoriya/modul/{module}', [AdminTheoryController::class, 'update'])
        ->whereNumber('module')
        ->name('admin.theory.update');

    Route::get('/adm/kurs/kontent/modul/{courseModule}', [AdminCourseContentController::class, 'edit'])
        ->whereNumber('courseModule')
        ->name('admin.course.module.content.edit');
    Route::post('/adm/kurs/kontent/modul/{courseModule}', [AdminCourseContentController::class, 'update'])
        ->whereNumber('courseModule')
        ->name('admin.course.module.content.update');
    });
});

Route::middleware([\App\Http\Middleware\ValidateTeacherReportToken::class])->group(function () {
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
