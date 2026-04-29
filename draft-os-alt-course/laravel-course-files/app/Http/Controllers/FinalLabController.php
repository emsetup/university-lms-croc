<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Services\CourseScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinalLabController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring
    ) {}

    /**
     * @param  array<int, array{q:string,a:array,c:int}>  $questions
     */
    protected function scorePercent(array $questions, Request $request, string $prefix): int
    {
        if (count($questions) === 0) {
            return 0;
        }
        $correct = 0;
        foreach (array_keys($questions) as $i) {
            $v = (int) $request->input($prefix.$i);
            if (isset($questions[$i]['c']) && $v === (int) $questions[$i]['c']) {
                $correct++;
            }
        }

        return (int) round(100 * $correct / count($questions));
    }

    public function show(): View|RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        if (! $this->scoring->allModulesComplete($learner)) {
            return redirect()->route('dashboard')->with('err', 'Финальная лабораторная доступна после прохождения всех модулей.');
        }

        $qs = config('course.final_lab_questions');

        return view('final-lab', [
            'questions' => $qs,
            'result' => $learner->finalLabResult,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        if (! $this->scoring->allModulesComplete($learner)) {
            abort(403);
        }

        $qs = config('course.final_lab_questions');
        $result = $learner->finalLabResult()->firstOrCreate(
            ['learner_id' => $learner->id],
            ['attempts' => 0, 'passed' => false, 'best_score' => 0]
        );

        $result->attempts = (int) $result->attempts + 1;
        $score = $this->scorePercent($qs, $request, 'f');
        $result->best_score = max((int) $result->best_score, $score);
        if ($score >= CourseScoringService::PASS_THRESHOLD) {
            $result->passed = true;
            $result->completed_at = now();
        }
        $result->save();

        return redirect()->route('final-lab')->with(
            $score >= CourseScoringService::PASS_THRESHOLD ? 'ok' : 'err',
            $score >= CourseScoringService::PASS_THRESHOLD
                ? 'Финальная лабораторная принята ('.$score.'%).'
                : 'Нужно набрать не менее '.CourseScoringService::PASS_THRESHOLD.'%. Сейчас: '.$score.'%.'
        );
    }
}
