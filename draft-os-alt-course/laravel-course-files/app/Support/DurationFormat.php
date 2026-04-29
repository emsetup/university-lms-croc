<?php

namespace App\Support;

final class DurationFormat
{
    /** Человекочитаемая длительность из секунд (для отчётов преподавателя). */
    public static function fromSeconds(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 мин';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        $parts = [];
        if ($h > 0) {
            $parts[] = $h.' ч';
        }
        if ($m > 0 || ($h > 0 && $s > 0)) {
            $parts[] = $m.' мин';
        }
        if ($h === 0 && $m === 0) {
            $parts[] = $s.' с';
        } elseif ($s > 0 && $h === 0) {
            $parts[] = $s.' с';
        }

        return implode(' ', $parts);
    }
}
