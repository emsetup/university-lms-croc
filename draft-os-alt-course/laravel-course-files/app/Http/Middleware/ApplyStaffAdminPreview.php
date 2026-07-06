<?php

namespace App\Http\Middleware;

use App\Models\Learner;
use App\Services\PortalStaffAccess;
use App\Support\CourseStaffPreview;
use App\Support\StaffAdminPreview;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Просмотр админки с правами другого сотрудника: ?staff_preview=token (токен затем в сессии).
 */
final class ApplyStaffAdminPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('admin.settings.staff-preview.end')) {
            StaffAdminPreview::clearSession();

            return $next($request);
        }

        $token = StaffAdminPreview::activeToken($request);
        if ($token === null) {
            return $next($request);
        }

        CourseStaffPreview::clearSession();

        $ctx = StaffAdminPreview::resolvePreview($token);
        if ($ctx === null) {
            StaffAdminPreview::clearSession();

            return redirect()
                ->route('admin.panel')
                ->with('err', 'Ссылка просмотра админки недействительна или истекла. Откройте сотрудника снова из настроек.');
        }

        if ((int) session('learner_id', 0) !== $ctx['viewer_staff_learner_id']) {
            StaffAdminPreview::clearSession();

            return redirect()
                ->route('admin.panel')
                ->with('err', 'Просмотр админки доступен только под учётной записью сотрудника, который его открыл.');
        }

        $targetAccess = PortalStaffAccess::fromLearnerId($ctx['target_staff_learner_id']);
        if ($targetAccess === null) {
            StaffAdminPreview::clearSession();

            return redirect()
                ->route('admin.panel')
                ->with('err', 'Сотрудник не найден.');
        }

        StaffAdminPreview::persistToken($token);

        app()->instance(PortalStaffAccess::class, $targetAccess);
        $request->attributes->set('staff_admin_preview_active', true);
        $request->attributes->set('staff_admin_preview_viewer_id', $ctx['viewer_staff_learner_id']);
        $request->attributes->set('staff_admin_preview_target_id', $ctx['target_staff_learner_id']);
        $request->attributes->set('staff_admin_preview_token', $token);
        $request->attributes->set('course_admin_readonly', $targetAccess->isReadOnlyCourseContent());

        URL::defaults(StaffAdminPreview::routeQueryParams($request));
        View::share('staffAdminPreviewActive', true);
        View::share('staffAdminPreviewToken', $token);
        View::share('staffAdminPreviewTarget', Learner::query()->find($ctx['target_staff_learner_id']));
        View::share('staffAdminPreviewRoleLabel', $targetAccess->roleLabel());

        return $next($request);
    }
}
