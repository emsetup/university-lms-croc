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

    /** @return list<array{date: string, date_label: string, date_short: string, tag: string, tag_label: string, title: string, summary: string, items: list<string>, doc_url: ?string, doc_label: ?string, image_url: ?string, image_alt: ?string}> */
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

    /** @return list<array{date: string, date_label: string, date_short: string, tag: string, tag_label: string, title: string, summary: string, items: list<string>, doc_url: ?string, doc_label: ?string, image_url: ?string, image_alt: ?string}> */
    public static function forDashboard(): array
    {
        $limit = max(1, (int) config('portal_changelog.max_on_dashboard', 12));

        return array_slice(self::entries(), 0, $limit);
    }

    /** Экранирует текст и выделяет фрагменты в «ёлочках», **markdown** и `код`. */
    public static function highlightQuotedHtml(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $highlighted = preg_replace(
            '/`([^`]+)`/u',
            '<code>$1</code>',
            $escaped
        ) ?? $escaped;
        do {
            $prev = $highlighted;
            $highlighted = preg_replace(
                '/\*\*([^*]+)\*\*/u',
                '<strong>$1</strong>',
                $highlighted
            ) ?? $highlighted;
        } while ($highlighted !== $prev);
        $highlighted = preg_replace(
            '/«([^»]+)»/u',
            '<strong>«$1»</strong>',
            $highlighted
        ) ?? $highlighted;

        return $highlighted;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{date: string, date_label: string, date_short: string, tag: string, tag_label: string, title: string, summary: string, summary_html: string, items: list<string>, items_html: list<string>, doc_url: ?string, doc_label: ?string, image_url: ?string, image_alt: ?string}|null
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

        $docUrl = null;
        $docLabel = null;
        $docSlug = trim((string) ($row['doc_slug'] ?? ''));
        if ($docSlug !== '' && \Illuminate\Support\Facades\Route::has('documentation.show')) {
            $docUrl = route('documentation.show', ['slug' => $docSlug]);
            $docLabel = trim((string) ($row['doc_label'] ?? ''));
            if ($docLabel === '') {
                $docLabel = 'Документация';
            }
        }

        $imageUrl = null;
        $imageAlt = null;
        $imagePath = trim((string) ($row['image'] ?? ''));
        if ($imagePath !== '') {
            $imageUrl = asset(ltrim($imagePath, '/'));
            $imageAlt = trim((string) ($row['image_alt'] ?? $title));
            if ($imageAlt === '') {
                $imageAlt = $title;
            }
        }

        return [
            'date' => $dateStr,
            'date_label' => $dateLabel,
            'date_short' => $dateShort,
            'tag' => $tag,
            'tag_label' => self::TAG_LABELS[$tag],
            'title' => $title,
            'summary' => $summary,
            'summary_html' => self::highlightQuotedHtml($summary),
            'items' => $items,
            'items_html' => array_map(
                static fn (string $item): string => self::highlightQuotedHtml($item),
                $items
            ),
            'doc_url' => $docUrl,
            'doc_label' => $docLabel,
            'image_url' => $imageUrl,
            'image_alt' => $imageAlt,
        ];
    }
}
