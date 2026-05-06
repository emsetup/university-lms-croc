<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminCoursesController extends Controller
{
    public function index(Request $request): View
    {
        $adminKey = (string) $request->query('key', '');
        // На странице выбора курса считаем, что курс ещё не выбран.
        session()->forget('admin_course_id');
        session()->forget('admin_course_title');

        $courses = Course::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(function (Course $c) {
                $started = CourseEnrollment::query()
                    ->where('course_id', $c->id)
                    ->whereNotNull('started_at')
                    ->count();
                $enrolled = CourseEnrollment::query()
                    ->where('course_id', $c->id)
                    ->count();

                return [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'title' => $c->title,
                    'summary' => $c->summary,
                    'is_published' => (bool) $c->is_published,
                    'enrolled' => (int) $enrolled,
                    'started' => (int) $started,
                ];
            });

        return view('admin.courses-index', [
            'adminKey' => $adminKey,
            'courses' => $courses,
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
}

