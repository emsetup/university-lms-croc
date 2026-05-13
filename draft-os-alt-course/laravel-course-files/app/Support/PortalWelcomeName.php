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
     * Имя для приветствия / подписи: из OIDC (сессия), иначе оформленная часть почты; без «имени» = null.
     */
    public static function forLearner(Learner $learner): ?string
    {
        $fromOidc = trim((string) session('learner_name', ''));
        $email = trim((string) $learner->email);
        $localRaw = strtolower((string) (explode('@', $email, 2)[0] ?? ''));

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
