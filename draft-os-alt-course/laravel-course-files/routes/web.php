<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AdminTheoryController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailLoginController;
use App\Http\Controllers\FinalLabController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PracticeLabController;
use App\Http\Controllers\TeacherCourseReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [EmailLoginController::class, 'show'])->name('login');
Route::post('/login', [EmailLoginController::class, 'store'])->name('login.store');
Route::post('/logout', [EmailLoginController::class, 'logout'])->name('logout');

Route::middleware('learner')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/assessment', AssessmentController::class)->name('assessment');
    Route::get('/final-lab', [FinalLabController::class, 'show'])->name('final-lab');
    Route::post('/final-lab', [FinalLabController::class, 'submit'])->name('final-lab.submit');
    Route::get('/certificate', CertificateController::class)->name('certificate');

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
    Route::get('/adm/kurs-teoriya', [AdminTheoryController::class, 'index'])->name('admin.theory.index');
    Route::get('/adm/kurs-teoriya/vse-md.zip', [AdminTheoryController::class, 'downloadZip'])->name('admin.theory.zip');
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
    Route::get('/adm/kurs-teoriya/modul/{module}', [AdminTheoryController::class, 'edit'])
        ->whereNumber('module')
        ->name('admin.theory.edit');
    Route::post('/adm/kurs-teoriya/modul/{module}', [AdminTheoryController::class, 'update'])
        ->whereNumber('module')
        ->name('admin.theory.update');
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
