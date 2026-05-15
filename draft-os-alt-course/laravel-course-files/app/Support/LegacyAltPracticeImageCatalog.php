<?php

namespace App\Support;

/**
 * Системные Docker-образы курса «ОС Альт» из config/practice_lab.php (ключи 1–10).
 */
final class LegacyAltPracticeImageCatalog
{
    /**
     * @return array<int, array{title: string, template: string, slug: string}>
     */
    public static function moduleMap(): array
    {
        return [
            1 => ['title' => 'Alt · Модуль 1', 'template' => 'lab-m1', 'slug' => 'alt-m1'],
            2 => ['title' => 'Alt · Модуль 2', 'template' => 'lab-m2', 'slug' => 'alt-m2'],
            3 => ['title' => 'Alt · Модуль 3', 'template' => 'lab-m3', 'slug' => 'alt-m3'],
            5 => ['title' => 'Alt · Модуль 5', 'template' => 'lab-m5', 'slug' => 'alt-m5'],
            6 => ['title' => 'Alt · Модуль 6', 'template' => 'lab-m6', 'slug' => 'alt-m6'],
            7 => ['title' => 'Alt · Модуль 7', 'template' => 'lab-m7', 'slug' => 'alt-m7'],
            8 => ['title' => 'Alt · Модуль 8', 'template' => 'lab-m8', 'slug' => 'alt-m8'],
            9 => ['title' => 'Alt · Модуль 9', 'template' => 'lab-m9', 'slug' => 'alt-m9'],
            10 => ['title' => 'Alt · Финальная', 'template' => 'final-lab', 'slug' => 'alt-final-lab'],
        ];
    }

    /**
     * @return list<array{module_key: int, title: string, template: string, slug: string, docker_tag: string}>
     */
    public static function entries(): array
    {
        $images = config('practice_lab.images', []);
        if (! is_array($images)) {
            return [];
        }

        $out = [];
        foreach (self::moduleMap() as $moduleKey => $meta) {
            $tag = isset($images[(string) $moduleKey]) ? trim((string) $images[(string) $moduleKey]) : '';
            if ($tag === '') {
                continue;
            }
            $out[] = [
                'module_key' => (int) $moduleKey,
                'title' => (string) $meta['title'],
                'template' => (string) $meta['template'],
                'slug' => (string) $meta['slug'],
                'docker_tag' => $tag,
            ];
        }

        return $out;
    }
}
