<?php

namespace App\Http\Middleware;

use App\Models\Learner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureLearner
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = session('learner_id');
        if (! $id) {
            return redirect()->route('portal', ['login' => 1]);
        }

        $learner = Learner::find($id);
        if (! $learner) {
            session()->forget('learner_id');

            return redirect()->route('portal', ['login' => 1]);
        }

        View::share('currentLearner', $learner);

        return $next($request);
    }
}
