<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Статическая лента обновлений портала (config/portal_changelog.php).
 */
final class PortalChangelog
{
    public const TAG_LABELS = [
        'feature' => 'Новинка',
        'improvement' => 'Улучшение',
        'fix' => 'Исправление',
        'docs' => 'Документация',
    ];

    /** @return list<array{date: string, date_label: string, date_short: string, tag: string, tag_label: string, title: string, summary: string, items: list<string>}> */
    public static function entries(): array
    {
        $raw = config('portal_changelog.entries', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized = self::normalizeRow($row);
            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }

        usort($out, static function (array $a, array $b): int {
            return strcmp($b['date'], $a['date']);
        });

        return $out;
    }

    /** @return list<array{date: string, date_label: string, date_short: string, tag: string, tag_label: string, title: string, summary: string, items: list<string>}> */
    public static function forDashboard(): array
    {
        $limit = max(1, (int) config('portal_changelog.max_on_dashboard', 12));

        return array_slice(self::entries(), 0, $limit);
    }

    /** Экранирует текст и выделяет фрагменты в «ёлочках» жирным. */
    public static function highlightQuotedHtml(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $highlighted = preg_replace(
            '/«([^»]+)»/u',
            '<strong>«$1»</strong>',
            $escaped
        );

        return $highlighted ?? $escaped;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{date: string, date_label: string, date_short: string, tag: string, tag_label: string, title: string, summary: string, items: list<string>}|null
     */
    private static function normalizeRow(array $row): ?array
    {
        $dateStr = trim((string) ($row['date'] ?? ''));
        if ($dateStr === '' || ! Carbon::hasFormat($dateStr, 'Y-m-d')) {
            return null;
        }

        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $items = [];
        if (isset($row['items']) && is_array($row['items'])) {
            foreach ($row['items'] as $item) {
                $t = trim((string) $item);
                if ($t !== '') {
                    $items[] = $t;
                }
            }
        }

        $tag = (string) ($row['tag'] ?? 'feature');
        if (! isset(self::TAG_LABELS[$tag])) {
            $tag = 'feature';
        }

        $carbon = Carbon::createFromFormat('Y-m-d', $dateStr)->locale('ru');
        $dateLabel = $carbon->isoFormat('D MMMM YYYY');
        $dateShort = $carbon->translatedFormat('j M');

        $summary = $title;
        if ($items !== []) {
            $summary .= ' — '.implode(' ', $items);
        }

        return [
            'date' => $dateStr,
            'date_label' => $dateLabel,
            'date_short' => $dateShort,
            'tag' => $tag,
            'tag_label' => self::TAG_LABELS[$tag],
            'title' => $title,
            'summary' => $summary,
            'items' => $items,
        ];
    }
}
