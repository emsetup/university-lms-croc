<?php

namespace App\Support;

final class PracticeCheckOutputParser
{
    /**
     * Разбор хвоста вывода check.sh с маркером ===PRACTICE_RESULT_JSON===.
     *
     * @return array{score: ?int, max: int, hints: list<string>, has_json: bool}
     */
    public static function parse(string $stdout): array
    {
        $score = null;
        $max = 100;
        $hints = [];
        $hasJson = false;

        if (preg_match('/===PRACTICE_RESULT_JSON===\s*\R*(.+)\s*\z/su', $stdout, $m)) {
            $decoded = json_decode(trim($m[1]), true);
            if (is_array($decoded)) {
                $hasJson = true;
                $score = isset($decoded['score']) ? (int) $decoded['score'] : 0;
                $max = isset($decoded['max']) ? max(1, (int) $decoded['max']) : 100;
            }
        }

        if (preg_match_all('/^HINT: (.+)$/m', $stdout, $mm)) {
            foreach ($mm[1] as $line) {
                $hints[] = $line;
            }
        }

        if (preg_match_all('/^FAIL: (.+)$/m', $stdout, $fm)) {
            foreach ($fm[1] as $line) {
                $hints[] = $line;
            }
        }

        if (preg_match_all('/^TASK\d+:FAIL:(.+)$/m', $stdout, $tf)) {
            foreach ($tf[1] as $line) {
                $hints[] = $line;
            }
        }

        if (! $hasJson && $score === null && preg_match('/^RESULT:(\d+):(\d+)\s*$/m', $stdout, $rm)) {
            $ok = (int) $rm[1];
            $bad = (int) $rm[2];
            $tot = max(1, $ok + $bad);
            $score = (int) round(100 * $ok / $tot);
            $max = 100;
        }

        return [
            'score' => $score,
            'max' => $max,
            'hints' => $hints,
            'has_json' => $hasJson,
        ];
    }
}
