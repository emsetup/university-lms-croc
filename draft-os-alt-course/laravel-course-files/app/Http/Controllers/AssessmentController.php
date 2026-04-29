<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Services\CourseScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring
    ) {}

    public function __invoke(): View|RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        if (! $this->scoring->allModulesComplete($learner)) {
            return redirect()->route('dashboard')->with('err', 'Итоговая оценка по модулям доступна после прохождения всех модулей.');
        }

        return view('assessment', [
            'assessmentSnapshot' => $this->scoring->learnerAssessmentSnapshot($learner),
        ]);
    }
}
