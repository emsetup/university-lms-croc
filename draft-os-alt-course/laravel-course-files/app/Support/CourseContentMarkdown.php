<?php

namespace App\Support;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Markdown для learner-контента (теория, практика): GFM + callout-блоки из blockquote.
 *
 * Оглавление: маркер [[toc]] / [TOC] в Markdown → nav со ссылками на ## / ###
 * (якоря id проставляются автоматически).
 */
final class CourseContentMarkdown
{
    /** Plain token so CommonMark does not strip an HTML comment. */
    private const TOC_PLACEHOLDER = '§§COURSE_CONTENT_TOC§§';

    public static function toHtml(string $markdown): string
    {
        $markdown = str_replace("\0", '', $markdown);
        if ($markdown === '') {
            return '';
        }

        $markdown = self::normalizeTypographicLists($markdown);
        $markdown = self::expandMediaPaths($markdown);
        $markdown = self::replaceTocMarkers($markdown);
        // Одиночный Enter в редакторе = soft break; по умолчанию CommonMark
        // оставляет голый \\n внутри <p>, и браузер схлопывает его в пробел.
        $html = (string) Str::markdown($markdown, [
            'renderer' => [
                'soft_break' => "<br />\n",
            ],
        ]);
        $html = self::enrichMediaFigures($html);
        $html = self::enrichCallouts($html);
        $html = self::enrichCenteredHeadings($html);
        $html = self::addHeadingIds($html);
        $html = self::expandTocPlaceholders($html);

        return $html;
    }

    /**
     * Word/редакторы часто вставляют «•» вместо `-`/`*`/`+`.
     * CommonMark не считает их маркерами списка → soft-break схлопывается в один абзац.
     */
    private static function normalizeTypographicLists(string $markdown): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown);
        if ($lines === false) {
            return $markdown;
        }

        $inFence = false;
        $fenceChar = '';
        $out = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*([`~]{3,})/', $line, $fm)) {
                $char = $fm[1][0];
                if (! $inFence) {
                    $inFence = true;
                    $fenceChar = $char;
                } elseif ($char === $fenceChar) {
                    $inFence = false;
                    $fenceChar = '';
                }
                $out[] = $line;

                continue;
            }

            if (! $inFence) {
                // optional blockquote prefixes + indent + typographic bullet
                $line = (string) preg_replace(
                    '/^((?:\s{0,3}>\s?)*)([ \t]*)[•‣◦▪●○]\h+/u',
                    '$1$2- ',
                    $line
                );
            }

            $out[] = $line;
        }

        return implode("\n", $out);
    }

    /**
     * Проставляет уникальные id у h2/h3 (для якорей и оглавления).
     * Уже существующие id не трогает.
     */
    public static function addHeadingIds(string $html): string
    {
        $used = [];

        return (string) preg_replace_callback(
            '/<h([23])(\s[^>]*)?>(.*?)<\/h\1>/is',
            static function (array $m) use (&$used): string {
                $level = $m[1];
                $attrs = (string) ($m[2] ?? '');
                $inner = (string) $m[3];

                if (preg_match('/\bid\s*=/i', $attrs)) {
                    if (preg_match('/\bid=["\']([^"\']+)["\']/i', $attrs, $im)) {
                        $used[strtolower((string) $im[1])] = true;
                    }

                    return $m[0];
                }

                $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $base = Str::slug($text);
                if ($base === '') {
                    $base = 'section';
                }
                $id = $base;
                $n = 2;
                while (isset($used[strtolower($id)])) {
                    $id = $base.'-'.$n;
                    $n++;
                }
                $used[strtolower($id)] = true;

                return '<h'.$level.$attrs.' id="'.e($id).'">'.$inner.'</h'.$level.'>';
            },
            $html
        );
    }

    /**
     * @return list<array{id: string, text: string, level: int}>
     */
    public static function extractHeadings(string $html): array
    {
        $headings = [];
        if (! preg_match_all('/<h([23])\b([^>]*)>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($m as $match) {
            $attrs = (string) $match[2];
            if (! preg_match('/\bid=["\']([^"\']+)["\']/i', $attrs, $im)) {
                continue;
            }
            $text = trim(html_entity_decode(strip_tags((string) $match[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text === '') {
                continue;
            }
            $headings[] = [
                'level' => (int) $match[1],
                'id' => (string) $im[1],
                'text' => $text,
            ];
        }

        return $headings;
    }

    /**
     * @param  list<array{id: string, text: string, level: int}>  $headings
     */
    public static function renderTocHtml(array $headings): string
    {
        if ($headings === []) {
            return '<nav class="theory-toc theory-toc--empty" aria-label="Оглавление">'
                .'<p class="theory-toc__title">Оглавление</p>'
                .'<p class="theory-toc__hint">Добавьте заголовки <code>##</code> / <code>###</code> — они появятся здесь автоматически.</p>'
                .'</nav>';
        }

        $html = '<nav class="theory-toc" aria-label="Оглавление">'
            .'<p class="theory-toc__title">Оглавление</p>'
            .'<ol class="theory-toc__list">';

        $i = 0;
        $n = count($headings);
        while ($i < $n) {
            $h = $headings[$i];
            $level = (int) $h['level'];
            $id = e((string) $h['id']);
            $text = e((string) $h['text']);

            if ($level === 2) {
                $html .= '<li class="theory-toc__item theory-toc__item--h2">'
                    .'<a href="#'.$id.'">'.$text.'</a>';
                $i++;
                $subs = [];
                while ($i < $n && (int) $headings[$i]['level'] === 3) {
                    $subs[] = $headings[$i];
                    $i++;
                }
                if ($subs !== []) {
                    $html .= '<ol class="theory-toc__sub">';
                    foreach ($subs as $sub) {
                        $html .= '<li class="theory-toc__item theory-toc__item--h3">'
                            .'<a href="#'.e((string) $sub['id']).'">'.e((string) $sub['text']).'</a></li>';
                    }
                    $html .= '</ol>';
                }
                $html .= '</li>';
                continue;
            }

            $html .= '<li class="theory-toc__item theory-toc__item--h3">'
                .'<a href="#'.$id.'">'.$text.'</a></li>';
            $i++;
        }

        $html .= '</ol></nav>';

        return $html;
    }

    /** [[toc]] / [TOC] → placeholder that survives Str::markdown. */
    private static function replaceTocMarkers(string $markdown): string
    {
        return (string) preg_replace(
            '/^[ \t]*(?:\[\[toc\]\]|\[TOC\])[ \t]*$/im',
            self::TOC_PLACEHOLDER,
            $markdown
        );
    }

    private static function expandTocPlaceholders(string $html): string
    {
        $token = self::TOC_PLACEHOLDER;
        $escaped = e($token);
        if (! str_contains($html, $token) && ! str_contains($html, $escaped)) {
            return $html;
        }

        $headings = self::extractHeadings($html);
        $toc = self::renderTocHtml($headings);

        foreach ([$token, $escaped] as $needle) {
            $html = (string) preg_replace(
                '/<p>\s*'.preg_quote($needle, '/').'\s*<\/p>/u',
                $toc,
                $html
            );
            $html = str_replace($needle, $toc, $html);
        }

        return $html;
    }

    /**
     * Маркеры в Markdown:
     * ## {center} Текст → class theory-heading--center (полоска снизу)
     * ## {center-bar} Текст → class theory-heading--center-bar (текст по центру, полоска слева)
     */
    public static function enrichCenteredHeadings(string $html): string
    {
        return (string) preg_replace_callback(
            '/<(h[1-6])(\s[^>]*)?>\s*(?:\{(center-bar|center)\}|&#123;(center-bar|center)&#125;)\s*/iu',
            static function (array $m): string {
                $tag = $m[1];
                $attrs = (string) ($m[2] ?? '');
                $kind = strtolower((string) (($m[3] ?? '') !== '' ? $m[3] : ($m[4] ?? 'center')));
                $class = $kind === 'center-bar' ? 'theory-heading--center-bar' : 'theory-heading--center';
                if (preg_match('/\bclass="/i', $attrs)) {
                    $attrs = preg_replace(
                        '/\bclass="/i',
                        'class="'.$class.' ',
                        $attrs,
                        1
                    );
                } else {
                    $attrs .= ' class="'.$class.'"';
                }

                return '<'.$tag.$attrs.'>';
            },
            $html
        );
    }

    /** Короткий inline Markdown (варианты ответов, пары сопоставления). */
    public static function inlineHtml(string $text): string
    {
        $text = str_replace("\0", '', trim($text));
        if ($text === '') {
            return '';
        }

        if (! str_contains($text, '![') && ! str_contains($text, '**') && ! str_contains($text, '`')) {
            return e($text);
        }

        $text = self::expandMediaPaths($text);
        $html = (string) Str::markdown($text);

        return self::enrichMediaFigures($html, true);
    }

    public static function enrichCallouts(string $html): string
    {
        return (string) preg_replace_callback(
            '/<blockquote>(.*?)<\/blockquote>/s',
            static fn (array $m): string => self::convertBlockquote((string) $m[1]),
            $html
        );
    }

    private static function expandMediaPaths(string $markdown): string
    {
        return (string) preg_replace_callback(
            '/!\[([^\]]*)\]\((\/media\/[0-9a-fA-F-]{36})(?:\/thumb)?(\s+"([^"]*)")?\)/',
            static function (array $m): string {
                $uuid = basename(rtrim($m[2], '/'));
                if (str_ends_with($m[2], '/thumb')) {
                    $url = route('media.thumb', ['uuid' => $uuid], false);
                } else {
                    $url = route('media.show', ['uuid' => $uuid], false);
                }
                $title = isset($m[4]) && $m[4] !== '' ? ' "'.$m[4].'"' : '';

                return '!['.$m[1].']('.$url.$title.')';
            },
            $markdown
        );
    }

    private static function enrichMediaFigures(string $html, bool $compact = false): string
    {
        return (string) preg_replace_callback(
            '/<p>\s*<img([^>]+)>\s*<\/p>|<img([^>]+)>/',
            function (array $m) use ($compact): string {
                $attrs = (string) ($m[1] !== '' ? $m[1] : $m[2]);
                $src = '';
                $alt = '';
                if (preg_match('/\bsrc="([^"]+)"/', $attrs, $sm)) {
                    $src = $sm[1];
                }
                if (preg_match('/\balt="([^"]*)"/', $attrs, $am)) {
                    $alt = $am[1];
                }

                if ($src === '' || ! self::isCourseMediaUrl($src)) {
                    if ($compact && $src !== '') {
                        return '<span class="course-media-inline">'.$m[0].'</span>';
                    }

                    return $m[0];
                }

                $uuid = self::uuidFromMediaUrl($src);
                $meta = $uuid !== null ? self::mediaMeta($uuid) : null;
                $isLarge = $meta !== null
                    ? ($meta['width'] >= (int) config('media.lightbox_min_dimension', 600)
                        || $meta['height'] >= (int) config('media.lightbox_min_dimension', 600))
                    : true;
                $fullSrc = $uuid !== null ? route('media.show', ['uuid' => $uuid]) : $src;

                $imgClass = 'course-media-img'.($compact ? ' course-media-img--compact' : '');
                $figureClass = 'course-media-figure'.($compact ? ' course-media-figure--compact' : '');
                $display = self::parseMediaDisplayOptions($attrs);
                $figureClass .= self::figureAlignClass($display['align']);
                $figureStyle = self::figureWidthStyle($display['width']);
                $styleAttr = $figureStyle !== '' ? ' style="'.e($figureStyle).'"' : '';
                $imgStyleAttr = self::mediaImgStyleAttr();
                $displaySrc = self::displaySrcForOptions($src, $uuid, $display['width']);

                if ($isLarge) {
                    return '<figure class="'.$figureClass.'"'.$styleAttr.'>'
                        .'<button type="button" class="course-media-zoom" data-course-lightbox '
                        .'data-course-lightbox-src="'.e($fullSrc).'" data-course-lightbox-alt="'.e($alt).'" '
                        .'aria-label="Увеличить изображение">'
                        .'<img class="'.$imgClass.'" src="'.e($displaySrc).'" alt="'.e($alt).'"'.$imgStyleAttr.' loading="lazy" decoding="async">'
                        .'<span class="course-media-zoom__badge" aria-hidden="true">'
                        .'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35M11 8v6M8 11h6"/></svg>'
                        .'</span></button>'
                        .($alt !== '' && ! $compact ? '<figcaption class="course-media-caption">'.e($alt).'</figcaption>' : '')
                        .'</figure>';
                }

                return '<figure class="'.$figureClass.'"'.$styleAttr.'>'
                    .'<img class="'.$imgClass.'" src="'.e($displaySrc).'" alt="'.e($alt).'"'.$imgStyleAttr.' loading="lazy" decoding="async">'
                    .($alt !== '' && ! $compact ? '<figcaption class="course-media-caption">'.e($alt).'</figcaption>' : '')
                    .'</figure>';
            },
            $html
        );
    }

    private static function isCourseMediaUrl(string $src): bool
    {
        return str_contains($src, '/media/') && self::uuidFromMediaUrl($src) !== null;
    }

    private static function uuidFromMediaUrl(string $src): ?string
    {
        if (preg_match('#/media/([0-9a-fA-F-]{36})(?:/thumb)?(?:\?|$)#', $src, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    /**
     * @return array{width: ?string, align: ?string}
     */
    private static function parseMediaDisplayOptions(string $attrs): array
    {
        $out = ['width' => null, 'align' => null];
        if (! preg_match('/\btitle="([^"]*)"/', $attrs, $m)) {
            return $out;
        }

        foreach (explode(';', $m[1]) as $piece) {
            $piece = trim($piece);
            if (preg_match('/^w=(.+)$/i', $piece, $wm)) {
                $out['width'] = trim($wm[1]);
            } elseif (preg_match('/^align=(.+)$/i', $piece, $am)) {
                $out['align'] = strtolower(trim($am[1]));
            }
        }

        return $out;
    }

    private static function figureAlignClass(?string $align): string
    {
        return match ($align) {
            'left' => ' course-media-figure--align-left',
            'right' => ' course-media-figure--align-right',
            'center' => ' course-media-figure--align-center',
            default => '',
        };
    }

    private static function figureWidthStyle(?string $width): string
    {
        if ($width === null || $width === '') {
            return '';
        }

        if (preg_match('/^\d+$/', $width)) {
            return 'max-width:'.$width.'%;';
        }

        if (preg_match('/^\d+px$/i', $width)) {
            return 'max-width:'.$width.';';
        }

        return '';
    }

    private static function mediaImgStyleAttr(): string
    {
        return ' style="max-width:100%;height:auto;display:block"';
    }

    private static function displaySrcForOptions(string $src, ?string $uuid, ?string $width): string
    {
        if ($uuid === null || $width === null || $width === '') {
            return $src;
        }

        if (preg_match('/^\d+px$/i', $width) && (int) $width <= 320) {
            return route('media.thumb', ['uuid' => $uuid], false);
        }

        if (preg_match('/^\d+$/', $width) && (int) $width <= 50) {
            return route('media.thumb', ['uuid' => $uuid], false);
        }

        return $src;
    }

    /**
     * @return array{width:int,height:int}|null
     */
    private static function mediaMeta(string $uuid): ?array
    {
        static $cache = [];
        if (array_key_exists($uuid, $cache)) {
            return $cache[$uuid];
        }

        if (! Schema::hasTable('media_assets')) {
            return $cache[$uuid] = null;
        }

        $row = MediaAsset::query()->where('uuid', $uuid)->first(['width', 'height']);
        if ($row === null) {
            return $cache[$uuid] = null;
        }

        return $cache[$uuid] = [
            'width' => (int) $row->width,
            'height' => (int) $row->height,
        ];
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
