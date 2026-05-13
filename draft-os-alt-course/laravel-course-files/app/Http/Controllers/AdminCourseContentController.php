<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseModule;
use App\Services\CourseContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class AdminCourseContentController extends Controller
{
    public function __construct(
        private CourseContentService $content
    ) {}

    public function edit(Request $request, CourseModule $courseModule): View|RedirectResponse
    {
        $this->assertModuleCourse($courseModule);

        $courseId = (int) session('admin_course_id', 0);
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        if (! $course || $course->isLegacyAltCourse()) {
            return redirect()
                ->route('admin.theory.index')
                ->with('err', 'Редактор контента в БД доступен только для новых курсов. Курс «Альт» остаётся на legacy-сниппетах.');
        }

        $row = $this->content->contentForModule($courseModule);

        return view('admin.course-module-content-edit', [
            'courseModule' => $courseModule,
            'theory' => (string) ($row['theory_markdown'] ?? ''),
            'practice' => (string) ($row['practice_markdown'] ?? ''),
        ]);
    }

    public function update(Request $request, CourseModule $courseModule): RedirectResponse
    {
        $this->assertModuleCourse($courseModule);

        $courseId = (int) session('admin_course_id', 0);
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        if (! $course || $course->isLegacyAltCourse()) {
            return redirect()
                ->route('admin.theory.index')
                ->with('err', 'Сохранение отклонено: курс работает в legacy-режиме.');
        }
        if (! Schema::hasTable('course_module_contents')) {
            return redirect()
                ->route('admin.course.module.content.edit', ['courseModule' => $courseModule->id])
                ->with('err', 'Таблица course_module_contents не найдена. Выполните миграции.');
        }

        $data = $request->validate([
            'theory_markdown' => ['nullable', 'string', 'max:8000000'],
            'practice_markdown' => ['nullable', 'string', 'max:8000000'],
        ]);

        $theory = str_replace("\r\n", "\n", (string) ($data['theory_markdown'] ?? ''));
        $practice = str_replace("\r\n", "\n", (string) ($data['practice_markdown'] ?? ''));
        $this->content->upsertContentForModule($courseModule, $theory, $practice);

        return redirect()
            ->route('admin.course.module.content.edit', ['courseModule' => $courseModule->id])
            ->with('ok', 'Контент сохранён.');
    }

    private function assertModuleCourse(CourseModule $courseModule): void
    {
        $courseId = (int) session('admin_course_id');
        abort_unless($courseId > 0 && (int) $courseModule->course_id === $courseId, 403);
    }
}

