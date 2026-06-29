<?php

namespace App\Services;

use App\Support\CourseContentMarkdown;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class DocumentationCatalog
{
    /** @var list<string> */
    private const AUDIENCE_LEVELS = ['learner', 'staff', 'staff_editor', 'staff_admin'];

    /** @var list<array<string, mixed>>|null */
    private ?array $articles = null;

    public function allArticles(): array
    {
        if ($this->articles !== null) {
            return $this->articles;
        }

        $base = (string) config('documentation.path', resource_path('docs'));
        if (! is_dir($base)) {
            return $this->articles = [];
        }

        $items = [];
        foreach (File::allFiles($base) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }
            $parsed = $this->parseFile($file->getPathname());
            if ($parsed === null) {
                continue;
            }
            $items[] = $parsed;
        }

        usort($items, static function (array $a, array $b): int {
            $sec = strcmp((string) $a['section'], (string) $b['section']);
            if ($sec !== 0) {
                return $sec;
            }

            return ((int) $a['order']) <=> ((int) $b['order']);
        });

        return $this->articles = $items;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function groupedVisible(?PortalStaffAccess $psa): array
    {
        $visible = $this->visibleArticles($psa);
        $order = config('documentation.section_order', []);
        $grouped = [];
        foreach ($visible as $article) {
            $section = (string) $article['section'];
            $grouped[$section][] = $article;
        }

        if ($order !== []) {
            $sorted = [];
            foreach ($order as $section) {
                if (isset($grouped[$section])) {
                    $sorted[$section] = $grouped[$section];
                    unset($grouped[$section]);
                }
            }
            foreach ($grouped as $section => $articles) {
                $sorted[$section] = $articles;
            }

            return $sorted;
        }

        return $grouped;
    }

    /** @return list<array<string, mixed>> */
    public function visibleArticles(?PortalStaffAccess $psa): array
    {
        return array_values(array_filter(
            $this->allArticles(),
            fn (array $a): bool => $this->canViewAudience((string) $a['audience'], $psa)
        ));
    }

    public function findVisibleBySlug(string $slug, ?PortalStaffAccess $psa): ?array
    {
        $slug = trim($slug, '/');
        foreach ($this->allArticles() as $article) {
            if ((string) $article['slug'] !== $slug) {
                continue;
            }
            if (! $this->canViewAudience((string) $article['audience'], $psa)) {
                return null;
            }

            return $article;
        }

        return null;
    }

    public function renderBodyHtml(string $markdown): string
    {
        $markdown = $this->expandImagePaths($markdown);
        $html = (string) Str::markdown($markdown);
        $html = $this->expandImagePathsInHtml($html);
        $html = CourseContentMarkdown::enrichCallouts($html);
        $html = $this->wrapScreenshots($html);
        $html = $this->wrapTables($html);

        return $html;
    }

    /**
     * @return array{prev: ?array<string, mixed>, next: ?array<string, mixed>}
     */
    public function neighbors(string $slug, ?PortalStaffAccess $psa): array
    {
        $visible = $this->visibleArticles($psa);
        $index = null;
        foreach ($visible as $i => $article) {
            if ((string) $article['slug'] === $slug) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return ['prev' => null, 'next' => null];
        }

        return [
            'prev' => $visible[$index - 1] ?? null,
            'next' => $visible[$index + 1] ?? null,
        ];
    }

    /** @return list<array{id: string, text: string, level: int}> */
    public function extractHeadings(string $html): array
    {
        $headings = [];
        if (! preg_match_all('/<h([23]) id="([^"]+)"[^>]*>(.*?)<\/h\1>/s', $html, $m, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($m as $match) {
            $headings[] = [
                'level' => (int) $match[1],
                'id' => (string) $match[2],
                'text' => trim(strip_tags((string) $match[3])),
            ];
        }

        return $headings;
    }

    public function addHeadingIds(string $html): string
    {
        return (string) preg_replace_callback(
            '/<h([23])>(.*?)<\/h\1>/s',
            function (array $m): string {
                $text = trim(strip_tags((string) $m[2]));
                $id = Str::slug($text);

                return '<h'.$m[1].' id="'.e($id).'">'.$m[2].'</h'.$m[1].'>';
            },
            $html
        );
    }

    public function canViewAudience(string $audience, ?PortalStaffAccess $psa): bool
    {
        $audience = $this->normalizeAudience($audience);
        $userLevel = $this->userMaxAudienceLevel($psa);
        $required = array_search($audience, self::AUDIENCE_LEVELS, true);

        return $required !== false && $required <= $userLevel;
    }

    private function userMaxAudienceLevel(?PortalStaffAccess $psa): int
    {
        if ($psa === null) {
            return 0;
        }
        if ($psa->isPortalAdmin()) {
            return 3;
        }
        if ($psa->canUseCourseAdminTools()) {
            return 2;
        }

        return 1;
    }

    private function normalizeAudience(string $audience): string
    {
        $audience = strtolower(trim($audience));
        if (! in_array($audience, self::AUDIENCE_LEVELS, true)) {
            return 'learner';
        }

        return $audience;
    }

    /** @return array<string, mixed>|null */
    private function parseFile(string $path): ?array
    {
        $raw = File::get($path);
        if (! preg_match('/\A---\s*\r?\n(.*?)\r?\n---\s*\r?\n(.*)\z/s', $raw, $m)) {
            return null;
        }

        $meta = $this->parseFrontmatter((string) $m[1]);
        $slug = (string) ($meta['slug'] ?? '');
        if ($slug === '') {
            return null;
        }

        return [
            'slug' => $slug,
            'title' => (string) ($meta['title'] ?? $slug),
            'summary' => (string) ($meta['summary'] ?? ''),
            'audience' => $this->normalizeAudience((string) ($meta['audience'] ?? 'learner')),
            'order' => (int) ($meta['order'] ?? 0),
            'section' => (string) ($meta['section'] ?? 'Обучающийся'),
            'screenshot' => isset($meta['screenshot']) ? (string) $meta['screenshot'] : null,
            'body' => (string) $m[2],
            'path' => $path,
        ];
    }

    /** @return array<string, string|int> */
    private function parseFrontmatter(string $yaml): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $yaml) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            if ($key === 'order') {
                $out[$key] = (int) $value;
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function expandImagePaths(string $markdown): string
    {
        return (string) preg_replace_callback(
            '/!\[([^\]]*)\]\((\/images\/docs\/[^)]+)\)/',
            static function (array $m): string {
                return '!['.$m[1].']('.asset(ltrim($m[2], '/')).')';
            },
            $markdown
        );
    }

    private function expandImagePathsInHtml(string $html): string
    {
        return (string) preg_replace_callback(
            '/src="(\/images\/docs\/[^"]+)"/',
            static function (array $m): string {
                return 'src="'.e(asset(ltrim($m[1], '/'))).'"';
            },
            $html
        );
    }

    private function wrapScreenshots(string $html): string
    {
        return (string) preg_replace_callback(
            '/<p>\s*<img([^>]+)>\s*<\/p>/',
            function (array $m): string {
                $attrs = (string) $m[1];
                $src = '';
                $alt = 'Скриншот интерфейса';
                if (preg_match('/\bsrc="([^"]+)"/', $attrs, $sm)) {
                    $src = $sm[1];
                }
                if (preg_match('/\balt="([^"]*)"/', $attrs, $am)) {
                    $alt = $am[1] !== '' ? $am[1] : $alt;
                }

                return '<figure class="docs-figure">'
                    .'<button type="button" class="docs-figure__zoom" data-docs-lightbox '
                    .'data-docs-lightbox-src="'.e($src).'" data-docs-lightbox-alt="'.e($alt).'" '
                    .'aria-label="Увеличить: '.e($alt).'">'
                    .'<img src="'.e($src).'" alt="'.e($alt).'" loading="lazy" decoding="async">'
                    .'<span class="docs-figure__overlay" aria-hidden="true">'
                    .'<span class="docs-figure__overlay-icon">'
                    .'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">'
                    .'<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35M11 8v6M8 11h6"/>'
                    .'</svg>'
                    .'<span>Увеличить</span>'
                    .'</span>'
                    .'</span>'
                    .'</button>'
                    .'</figure>';
            },
            $html
        );
    }

    private function wrapTables(string $html): string
    {
        return (string) preg_replace(
            '/<table\b[^>]*>.*?<\/table>/s',
            '<div class="docs-table-wrap">$0</div>',
            $html
        );
    }
}
