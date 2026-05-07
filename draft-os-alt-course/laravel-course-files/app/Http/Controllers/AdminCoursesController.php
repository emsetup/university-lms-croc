<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class AdminCoursesController extends Controller
{
    public function index(Request $request): View
    {
        $adminKey = (string) $request->query('key', '');
        // На странице выбора курса считаем, что курс ещё не выбран.
        session()->forget('admin_course_id');
        session()->forget('admin_course_title');

        $showArchived = (bool) $request->query('archived', false);

        $courses = Course::query()
            ->when(! $showArchived, fn ($q) => $q->where('is_archived', false))
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(function (Course $c) {
                $courseId = (int) $c->id;
                $enrolled = (int) CourseEnrollment::query()
                    ->where('course_id', $courseId)
                    ->count();
                $completed = 0;

                // Исторические данные могли появиться без enrollment (старые УЗ, импорт, ранние версии).
                // Для админ-дашборда считаем "участники" по объединению enrollments + прогресс + финал.
                if (Schema::hasTable('module_progress') || Schema::hasTable('final_lab_results')) {
                    $enrollmentIds = DB::table('course_enrollments')
                        ->where('course_id', $courseId)
                        ->select('learner_id');

                    $progressIds = Schema::hasTable('module_progress')
                        ? DB::table('module_progress')->where('course_id', $courseId)->select('learner_id')
                        : null;
                    $finalIds = Schema::hasTable('final_lab_results')
                        ? DB::table('final_lab_results')->where('course_id', $courseId)->select('learner_id')
                        : null;

                    $unionEnrolled = $enrollmentIds;
                    if ($progressIds) {
                        $unionEnrolled = $unionEnrolled->union($progressIds);
                    }
                    if ($finalIds) {
                        $unionEnrolled = $unionEnrolled->union($finalIds);
                    }
                    $enrolled = (int) DB::query()
                        ->fromSub($unionEnrolled, 'u')
                        ->distinct()
                        ->count('learner_id');
                }

                // Завершили = есть сертификат (ФИО + серийник) по этому курсу.
                if (Schema::hasTable('final_lab_results')) {
                    $completed = (int) DB::table('final_lab_results')
                        ->where('course_id', $courseId)
                        ->whereNotNull('certificate_full_name')
                        ->whereNotNull('certificate_serial')
                        ->distinct()
                        ->count('learner_id');
                }

                return [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'title' => $c->title,
                    'summary' => $c->summary,
                    'is_published' => (bool) $c->is_published,
                    'is_archived' => (bool) $c->is_archived,
                    'enrolled' => (int) $enrolled,
                    'completed' => (int) $completed,
                ];
            });

        return view('admin.courses-index', [
            'adminKey' => $adminKey,
            'courses' => $courses,
            'showArchived' => $showArchived,
        ]);
    }

    public function select(Request $request, int $course): RedirectResponse
    {
        $adminKey = (string) $request->query('key', '');
        $c = Course::query()->findOrFail($course);
        session([
            'admin_course_id' => $c->id,
            'admin_course_title' => $c->title,
        ]);

        $next = (string) $request->input('next', 'content');
        if ($next === 'quiz') {
            return redirect()->route('admin.quiz.index', ['key' => $adminKey])->with('ok', 'Курс выбран: '.$c->title);
        }
        if ($next === 'certificates') {
            return redirect()->route('admin.certificates', ['key' => $adminKey])->with('ok', 'Курс выбран: '.$c->title);
        }
        if ($next === 'learners') {
            return redirect()->route('admin.learners.course', ['key' => $adminKey])->with('ok', 'Курс выбран: '.$c->title);
        }

        return redirect()->route('admin.theory.index', ['key' => $adminKey])->with('ok', 'Курс выбран: '.$c->title);
    }

    public function enter(Request $request, int $course): RedirectResponse
    {
        $adminKey = (string) $request->query('key', '');
        $c = Course::query()->findOrFail($course);
        session([
            'admin_course_id' => $c->id,
            'admin_course_title' => $c->title,
        ]);

        $next = (string) $request->query('next', 'content');
        if ($next === 'quiz') {
            return redirect()->route('admin.quiz.index', ['key' => $adminKey]);
        }
        if ($next === 'certificates') {
            return redirect()->route('admin.certificates', ['key' => $adminKey]);
        }
        if ($next === 'learners') {
            return redirect()->route('admin.learners.course', ['key' => $adminKey]);
        }

        return redirect()->route('admin.theory.index', ['key' => $adminKey]);
    }

    public function create(Request $request): View
    {
        $adminKey = (string) $request->query('key', '');
        // Создание курса — портал-уровень.
        session()->forget('admin_course_id');
        session()->forget('admin_course_title');

        return view('admin.course-edit', [
            'adminKey' => $adminKey,
            'mode' => 'create',
            'course' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $adminKey = (string) $request->query('key', '');
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:courses,slug'],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_published' => ['nullable', 'in:0,1'],
            'is_archived' => ['nullable', 'in:0,1'],
        ], [
            'slug.regex' => 'Slug: только латиница/цифры и дефис (например: alt-os-features).',
        ]);

        $course = Course::query()->create([
            'slug' => strtolower((string) $data['slug']),
            'title' => (string) $data['title'],
            'summary' => (string) ($data['summary'] ?? ''),
            'sort' => isset($data['sort']) ? (int) $data['sort'] : 100,
            'is_published' => isset($data['is_published']) ? ((string) $data['is_published'] === '1') : false,
            'is_archived' => isset($data['is_archived']) ? ((string) $data['is_archived'] === '1') : false,
        ]);

        return redirect()
            ->route('admin.courses.edit', ['course' => $course->id, 'key' => $adminKey])
            ->with('ok', 'Курс создан.');
    }

    public function edit(Request $request, int $course): View
    {
        $adminKey = (string) $request->query('key', '');
        $c = Course::query()->findOrFail($course);
        $courseId = (int) $c->id;
        $enrolled = (int) CourseEnrollment::query()->where('course_id', $courseId)->count();

        if (Schema::hasTable('module_progress') || Schema::hasTable('final_lab_results')) {
            $enrollmentIds = DB::table('course_enrollments')
                ->where('course_id', $courseId)
                ->select('learner_id');

            $progressIds = Schema::hasTable('module_progress')
                ? DB::table('module_progress')->where('course_id', $courseId)->select('learner_id')
                : null;
            $finalIds = Schema::hasTable('final_lab_results')
                ? DB::table('final_lab_results')->where('course_id', $courseId)->select('learner_id')
                : null;

            $union = $enrollmentIds;
            if ($progressIds) {
                $union = $union->union($progressIds);
            }
            if ($finalIds) {
                $union = $union->union($finalIds);
            }

            $enrolled = (int) DB::query()
                ->fromSub($union, 'u')
                ->distinct()
                ->count('learner_id');
        }

        $completed = 0;
        if (Schema::hasTable('final_lab_results')) {
            $completed = (int) DB::table('final_lab_results')
                ->where('course_id', $courseId)
                ->whereNotNull('certificate_full_name')
                ->whereNotNull('certificate_serial')
                ->distinct()
                ->count('learner_id');
        }

        return view('admin.course-edit', [
            'adminKey' => $adminKey,
            'mode' => 'edit',
            'course' => $c,
            'stats' => [
                'enrolled' => $enrolled,
                'completed' => $completed,
            ],
        ]);
    }

    public function update(Request $request, int $course): RedirectResponse
    {
        $adminKey = (string) $request->query('key', '');
        $c = Course::query()->findOrFail($course);
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:courses,slug,'.$c->id],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_published' => ['nullable', 'in:0,1'],
            'is_archived' => ['nullable', 'in:0,1'],
        ], [
            'slug.regex' => 'Slug: только латиница/цифры и дефис (например: alt-os-features).',
        ]);

        $c->slug = strtolower((string) $data['slug']);
        $c->title = (string) $data['title'];
        $c->summary = (string) ($data['summary'] ?? '');
        $c->sort = isset($data['sort']) ? (int) $data['sort'] : 100;
        $c->is_published = isset($data['is_published']) && (string) $data['is_published'] === '1';
        $c->is_archived = isset($data['is_archived']) && (string) $data['is_archived'] === '1';
        $c->save();

        // Если редактируем текущий выбранный курс админки — обновим заголовок в сессии.
        if ((int) session('admin_course_id', 0) === (int) $c->id) {
            session(['admin_course_title' => $c->title]);
        }

        return redirect()
            ->route('admin.courses.edit', ['course' => $c->id, 'key' => $adminKey])
            ->with('ok', 'Курс обновлён.');
    }

    public function archive(Request $request, int $course): RedirectResponse
    {
        $adminKey = (string) $request->query('key', '');
        $c = Course::query()->findOrFail($course);
        $c->is_archived = true;
        $c->is_published = false;
        $c->save();

        return redirect()
            ->route('admin.courses.edit', ['course' => $c->id, 'key' => $adminKey])
            ->with('ok', 'Курс перенесён в архив.');
    }

    public function unarchive(Request $request, int $course): RedirectResponse
    {
        $adminKey = (string) $request->query('key', '');
        $c = Course::query()->findOrFail($course);
        $c->is_archived = false;
        $c->save();

        return redirect()
            ->route('admin.courses.edit', ['course' => $c->id, 'key' => $adminKey])
            ->with('ok', 'Курс восстановлен из архива.');
    }
}

