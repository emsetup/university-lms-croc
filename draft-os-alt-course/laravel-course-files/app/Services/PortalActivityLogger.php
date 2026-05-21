<?php

namespace App\Services;

use App\Models\PortalActivityEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class PortalActivityLogger
{
    private const ADMIN_THROTTLE_MINUTES = 15;

    public static function recordAdminAccess(int $learnerId, string $path): void
    {
        if ($learnerId <= 0 || ! Schema::hasTable('portal_activity_events')) {
            return;
        }

        $cacheKey = 'portal_activity:admin:'.$learnerId;
        if (! Cache::add($cacheKey, true, now()->addMinutes(self::ADMIN_THROTTLE_MINUTES))) {
            return;
        }

        PortalActivityEvent::query()->create([
            'learner_id' => $learnerId,
            'type' => PortalActivityEvent::TYPE_ADMIN_PANEL,
            'path' => mb_substr($path, 0, 255),
            'occurred_at' => now(),
        ]);
    }
}
