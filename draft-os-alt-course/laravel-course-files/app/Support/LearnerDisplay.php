<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Learner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Отображаемое ФИО обучающегося: сохранённое из OIDC/Keycloak при входе ({@see Learner::$sso_display_name}),
 * те же claim'ы, что и на главной портала ({@see OidcIdentityClaims::displayName}).
 */
final class LearnerDisplay
{
    /**
     * Имя из БД (последний успешный вход через SSO).
     */
    public static function portalDisplayName(Learner $learner): string
    {
        return self::normalizeString($learner->sso_display_name ?? null);
    }

    /**
     * @param  list<int>  $learnerIds
     * @return array<int, string>
     */
    public static function portalDisplayNamesByLearnerIds(array $learnerIds): array
    {
        if ($learnerIds === [] || ! Schema::hasTable('learners')) {
            return [];
        }
        if (! Schema::hasColumn('learners', 'sso_display_name')) {
            return [];
        }

        $rows = DB::table('learners')
            ->whereIn('id', $learnerIds)
            ->get(['id', 'sso_display_name']);

        $out = [];
        foreach ($rows as $row) {
            $n = self::normalizeString($row->sso_display_name ?? null);
            if ($n !== '') {
                $out[(int) $row->id] = $n;
            }
        }

        return $out;
    }

    public static function initials(string $email, string $displayName = ''): string
    {
        $trimmed = trim($displayName);
        if ($trimmed !== '') {
            $parts = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($parts) && $parts !== []) {
                if (count($parts) >= 2) {
                    $a = mb_substr($parts[0], 0, 1, 'UTF-8');
                    $b = mb_substr($parts[1], 0, 1, 'UTF-8');

                    return mb_strtoupper($a.$b, 'UTF-8');
                }
                $one = $parts[0];
                $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $one) ?? '';
                if ($clean !== '') {
                    return mb_strtoupper(mb_substr($clean, 0, 2, 'UTF-8'), 'UTF-8');
                }
            }
        }

        return self::initialsFromEmail($email);
    }

    public static function initialsFromEmail(string $email): string
    {
        $local = strstr($email, '@', true) ?: $email;
        $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $local) ?? '';
        if ($clean === '') {
            return '—';
        }

        return mb_strtoupper(mb_substr($clean, 0, 2, 'UTF-8'), 'UTF-8');
    }

    private static function normalizeString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
