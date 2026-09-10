<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AdMailGroup;
use App\Models\Course;
use App\Models\PortalMailLog;
use App\Services\Mail\PortalMailNotifier;
use App\Services\PortalStaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class AdminAdMailGroupsController extends Controller
{
    public function search(Request $request, Course $adminCourse): JsonResponse
    {
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $adminCourse->id);

        if (! Schema::hasTable('ad_mail_groups')) {
            return response()->json(['ok' => true, 'items' => [], 'message' => 'Каталог групп ещё не создан.']);
        }

        $q = trim((string) $request->query('q', ''));
        $browse = filter_var($request->query('browse', false), FILTER_VALIDATE_BOOL);

        $query = AdMailGroup::query()->active()->orderBy('display_name')->orderBy('mail');

        if ($browse || $q === '') {
            $items = $query->limit(40)->get(['id', 'mail', 'display_name', 'cn']);
        } elseif (mb_strlen($q) < 2) {
            return response()->json(['ok' => true, 'items' => []]);
        } else {
            $items = $query->search($q)->limit(30)->get(['id', 'mail', 'display_name', 'cn']);
        }

        $payload = $items
            ->map(static function (AdMailGroup $g) {
                return [
                    'id' => (int) $g->id,
                    'mail' => (string) $g->mail,
                    'name' => $g->label(),
                ];
            })
            ->values()
            ->all();

        return response()->json(['ok' => true, 'items' => $payload]);
    }

    public function notify(Request $request, Course $adminCourse): JsonResponse
    {
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $adminCourse->id);

        if (! Schema::hasTable('ad_mail_groups')) {
            return response()->json(['ok' => false, 'message' => 'Каталог групп рассылок пуст.'], 422);
        }

        $data = $request->validate([
            'group_ids' => ['required', 'array', 'min:1', 'max:50'],
            'group_ids.*' => ['integer', 'min:1'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['group_ids'])));
        $groups = AdMailGroup::query()
            ->active()
            ->whereIn('id', $ids)
            ->get();

        if ($groups->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Группы не найдены.'], 422);
        }

        $notifier = app(PortalMailNotifier::class);
        $sent = 0;
        $failed = 0;
        $results = [];
        $batchId = (string) \Illuminate\Support\Str::uuid();
        $batchRecipients = $groups->map(static fn (AdMailGroup $g) => (string) $g->mail)->values()->all();
        $batchNames = $groups->map(static fn (AdMailGroup $g) => $g->label())->values()->all();

        foreach ($groups as $group) {
            $log = $notifier->notifyMailingGroupCourse(
                $adminCourse,
                (string) $group->mail,
                $group->label(),
                null,
                [
                    'group_id' => (int) $group->id,
                    'batch_id' => $batchId,
                    'batch_size' => $groups->count(),
                    'batch_recipients' => $batchRecipients,
                    'batch_group_names' => $batchNames,
                ],
            );
            $ok = $log !== null && $log->status === PortalMailLog::STATUS_SENT;
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
            $results[] = [
                'id' => (int) $group->id,
                'mail' => (string) $group->mail,
                'name' => $group->label(),
                'status' => $ok ? 'sent' : ($log?->status ?? 'failed'),
                'mail_log_id' => $log?->id,
            ];
        }

        return response()->json([
            'ok' => true,
            'sent' => $sent,
            'failed' => $failed,
            'batch_id' => $batchId,
            'results' => $results,
            'message' => 'Отправлено: '.$sent.($failed > 0 ? ', ошибок: '.$failed : ''),
        ]);
    }
}
