<?php

namespace App\Http\Middleware;

use App\Models\CourseSection;
use App\Services\PortalStaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Проверка права редактирования раздела курса (route param section). */
final class EnsureSectionEditAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $section = $request->route('section');
        $sectionId = $section instanceof CourseSection ? (int) $section->id : (int) $section;
        if ($sectionId > 0) {
            app(PortalStaffAccess::class)->assertCanEditSection($sectionId);
        }

        return $next($request);
    }
}
