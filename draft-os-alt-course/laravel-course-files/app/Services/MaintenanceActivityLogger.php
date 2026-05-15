<?php

namespace App\Services;

use App\Models\PortalActivityEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class MaintenanceActivityLogger
{
    private const THROTTLE_MINUTES = 30;

    public static function recordBlockedAccess(int $learnerId, string $path): void
    {
        if ($learnerId <= 0 || ! Schema::hasTable('portal_activity_events')) {
            return;
        }

        $cacheKey = 'maintenance_blocked:'.$learnerId;
        if (! Cache::add($cacheKey, true, now()->addMinutes(self::THROTTLE_MINUTES))) {
            return;
        }

        PortalActivityEvent::query()->create([
            'learner_id' => $learnerId,
            'type' => PortalActivityEvent::TYPE_MAINTENANCE_BLOCKED,
            'path' => mb_substr($path, 0, 255),
            'occurred_at' => now(),
        ]);
    }
}
