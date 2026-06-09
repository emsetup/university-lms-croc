<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Learner;
use App\Support\LearnerPreviewContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring
    ) {}

    public function __invoke(): View|RedirectResponse
    {
        $courseId = LearnerPreviewContext::courseId();
        $course = $courseId > 0 ? Course::query()->find($courseId) : null;
        if ($course
            && Schema::hasColumn('courses', 'assessment_enabled')
            && ! (bool) ($course->assessment_enabled ?? true)) {
            return redirect()
                ->route('course.dashboard', ['course' => $courseId])
                ->with('err', 'Оценка по модулям отключена для этого курса.');
        }

        $learner = Learner::findOrFail(LearnerPreviewContext::learnerId());
        if (! $this->scoring->allModulesComplete($learner)) {
            return redirect()->route('dashboard')->with('err', 'Итоговая оценка по модулям доступна после прохождения всех модулей.');
        }

        return view('assessment', [
            'assessmentSnapshot' => $this->scoring->learnerAssessmentSnapshot($learner),
        ]);
    }
}
