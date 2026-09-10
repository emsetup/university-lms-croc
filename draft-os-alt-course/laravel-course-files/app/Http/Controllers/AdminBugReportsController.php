<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\PortalBugReport;
use App\Services\PortalBugReportFeedService;
use App\Services\PortalBugReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AdminBugReportsController extends Controller
{
    public function index(PortalBugReportFeedService $feed): View
    {
        $learner = $this->currentLearner();
        $restrict = $this->restrictCourseIds($learner);

        return view('admin.bug-reports', [
            'bugStats' => $feed->stats($restrict),
            'bugFeedUrl' => route('admin.bugs.feed'),
            'typeLabels' => PortalBugReportFeedService::TYPE_LABELS,
            'scopeLabels' => PortalBugReportFeedService::SCOPE_LABELS,
            'statusLabels' => PortalBugReportFeedService::STATUS_LABELS,
            'emailSuggestions' => $feed->recentEmailSuggestions(),
            'bugInboxScoped' => $restrict !== null,
        ]);
    }

    public function feed(Request $request, PortalBugReportFeedService $feed): JsonResponse
    {
        $learner = $this->currentLearner();
        $restrict = $this->restrictCourseIds($learner);

        return response()->json($feed->feed($request, $restrict));
    }

    public function show(PortalBugReport $bug, PortalBugReportFeedService $feed): JsonResponse
    {
        $this->assertCanView($bug);

        return response()->json($feed->detail($bug));
    }

    public function updateStatus(Request $request, PortalBugReport $bug): JsonResponse
    {
        $this->assertCanView($bug);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', PortalBugReport::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:4000'],
        ]);

        $bug->status = $data['status'];
        if (array_key_exists('admin_note', $data)) {
            $note = trim((string) ($data['admin_note'] ?? ''));
            $bug->admin_note = $note !== '' ? $note : null;
        }

        if (in_array($bug->status, [PortalBugReport::STATUS_DONE, PortalBugReport::STATUS_DISMISSED], true)) {
            $bug->resolved_at = $bug->resolved_at ?? now();
        } else {
            $bug->resolved_at = null;
        }
        $bug->save();

        return response()->json([
            'ok' => true,
            'status' => (string) $bug->status,
            'status_label' => PortalBugReport::statusLabel((string) $bug->status),
            'admin_note' => $bug->admin_note,
        ]);
    }

    public function screenshot(
        PortalBugReport $bug,
        int $index,
        PortalBugReportService $service,
    ): BinaryFileResponse {
        $this->assertCanView($bug);

        $shots = (array) ($bug->screenshots ?? []);
        abort_unless(isset($shots[$index]) && is_array($shots[$index]), 404);

        $path = (string) ($shots[$index]['path'] ?? '');
        $abs = $service->absolutePath($path);
        abort_unless($abs !== null && is_file($abs), 404);

        $mime = (string) ($shots[$index]['mime'] ?? 'image/png');
        $name = (string) ($shots[$index]['name'] ?? ('screenshot-'.$index));

        return ResponseFacade::file($abs, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function currentLearner(): ?Learner
    {
        $id = (int) session('learner_id', 0);

        return $id > 0 ? Learner::query()->find($id) : null;
    }

    /**
     * null = глобальный инбокс; list = только свои курсы.
     *
     * @return list<int>|null
     */
    private function restrictCourseIds(?Learner $learner): ?array
    {
        if (PortalBugReportService::isGlobalInboxLearner($learner)) {
            return null;
        }

        return PortalBugReportService::courseIdsCreatedByLearner($learner);
    }

    private function assertCanView(PortalBugReport $bug): void
    {
        abort_unless(
            PortalBugReportService::learnerCanViewReport($this->currentLearner(), $bug),
            404,
        );
    }
}
