<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\PortalStaff;
use App\Services\PortalMaintenance;
use App\Services\PortalStaffAccess;
use App\Support\LearnerDisplay;
use App\Support\StaffAdminPreview;
use App\Support\StaffImpersonation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminSettingsController extends Controller
{
    public function show(): View
    {
        $viewer = $this->viewerGate();
        abort_unless(
            $viewer !== null && ($viewer->canManagePortalSettings() || $viewer->canImpersonateLearners() || $viewer->canPreviewStaffAdmin()),
            403
        );

        return view('admin.settings', [
            'canMaintenance' => $viewer->canManagePortalSettings(),
            'canImpersonate' => $viewer->canImpersonateLearners(),
            'canPreviewStaffAdmin' => $viewer->canPreviewStaffAdmin(),
            'maintenanceEnabled' => PortalMaintenance::isEnabled(),
            'maintenanceSource' => PortalMaintenance::effectiveSource(),
            'maintenanceEnvDefault' => PortalMaintenance::envDefaultEnabled(),
        ]);
    }

    public function updateMaintenance(Request $request): RedirectResponse
    {
        $gate = $this->viewerGate();
        abort_unless($gate !== null && $gate->canManagePortalSettings(), 403);

        $enabled = $request->boolean('enabled');
        PortalMaintenance::setEnabled($enabled);

        $msg = $enabled
            ? 'Заглушка обновления включена для обучающихся.'
            : 'Заглушка обновления выключена — обучающиеся видят портал.';

        return redirect()->route('admin.settings')->with('ok', $msg);
    }

    public function resetMaintenance(): RedirectResponse
    {
        $gate = $this->viewerGate();
        abort_unless($gate !== null && $gate->canManagePortalSettings(), 403);

        PortalMaintenance::clearRuntimeOverride();
        $def = PortalMaintenance::envDefaultEnabled();

        return redirect()->route('admin.settings')->with(
            'ok',
            'Сброшено переопределение. Используется значение из .env (PORTAL_USER_MAINTENANCE='.($def ? 'true' : 'false').').'
        );
    }

    /** Открывается в новой вкладке (target="_blank"); сессия сотрудника не меняется. */
    public function impersonate(Request $request): RedirectResponse
    {
        $gate = $this->viewerGate();
        abort_unless($gate !== null && $gate->canImpersonateLearners(), 403);

        $request->validate([
            'learner_id' => ['required', 'integer', 'min:1'],
        ]);

        $staffId = (int) session('learner_id', 0);
        abort_if($staffId <= 0, 403);

        $target = Learner::query()->findOrFail((int) $request->input('learner_id'));
        StaffImpersonation::assertCanImpersonate($target, $staffId);

        $token = StaffImpersonation::createPreviewToken($staffId, (int) $target->id);

        return redirect()->route('portal', [StaffImpersonation::QUERY_PARAM => $token]);
    }

    public function learnerSearch(Request $request): JsonResponse
    {
        $gate = $this->viewerGate();
        abort_unless($gate !== null && $gate->canImpersonateLearners(), 403);

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $staffLearnerIds = PortalStaff::query()->pluck('learner_id')->all();
        $like = '%'.addcslashes($q, '%_\\').'%';

        $rows = Learner::query()
            ->where(function ($w) use ($like) {
                $w->where('email', 'like', $like)
                    ->orWhere('sso_display_name', 'like', $like);
            })
            ->when($staffLearnerIds !== [], fn ($w) => $w->whereNotIn('id', $staffLearnerIds))
            ->orderBy('email')
            ->limit(20)
            ->get(['id', 'email', 'sso_display_name']);

        $items = [];
        foreach ($rows as $learner) {
            $name = LearnerDisplay::portalDisplayName($learner);
            $items[] = [
                'id' => (int) $learner->id,
                'email' => (string) $learner->email,
                'name' => $name,
                'label' => $name !== '' ? $name.' · '.$learner->email : (string) $learner->email,
            ];
        }

        return response()->json(['items' => $items]);
    }

    /** Открывается в новой вкладке; сессия не меняется, права — как у выбранного сотрудника. */
    public function staffPreview(Request $request): RedirectResponse
    {
        $gate = $this->viewerGate();
        abort_unless($gate !== null && $gate->canPreviewStaffAdmin(), 403);

        $request->validate([
            'staff_learner_id' => ['required', 'integer', 'min:1'],
        ]);

        $viewerId = (int) session('learner_id', 0);
        abort_if($viewerId <= 0, 403);

        $targetId = (int) $request->input('staff_learner_id');
        StaffAdminPreview::assertCanPreview($targetId, $viewerId);

        $token = StaffAdminPreview::createPreviewToken($viewerId, $targetId);

        return redirect()->route('admin.panel', [StaffAdminPreview::QUERY_PARAM => $token]);
    }

    public function endStaffPreview(): RedirectResponse
    {
        StaffAdminPreview::clearSession();

        return redirect()
            ->route('admin.settings', [], 302)
            ->with('ok', 'Просмотр админки от лица сотрудника завершён.');
    }

    public function staffSearch(Request $request): JsonResponse
    {
        $gate = $this->viewerGate();
        abort_unless($gate !== null && $gate->canPreviewStaffAdmin(), 403);

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $like = '%'.addcslashes($q, '%_\\').'%';

        $rows = PortalStaff::query()
            ->with('learner')
            ->whereHas('learner', function ($w) use ($like) {
                $w->where('email', 'like', $like)
                    ->orWhere('sso_display_name', 'like', $like);
            })
            ->orderBy('role')
            ->limit(20)
            ->get();

        $items = [];
        foreach ($rows as $staff) {
            $learner = $staff->learner;
            if ($learner === null) {
                continue;
            }
            $access = new PortalStaffAccess($staff);
            $name = LearnerDisplay::portalDisplayName($learner);
            $role = $access->roleLabel();
            $items[] = [
                'id' => (int) $learner->id,
                'email' => (string) $learner->email,
                'name' => $name,
                'role' => $role,
                'label' => ($name !== '' ? $name.' · ' : '').$learner->email.' — '.$role,
            ];
        }

        return response()->json(['items' => $items]);
    }

    private function viewerGate(): ?PortalStaffAccess
    {
        return PortalStaffAccess::fromLearnerId((int) session('learner_id', 0));
    }
}
