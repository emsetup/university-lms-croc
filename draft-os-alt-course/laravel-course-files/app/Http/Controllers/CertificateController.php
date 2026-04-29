<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Services\CourseScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring
    ) {}

    public function __invoke(): View|RedirectResponse
    {
        $learner = Learner::findOrFail(session('learner_id'));
        $final = $learner->finalLabResult;
        if (! $final || ! $final->passed) {
            return redirect()->route('final-lab')->with('err', 'Сначала сдайте финальную лабораторную работу.');
        }

        return view('certificate', [
            'learner' => $learner,
            'grand' => $this->scoring->grandTotal($learner),
            'modulePoints' => $this->scoring->totalModulePoints($learner),
            'modulePointsMax' => $this->scoring->maxTotalModulePoints(),
            'finalPoints' => $this->scoring->finalLabPoints($final),
            'moduleReport' => $this->scoring->moduleReport($learner),
            'final' => $final,
        ]);
    }
}
