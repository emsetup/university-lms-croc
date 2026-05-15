<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Learner;
use App\Models\PortalStaff;
use App\Services\PortalStaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Просмотр портала от лица обучающегося в отдельном окне (параметр ?preview=…).
 * Сессия сотрудника не меняется — в другой вкладке остаётесь собой.
 */
final class StaffImpersonation
{
    public const QUERY_PARAM = 'preview';

    private const CACHE_PREFIX = 'portal_learner_preview:';

    private const TTL_HOURS = 8;

    /** @deprecated остаток старого режима; очищаем при входе */
    public const SESSION_STAFF_ID = 'staff_impersonator_learner_id';

    public static function createPreviewToken(int $staffLearnerId, int $targetLearnerId): string
    {
        $token = bin2hex(random_bytes(16));
        Cache::put(self::CACHE_PREFIX.$token, [
            'staff_learner_id' => $staffLearnerId,
            'target_learner_id' => $targetLearnerId,
        ], now()->addHours(self::TTL_HOURS));

        return $token;
    }

    /**
     * @return array{staff_learner_id: int, target_learner_id: int}|null
     */
    public static function resolvePreview(?string $token): ?array
    {
        if ($token === null || $token === '' || ! preg_match('/^[a-f0-9]{32}$/D', $token)) {
            return null;
        }

        $data = Cache::get(self::CACHE_PREFIX.$token);
        if (! is_array($data)) {
            return null;
        }

        $staffId = (int) ($data['staff_learner_id'] ?? 0);
        $targetId = (int) ($data['target_learner_id'] ?? 0);
        if ($staffId <= 0 || $targetId <= 0) {
            return null;
        }

        $access = PortalStaffAccess::fromLearnerId($staffId);
        if ($access === null || ! $access->canImpersonateLearners()) {
            return null;
        }

        return [
            'staff_learner_id' => $staffId,
            'target_learner_id' => $targetId,
        ];
    }

    public static function previewTokenFromRequest(Request $request): ?string
    {
        $t = $request->query(self::QUERY_PARAM);

        return is_string($t) && $t !== '' ? $t : null;
    }

    public static function isPreviewRequest(Request $request): bool
    {
        return self::resolvePreview(self::previewTokenFromRequest($request)) !== null;
    }

    /**
     * @return array{staff_learner_id: int, target_learner_id: int}|null
     */
    public static function previewContext(Request $request): ?array
    {
        return self::resolvePreview(self::previewTokenFromRequest($request));
    }

    public static function assertCanImpersonate(Learner $target, int $staffLearnerId): void
    {
        if ((int) $target->id === $staffLearnerId) {
            abort(422, 'Нельзя переключиться на свою учётную запись.');
        }
        if (PortalStaff::query()->where('learner_id', (int) $target->id)->exists()) {
            abort(422, 'Нельзя просматривать портал от лица сотрудника.');
        }
    }

    public static function clearLegacySessionImpersonation(): void
    {
        if ((int) session(self::SESSION_STAFF_ID, 0) <= 0) {
            return;
        }
        $staffId = (int) session(self::SESSION_STAFF_ID);
        session([
            'learner_id' => $staffId,
        ]);
        session()->forget(self::SESSION_STAFF_ID);
    }
}
