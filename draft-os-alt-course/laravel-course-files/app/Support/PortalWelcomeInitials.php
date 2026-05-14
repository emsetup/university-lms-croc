<?php

namespace App\Support;

final class PortalWelcomeInitials
{
    public static function from(?string $displayName, string $email): string
    {
        $name = trim((string) $displayName);
        if ($name !== '') {
            $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($parts) >= 2) {
                $a = mb_substr($parts[0], 0, 1);
                $b = mb_substr($parts[1], 0, 1);

                return mb_strtoupper($a.$b, 'UTF-8');
            }
            $compact = preg_replace('/\s+/u', '', $name) ?? '';

            return mb_strtoupper(mb_substr($compact, 0, 2), 'UTF-8');
        }

        $local = explode('@', strtolower(trim($email)), 2)[0] ?? '';

        return mb_strtoupper(mb_substr($local, 0, 2), 'UTF-8');
    }
}
