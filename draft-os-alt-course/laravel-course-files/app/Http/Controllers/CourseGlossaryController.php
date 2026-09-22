<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseGlossaryTerm;
use App\Support\LearnerPreviewContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class CourseGlossaryController extends Controller
{
    public function __invoke(): View
    {
        $courseId = LearnerPreviewContext::courseId();
        abort_unless($courseId > 0, 404);

        $course = Course::query()->findOrFail($courseId);
        $terms = Schema::hasTable('course_glossary_terms')
            ? CourseGlossaryTerm::query()
                ->where('course_id', $courseId)
                ->orderBy('sort')
                ->orderBy('term')
                ->get()
            : collect();

        return view('glossary', [
            'course' => $course,
            'terms' => $terms,
        ]);
    }
}
