<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Markdown для learner-контента (теория, практика): GFM + callout-блоки из blockquote.
 */
final class CourseContentMarkdown
{
    public static function toHtml(string $markdown): string
    {
        $markdown = str_replace("\0", '', $markdown);
        if ($markdown === '') {
            return '';
        }

        $html = (string) Str::markdown($markdown);

        return self::enrichCallouts($html);
    }

    public static function enrichCallouts(string $html): string
    {
        return (string) preg_replace_callback(
            '/<blockquote>(.*?)<\/blockquote>/s',
            static fn (array $m): string => self::convertBlockquote((string) $m[1]),
            $html
        );
    }

    private static function convertBlockquote(string $inner): string
    {
        $inner = trim($inner);
        if ($inner === '') {
            return '';
        }

        if (! preg_match_all('/<p>(.*?)<\/p>/s', $inner, $paragraphs, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return self::renderCallout('Примечание', $inner, 'note');
        }

        /** @var list<array{label: string, body: string, type: string}> $callouts */
        $callouts = [];
        $current = null;

        foreach ($paragraphs as $paragraph) {
            $content = (string) $paragraph[1][0];
            $labelInfo = self::extractLabel($content);

            if ($labelInfo !== null) {
                if ($current !== null) {
                    $callouts[] = $current;
                }
                $current = [
                    'label' => $labelInfo['label'],
                    'body' => $labelInfo['body'],
                    'type' => self::calloutTypeForLabel($labelInfo['label']),
                ];

                continue;
            }

            if ($current !== null) {
                $current['body'] .= '<p>'.$content.'</p>';
            } else {
                return self::renderCallout('Примечание', $inner, 'note');
            }
        }

        if ($current !== null) {
            $callouts[] = $current;
        }

        if ($callouts === []) {
            return self::renderCallout('Примечание', $inner, 'note');
        }

        $lastParagraph = end($paragraphs);
        $lastEnd = (int) $lastParagraph[0][1] + strlen((string) $lastParagraph[0][0]);
        $remainder = trim(substr($inner, $lastEnd));
        if ($remainder !== '') {
            $callouts[count($callouts) - 1]['body'] .= $remainder;
        }

        $html = '';
        foreach ($callouts as $callout) {
            $html .= self::renderCallout($callout['label'], $callout['body'], $callout['type']);
        }

        return $html;
    }

    /**
     * @return array{label: string, body: string}|null
     */
    private static function extractLabel(string $html): ?array
    {
        $html = trim($html);
        if (! preg_match(
            '/^(?:[\x{00A0}\s\p{Extended_Pictographic}\x{FE0F}\x{200D}]*)*<strong>([^<]+)<\/strong>\s*:?\s*(.*)$/su',
            $html,
            $m
        )) {
            return null;
        }

        $label = trim(strip_tags($m[1]));
        $rest = trim($m[2]);

        return [
            'label' => $label,
            'body' => $rest !== '' ? '<p>'.$rest.'</p>' : '',
        ];
    }

    private static function renderCallout(string $label, string $body, string $type): string
    {
        $body = trim($body);
        if ($body === '') {
            $body = '<p></p>';
        }

        return '<aside class="docs-callout docs-callout--'.e($type).'">'
            .'<span class="docs-callout__label">'.e($label).'</span>'
            .'<div class="docs-callout__body">'.$body.'</div></aside>';
    }

    private static function calloutTypeForLabel(string $label): string
    {
        $normalized = mb_strtolower($label);

        return match (true) {
            str_starts_with($normalized, 'подсказ'),
            str_starts_with($normalized, 'практическое правило'),
            str_starts_with($normalized, 'как читать'),
            str_starts_with($normalized, 'как правильно') => 'tip',
            str_starts_with($normalized, 'важн'),
            str_starts_with($normalized, 'критич'),
            str_starts_with($normalized, 'границ'),
            str_contains($normalized, '⚠') => 'warn',
            str_starts_with($normalized, 'зачем'),
            str_starts_with($normalized, 'идея модуля'),
            str_starts_with($normalized, 'почему это важно'),
            str_starts_with($normalized, 'для кого'),
            str_starts_with($normalized, 'о чём') => 'goal',
            default => 'note',
        };
    }
}
