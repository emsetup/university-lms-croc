<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CourseGlossaryTerm;
use Illuminate\Support\Facades\Schema;

final class CourseGlossaryService
{
    /** @var array<int, list<array{id:int, term:string, abbreviation:?string, definition:string, forms:list<string>}>> */
    private static array $cache = [];

    /**
     * @return list<array{id:int, term:string, abbreviation:?string, definition:string, forms:list<string>}>
     */
    public function termsForCourse(int $courseId): array
    {
        if ($courseId < 1 || ! Schema::hasTable('course_glossary_terms')) {
            return [];
        }
        if (isset(self::$cache[$courseId])) {
            return self::$cache[$courseId];
        }

        $rows = CourseGlossaryTerm::query()
            ->where('course_id', $courseId)
            ->orderBy('sort')
            ->orderBy('term')
            ->get(['id', 'term', 'abbreviation', 'definition']);

        $out = [];
        foreach ($rows as $row) {
            $forms = $row->matchForms();
            if ($forms === []) {
                continue;
            }
            $out[] = [
                'id' => (int) $row->id,
                'term' => (string) $row->term,
                'abbreviation' => $row->abbreviation !== null && trim((string) $row->abbreviation) !== ''
                    ? trim((string) $row->abbreviation)
                    : null,
                'definition' => trim((string) $row->definition),
                'forms' => $forms,
            ];
        }

        // Длинные формы раньше, чтобы «ЭДО СБИС» матчился раньше «ЭДО».
        usort($out, static function (array $a, array $b): int {
            $la = max(array_map('mb_strlen', $a['forms']));
            $lb = max(array_map('mb_strlen', $b['forms']));

            return $lb <=> $la;
        });

        return self::$cache[$courseId] = $out;
    }

    public function clearCache(?int $courseId = null): void
    {
        if ($courseId === null) {
            self::$cache = [];

            return;
        }
        unset(self::$cache[$courseId]);
    }

    /**
     * Подсветить термины в HTML (текст вне ссылок/кода).
     */
    public function enrichHtml(string $html, int $courseId): string
    {
        $terms = $this->termsForCourse($courseId);
        if ($terms === [] || trim($html) === '') {
            return $html;
        }

        $patterns = [];
        $byKey = [];
        foreach ($terms as $term) {
            foreach ($term['forms'] as $form) {
                $form = trim($form);
                if ($form === '') {
                    continue;
                }
                $key = mb_strtolower($form);
                if (isset($byKey[$key])) {
                    continue;
                }
                $byKey[$key] = $term;
                $patterns[] = preg_quote($form, '/');
            }
        }
        if ($patterns === []) {
            return $html;
        }

        // Длиннее раньше.
        usort($patterns, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $alt = implode('|', $patterns);
        // Границы: не буква/цифра/underscore с обеих сторон (Unicode).
        $regex = '/(?<![\p{L}\p{N}_])('.$alt.')(?![\p{L}\p{N}_])/iu';

        $skipTags = ['a', 'abbr', 'code', 'pre', 'script', 'style', 'kbd', 'samp', 'textarea', 'button'];

        $wrapped = '<div id="course-glossary-root">'.$html.'</div>';
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $textNodes = $xpath->query('//text()[normalize-space(.) != ""]');
        if ($textNodes === false) {
            return $html;
        }

        /** @var list<\DOMText> $nodes */
        $nodes = [];
        foreach ($textNodes as $node) {
            if (! $node instanceof \DOMText) {
                continue;
            }
            if ($this->isInsideSkippedTag($node, $skipTags)) {
                continue;
            }
            if ($this->isInsideClass($node, 'course-glossary-term')) {
                continue;
            }
            $nodes[] = $node;
        }

        foreach ($nodes as $textNode) {
            $text = $textNode->nodeValue ?? '';
            if ($text === '' || ! preg_match($regex, $text)) {
                continue;
            }
            $frag = $dom->createDocumentFragment();
            $offset = 0;
            if (! preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as $m) {
                $match = $m[0];
                $pos = $this->utf8Offset($text, (int) $m[1]);
                if ($pos > $offset) {
                    $frag->appendChild($dom->createTextNode(mb_substr($text, $offset, $pos - $offset)));
                }
                $key = mb_strtolower($match);
                $term = $byKey[$key] ?? null;
                if ($term === null) {
                    $frag->appendChild($dom->createTextNode($match));
                } else {
                    $el = $dom->createElement('abbr');
                    $el->setAttribute('class', 'course-glossary-term');
                    $el->setAttribute('tabindex', '0');
                    $el->setAttribute('data-glossary-id', (string) $term['id']);
                    $el->setAttribute('data-glossary-term', $term['term']);
                    $tip = $term['definition'];
                    if ($term['abbreviation'] !== null) {
                        $tip = $term['term'].' ('.$term['abbreviation'].') — '.$tip;
                    } elseif ($match !== $term['term']) {
                        $tip = $term['term'].' — '.$tip;
                    }
                    $el->setAttribute('data-glossary-tip', $tip);
                    $el->setAttribute('aria-label', $tip);
                    $el->appendChild($dom->createTextNode($match));
                    $frag->appendChild($el);
                }
                $offset = $pos + mb_strlen($match);
            }
            if ($offset < mb_strlen($text)) {
                $frag->appendChild($dom->createTextNode(mb_substr($text, $offset)));
            }
            $textNode->parentNode?->replaceChild($frag, $textNode);
        }

        $root = $dom->getElementById('course-glossary-root');
        if ($root === null) {
            return $html;
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out !== '' ? $out : $html;
    }

    /**
     * @param  list<string>  $tags
     */
    private function isInsideSkippedTag(\DOMNode $node, array $tags): bool
    {
        $p = $node->parentNode;
        while ($p instanceof \DOMElement) {
            if (in_array(strtolower($p->tagName), $tags, true)) {
                return true;
            }
            $p = $p->parentNode;
        }

        return false;
    }

    private function isInsideClass(\DOMNode $node, string $class): bool
    {
        $p = $node->parentNode;
        while ($p instanceof \DOMElement) {
            $c = $p->getAttribute('class');
            if ($c !== '' && preg_match('/(?:^|\s)'.preg_quote($class, '/').'(?:\s|$)/', $c)) {
                return true;
            }
            $p = $p->parentNode;
        }

        return false;
    }

    /** PCRE byte offset → mb character offset. */
    private function utf8Offset(string $text, int $byteOffset): int
    {
        if ($byteOffset <= 0) {
            return 0;
        }
        $slice = substr($text, 0, $byteOffset);

        return mb_strlen($slice === false ? '' : $slice);
    }
}
