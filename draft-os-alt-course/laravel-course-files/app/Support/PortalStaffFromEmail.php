<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Learner;
use App\Models\PortalStaff;

/**
 * Найти или создать сотрудника портала по корпоративному email (для групп и приглашений).
 */
final class PortalStaffFromEmail
{
    /**
     * @return array{staff: PortalStaff, created: bool}
     */
    public static function findOrCreateStaff(string $email, string $role): array
    {
        $email = self::normalizeEmail($email);
        if (! in_array($role, PortalStaff::ROLES, true)) {
            $role = PortalStaff::ROLE_COURSE_TESTER;
        }
        $learner = Learner::query()->firstOrCreate(['email' => $email]);
        $existing = PortalStaff::query()->where('learner_id', $learner->id)->first();
        if ($existing !== null) {
            return ['staff' => $existing, 'created' => false];
        }

        $staff = PortalStaff::query()->create([
            'learner_id' => $learner->id,
            'role' => $role,
        ]);

        return ['staff' => $staff, 'created' => true];
    }

    /**
     * @return list<string>
     */
    public static function parseLines(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $parts = preg_split('/[\n,;]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $e = self::normalizeEmail($p);
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[] = $e;
            }
        }

        return array_values(array_unique($out));
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function isCorporateEmail(string $email, ?string $domain = null): bool
    {
        $domain = strtolower(trim($domain ?? (string) config('course.email_domain', '')));
        if ($domain === '') {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }
        $d = preg_quote($domain, '/');

        return preg_match('/^[^@\s]+@'.$d.'$/i', $email) === 1;
    }
}
