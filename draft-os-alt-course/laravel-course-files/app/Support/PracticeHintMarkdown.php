<?php

namespace App\Support;

use App\Models\PracticeSession;

/**
 * Скрывает markdown-цитаты с «Подсказка:» до тех пор, пока не будет «разрешён» показ подсказок.
 *
 * По умолчанию для лаборатории: подсказки показываются только после первой автопроверки,
 * в которой не набран полный балл (или явно не пройдена проверка при отсутствии поля score).
 */
final class PracticeHintMarkdown
{
    public static function shouldShowBlockquoteHints(?PracticeSession $session): bool
    {
        if ($session === null || $session->last_check_at === null) {
            return false;
        }

        $max = (int) ($session->last_check_max_score ?? 100);
        if ($session->last_check_score !== null) {
            return (int) $session->last_check_score < $max;
        }

        return ! ($session->last_check_passed ?? false);
    }

    public static function stripBlockquoteHintsUnlessVisible(string $markdown, bool $hintsVisible): string
    {
        if ($hintsVisible || $markdown === '') {
            return $markdown;
        }

        $lines = preg_split('/\R/u', $markdown) ?: [];
        $out = [];
        $skippingHintBlock = false;

        foreach ($lines as $line) {
            if (preg_match('/^\h*> \*\*Подсказка:\*\*/u', $line)) {
                $skippingHintBlock = true;

                continue;
            }
            if ($skippingHintBlock) {
                if (preg_match('/^\h*>/u', $line)) {
                    continue;
                }
                $skippingHintBlock = false;
            }
            $out[] = $line;
        }

        return preg_replace("/\n{3,}/u", "\n\n", implode("\n", $out));
    }

    public static function containsBlockquoteHints(string $markdown): bool
    {
        return (bool) preg_match('/^\h*> \*\*Подсказка:\*\*/mu', $markdown);
    }
}
