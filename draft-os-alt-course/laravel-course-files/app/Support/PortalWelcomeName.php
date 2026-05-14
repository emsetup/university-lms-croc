<?php

namespace App\Support;

use App\Models\Learner;
use Illuminate\Support\Str;

/**
 * Отображаемое имя слушателя для портала и ЛК: OIDC (сессия), иначе оформленная часть почты.
 * Совпадает с логикой приветствия на главной портала.
 */
final class PortalWelcomeName
{
    /**
     * ФИО из OIDC (сессия learner_name или userinfo/id_token в probe), без БД и без «красивой» части email.
     * Используется для записи в learners.sso_display_name.
     */
    public static function ssoMeaningfulNameFromSession(Learner $learner): ?string
    {
        $email = trim((string) $learner->email);
        $localRaw = strtolower((string) (explode('@', $email, 2)[0] ?? ''));

        $fromOidc = trim((string) session('learner_name', ''));
        if ($fromOidc !== '' && self::sessionNameIsDisplayable($fromOidc, $localRaw)) {
            return $fromOidc;
        }

        $probe = session('oidc_identity_probe_claims');
        if (is_array($probe)) {
            $fromProbe = trim(OidcIdentityClaims::displayName($probe));
            if ($fromProbe !== '' && self::sessionNameIsDisplayable($fromProbe, $localRaw)) {
                return $fromProbe;
            }
        }

        return null;
    }

    /**
     * Имя для приветствия / подписи: из OIDC (сессия), иначе оформленная часть почты; без «имени» = null.
     */
    public static function forLearner(Learner $learner): ?string
    {
        $email = trim((string) $learner->email);
        $localRaw = strtolower((string) (explode('@', $email, 2)[0] ?? ''));

        $fromSso = self::ssoMeaningfulNameFromSession($learner);
        if ($fromSso !== null) {
            return $fromSso;
        }

        $fromDb = trim((string) ($learner->sso_display_name ?? ''));
        if ($fromDb !== '' && self::sessionNameIsDisplayable($fromDb, $localRaw)) {
            return $fromDb;
        }

        $local = explode('@', $email, 2)[0] ?? '';
        $local = trim(str_replace(['.', '_', '-'], ' ', $local));
        if ($local === '') {
            return $email !== '' ? $email : 'участник';
        }

        $pretty = Str::title($local);
        if (! str_contains($pretty, ' ') && strcasecmp($pretty, $localRaw) === 0) {
            return null;
        }

        return $pretty;
    }

    private static function sessionNameIsDisplayable(string $name, string $emailLocalPartLower): bool
    {
        if ($emailLocalPartLower !== '' && strcasecmp($name, $emailLocalPartLower) === 0) {
            return false;
        }
        if (str_contains($name, ' ') || preg_match('/\p{Cyrillic}/u', $name)) {
            return true;
        }

        return strcasecmp($name, $emailLocalPartLower) !== 0;
    }
}
