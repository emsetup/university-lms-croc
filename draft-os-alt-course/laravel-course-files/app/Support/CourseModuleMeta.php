<?php

namespace App\Support;

/**
 * Метаданные модуля из config/course.php с подстановкой теории из @snippet:… при необходимости.
 */
final class CourseModuleMeta
{
    /**
     * @return array<string, mixed>
     */
    public static function resolved(int $module): array
    {
        $meta = config('course.modules.'.$module);
        if (! is_array($meta)) {
            return [
                'letter' => (string) $module,
                'title' => 'Модуль '.$module,
                'summary' => '',
                'theory' => '',
                'practice' => '',
            ];
        }

        $out = $meta;
        $ref = CourseTheoryPaths::rawTheoryReference($module);
        $snippet = CourseTheoryPaths::snippetBasenameFromReference($ref);
        if ($snippet !== null && CourseTheoryPaths::snippetBasenameTargetsModule($snippet, $module)) {
            $path = CourseTheoryPaths::resolvedTheoryMarkdownPath($module);
            $out['theory_snippet_basename'] = $snippet;
            $out['theory'] = is_file($path) ? (string) file_get_contents($path) : 'Файл теории не найден: '.$snippet;
        }

        return $out;
    }
}
