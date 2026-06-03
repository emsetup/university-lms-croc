<?php

namespace App\Http\Controllers;

use App\Models\PortalIncidentLog;
use App\Services\PortalIncidentFeedService;
use App\Services\PortalIncidentLogger;
use App\Services\PortalServerStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminIncidentLogsController extends Controller
{
    public function index(PortalServerStatsService $stats, PortalIncidentFeedService $feed): View
    {
        return view('admin.incident-logs', [
            'serverStats' => $stats->snapshot(),
            'incidentFeedUrl' => route('admin.incidents.feed'),
            'sourceLabels' => PortalIncidentFeedService::SOURCE_LABELS,
            'emailSuggestions' => $feed->recentEmailSuggestions(),
        ]);
    }

    public function feed(Request $request, PortalIncidentFeedService $feed): JsonResponse
    {
        return response()->json($feed->feed($request));
    }

    public function show(PortalIncidentLog $incident, PortalIncidentFeedService $feed): JsonResponse
    {
        return response()->json($feed->detail($incident));
    }

    public function storeClient(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'stack' => ['nullable', 'string', 'max:12000'],
            'url' => ['nullable', 'string', 'max:500'],
            'filename' => ['nullable', 'string', 'max:500'],
            'line' => ['nullable', 'integer', 'min:0'],
            'column' => ['nullable', 'integer', 'min:0'],
        ]);

        PortalIncidentLogger::recordClient($request, $data);

        return response()->json(['ok' => true]);
    }
}
