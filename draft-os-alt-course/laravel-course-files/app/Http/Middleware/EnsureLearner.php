<?php

namespace App\Http\Middleware;

use App\Models\Learner;
use App\Support\LearnerPortalLoginPersistence;
use App\Support\LoginReturnUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureLearner
{
    public function handle(Request $request, Closure $next): Response
    {
        $previewId = (int) $request->attributes->get('preview_learner_id', 0);
        $id = $previewId > 0 ? $previewId : session('learner_id');

        if (! $id) {
            LoginReturnUrl::rememberIfSurveyQuick($request);

            return redirect()
                ->route('portal', ['login' => 1])
                ->with('err', 'Для доступа к этому разделу необходимо войти в учётную запись.');
        }

        $learner = Learner::find($id);
        if (! $learner) {
            if ($previewId > 0) {
                return redirect()
                    ->route('portal')
                    ->with('err', 'Обучающийся не найден.');
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('portal', ['login' => 1])
                ->with('err', 'Сессия устарела. Войдите снова.');
        }

        View::share('currentLearner', $learner);
        if ($previewId > 0) {
            View::share('learnerPreviewTarget', $learner);
        } elseif ($previewId === 0) {
            LearnerPortalLoginPersistence::recordLoginForSession($request, $learner);
        }

        return $next($request);
    }
}
