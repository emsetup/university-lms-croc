<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PortalPlatformStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminPlatformStatsController extends Controller
{
    public function show(PortalPlatformStatsService $stats): View
    {
        session()->forget(['admin_course_id', 'admin_course_title', 'admin_course_slug']);

        return view('admin.platform-stats', [
            'stats' => $stats->snapshot(),
        ]);
    }

    public function exportPdf(PortalPlatformStatsService $stats, Request $request): View
    {
        session()->forget(['admin_course_id', 'admin_course_title', 'admin_course_slug']);

        return view('admin.platform-stats-export', [
            'stats' => $stats->snapshot(),
            'staffRoster' => $stats->staffRoster(),
            'autoDownload' => $request->boolean('auto'),
        ]);
    }
}
