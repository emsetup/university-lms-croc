<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Learner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Фиксация успешного входа на портал (SSO или email) в learners.last_login_at.
 */
final class LearnerPortalLoginPersistence
{
    private const SESSION_RECORDED = 'portal_login_recorded_at';

    public static function recordLogin(Learner $learner): void
    {
        if (! Schema::hasColumn($learner->getTable(), 'last_login_at')) {
            return;
        }
        $learner->last_login_at = now();
        $learner->saveQuietly();
    }

    /** Один раз за сессию — для уже открытой сессии до появления поля или без повторного SSO. */
    public static function recordLoginForSession(Request $request, Learner $learner): void
    {
        if ($request->session()->has(self::SESSION_RECORDED)) {
            return;
        }
        self::recordLogin($learner);
        $request->session()->put(self::SESSION_RECORDED, now()->toIso8601String());
    }

    public static function markSessionRecorded(Request $request): void
    {
        $request->session()->put(self::SESSION_RECORDED, now()->toIso8601String());
    }
}
