<?php

namespace App\Http\Middleware;

use App\Support\StaffImpersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Просмотр от лица обучающегося: ?preview=token, сессия сотрудника не трогается.
 */
final class ApplyLearnerPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        StaffImpersonation::clearLegacySessionImpersonation();

        $token = StaffImpersonation::previewTokenFromRequest($request);
        if ($token === null) {
            return $next($request);
        }

        $ctx = StaffImpersonation::resolvePreview($token);
        if ($ctx === null) {
            return redirect()
                ->route('portal')
                ->with('err', 'Ссылка просмотра недействительна или истекла. Откройте пользователя снова из настроек.');
        }

        $sessionLearnerId = (int) session('learner_id', 0);
        if ($sessionLearnerId !== $ctx['staff_learner_id']) {
            return redirect()
                ->route('portal')
                ->with('err', 'Просмотр доступен только под учётной записью сотрудника, который его открыл.');
        }

        $request->attributes->set('preview_learner_id', $ctx['target_learner_id']);
        $request->attributes->set('preview_staff_learner_id', $ctx['staff_learner_id']);
        $request->attributes->set('preview_token', $token);

        URL::defaults([StaffImpersonation::QUERY_PARAM => $token]);
        View::share('learnerPreviewActive', true);
        View::share('learnerPreviewToken', $token);

        return $next($request);
    }
}
