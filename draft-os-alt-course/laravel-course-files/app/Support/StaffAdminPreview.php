<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PortalStaff;
use App\Services\PortalStaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Просмотр админки с правами другого сотрудника (?staff_preview=…).
 * Сессия не меняется; изменения в этом режиме запрещены.
 */
final class StaffAdminPreview
{
    public const QUERY_PARAM = 'staff_preview';

    public const SESSION_TOKEN = 'staff_admin_preview_token';

    private const CACHE_PREFIX = 'portal_staff_admin_preview:';

    private const TTL_HOURS = 8;

    public static function createPreviewToken(int $viewerStaffLearnerId, int $targetStaffLearnerId): string
    {
        $token = bin2hex(random_bytes(16));
        Cache::put(self::CACHE_PREFIX.$token, [
            'viewer_staff_learner_id' => $viewerStaffLearnerId,
            'target_staff_learner_id' => $targetStaffLearnerId,
        ], now()->addHours(self::TTL_HOURS));

        return $token;
    }

    /**
     * @return array{viewer_staff_learner_id: int, target_staff_learner_id: int}|null
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

        $viewerId = (int) ($data['viewer_staff_learner_id'] ?? 0);
        $targetId = (int) ($data['target_staff_learner_id'] ?? 0);
        if ($viewerId <= 0 || $targetId <= 0) {
            return null;
        }

        $viewerAccess = PortalStaffAccess::fromLearnerId($viewerId);
        if ($viewerAccess === null || ! $viewerAccess->canPreviewStaffAdmin()) {
            return null;
        }

        if (PortalStaffAccess::fromLearnerId($targetId) === null) {
            return null;
        }

        return [
            'viewer_staff_learner_id' => $viewerId,
            'target_staff_learner_id' => $targetId,
        ];
    }

    public static function previewTokenFromRequest(Request $request): ?string
    {
        $t = $request->query(self::QUERY_PARAM);

        return is_string($t) && $t !== '' ? $t : null;
    }

    /** Токен из query или сессии (чтобы не терять просмотр при переходах без ?staff_preview=). */
    public static function activeToken(Request $request): ?string
    {
        $fromQuery = self::previewTokenFromRequest($request);
        if ($fromQuery !== null) {
            return $fromQuery;
        }

        $fromSession = session(self::SESSION_TOKEN);

        return is_string($fromSession) && $fromSession !== '' ? $fromSession : null;
    }

    public static function persistToken(string $token): void
    {
        session([self::SESSION_TOKEN => $token]);
    }

    public static function clearSession(): void
    {
        session()->forget(self::SESSION_TOKEN);
    }

    public static function isPreviewRequest(Request $request): bool
    {
        return self::resolvePreview(self::activeToken($request)) !== null;
    }

    /**
     * @return array<string, string>
     */
    public static function routeQueryParams(Request $request): array
    {
        $token = self::activeToken($request);

        return $token !== null ? [self::QUERY_PARAM => $token] : [];
    }

    public static function assertCanPreview(int $targetStaffLearnerId, int $viewerStaffLearnerId): void
    {
        if ($targetStaffLearnerId === $viewerStaffLearnerId) {
            abort(422, 'Нельзя открыть просмотр от своей учётной записи.');
        }
        if (PortalStaffAccess::fromLearnerId($targetStaffLearnerId) === null) {
            abort(422, 'Выбранный пользователь не является сотрудником портала.');
        }
        $viewer = PortalStaffAccess::fromLearnerId($viewerStaffLearnerId);
        abort_unless($viewer !== null && $viewer->canPreviewStaffAdmin(), 403);
    }
}
