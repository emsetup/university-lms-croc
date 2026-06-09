<?php

namespace App\Support;

/**
 * Буквенные метки вариантов ответа (A, B, C…) для отчётов преподавателя.
 */
final class TeacherQuizLabels
{
    public static function letter(int $index): string
    {
        if ($index < 0) {
            return '?';
        }

        return chr(65 + ($index % 26));
    }

    public static function lettersList(int|array|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_array($value)) {
            if ($value === []) {
                return '—';
            }
            $letters = array_map(
                static fn ($idx): string => self::letter((int) $idx),
                array_values($value)
            );

            return implode(', ', $letters);
        }

        return self::letter((int) $value);
    }
}
