<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Заглушка «Портал обновляется» для обучающихся: значение по умолчанию из .env,
 * переопределение в runtime — через кэш (админка → Настройки).
 */
final class PortalMaintenance
{
    private const CACHE_KEY = 'portal:maintenance:enabled';

    public static function envDefaultEnabled(): bool
    {
        return (bool) config('course.portal_user_maintenance', false);
    }

    public static function isEnabled(): bool
    {
        $override = Cache::get(self::CACHE_KEY);
        if ($override !== null) {
            return (bool) $override;
        }

        return self::envDefaultEnabled();
    }

    public static function hasRuntimeOverride(): bool
    {
        return Cache::has(self::CACHE_KEY);
    }

    public static function setEnabled(bool $enabled): void
    {
        Cache::forever(self::CACHE_KEY, $enabled);
    }

    public static function clearRuntimeOverride(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return 'env'|'runtime' */
    public static function effectiveSource(): string
    {
        return self::hasRuntimeOverride() ? 'runtime' : 'env';
    }
}
