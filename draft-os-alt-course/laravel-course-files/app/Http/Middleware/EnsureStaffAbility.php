<?php

namespace App\Http\Middleware;

use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureStaffAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $g = app(PortalStaffAccess::class);
        match ($ability) {
            'manage_staff' => $g->assertCanManageStaff(),
            'view_portal_learners' => $g->assertCanViewPortalLearners(),
            'course_catalog_create' => $g->assertCanCreateCourses(),
            default => abort(500, 'Неизвестная проверка прав: '.$ability),
        };

        return $next($request);
    }
}
