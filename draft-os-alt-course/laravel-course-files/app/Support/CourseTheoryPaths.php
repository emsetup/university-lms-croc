<?php

namespace App\Support;

/**
 * Пути к файлам теории в config/snippets (module_N_theory.md).
 */
final class CourseTheoryPaths
{
    public static function snippetsDirectory(): string
    {
        return rtrim(config_path('snippets'), DIRECTORY_SEPARATOR);
    }

    public static function theoryBasenameForModule(int $module): string
    {
        return 'module_'.$module.'_theory.md';
    }

    /**
     * Имя файла из course.php соответствует номеру модуля (module_1_theory.md или module_01_theory.md).
     */
    public static function snippetBasenameTargetsModule(string $snippetBasename, int $module): bool
    {
        if (! preg_match('/^module_(\d+)_theory\.md$/', $snippetBasename, $m)) {
            return false;
        }

        return (int) $m[1] === $module;
    }

    public static function theoryFilePathForModule(int $module): string
    {
        return self::snippetsDirectory().DIRECTORY_SEPARATOR.self::theoryBasenameForModule($module);
    }

    /**
     * Абсолютный путь к файлу теории для модуля: сначала из @snippet: в конфиге, иначе module_N_theory.md.
     */
    public static function resolvedTheoryMarkdownPath(int $module): string
    {
        $ref = self::rawTheoryReference($module);
        $snippet = self::snippetBasenameFromReference($ref);
        if ($snippet !== null && self::snippetBasenameTargetsModule($snippet, $module)) {
            return self::absolutePathForSnippetBasename($snippet);
        }

        return self::theoryFilePathForModule($module);
    }

    /**
     * Сырое значение theory из config/course.php (строка heredoc или @snippet:file.md).
     */
    public static function rawTheoryReference(int $module): string
    {
        $v = config('course.modules.'.$module.'.theory');

        return is_string($v) ? $v : '';
    }

    public static function snippetBasenameFromReference(string $ref): ?string
    {
        if (! str_starts_with($ref, '@snippet:')) {
            return null;
        }
        $name = trim(substr($ref, strlen('@snippet:')));
        if ($name === '' || basename($name) !== $name) {
            return null;
        }
        if (! preg_match('/^[a-zA-Z0-9._-]+\.md$/', $name)) {
            return null;
        }

        return $name;
    }

    public static function absolutePathForSnippetBasename(string $basename): string
    {
        return self::snippetsDirectory().DIRECTORY_SEPARATOR.$basename;
    }

    /**
     * @return list<string> пути к существующим module_*_theory.md
     */
    public static function existingTheoryMarkdownFiles(): array
    {
        $dir = self::snippetsDirectory();
        if (! is_dir($dir)) {
            return [];
        }
        $paths = glob($dir.DIRECTORY_SEPARATOR.'module_*_theory.md') ?: [];

        return array_values(array_filter($paths, 'is_file'));
    }
}
