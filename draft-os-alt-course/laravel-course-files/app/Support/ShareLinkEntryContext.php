<?php

namespace App\Support;

/**
 * Сессионный контекст входа по быстрой ссылке /s/{token}:
 * открывает целевой модуль/раздел без прохождения последовательности,
 * но не обходит видимость (view_audience).
 */
final class ShareLinkEntryContext
{
    private const SESSION_KEY = 'share_link_entry';

    public static function activate(int $courseId, ?int $moduleId = null, ?int $sectionId = null): void
    {
        session([self::SESSION_KEY => [
            'course_id' => $courseId,
            'module_id' => $moduleId,
            'section_id' => $sectionId,
            'at' => time(),
        ]]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array{course_id:int,module_id:?int,section_id:?int,at:int}|null
     */
    public static function payload(): ?array
    {
        $raw = session(self::SESSION_KEY);
        if (! is_array($raw)) {
            return null;
        }
        $courseId = (int) ($raw['course_id'] ?? 0);
        if ($courseId < 1) {
            return null;
        }

        return [
            'course_id' => $courseId,
            'module_id' => isset($raw['module_id']) && (int) $raw['module_id'] > 0 ? (int) $raw['module_id'] : null,
            'section_id' => isset($raw['section_id']) && (int) $raw['section_id'] > 0 ? (int) $raw['section_id'] : null,
            'at' => (int) ($raw['at'] ?? 0),
        ];
    }

    public static function isActiveForCourse(int $courseId): bool
    {
        $p = self::payload();

        return $p !== null && $p['course_id'] === $courseId;
    }

    public static function allowsModule(int $courseModuleId, ?int $courseId = null): bool
    {
        $p = self::payload();
        if ($p === null) {
            return false;
        }
        if ($courseId !== null && $p['course_id'] !== $courseId) {
            return false;
        }
        // Ссылка на весь курс — не разблокирует модули по последовательности.
        if ($p['module_id'] === null && $p['section_id'] === null) {
            return false;
        }
        if ($p['module_id'] !== null && $p['module_id'] === $courseModuleId) {
            return true;
        }

        return false;
    }

    public static function bypassesStepGates(int $courseModuleId, ?int $courseId = null): bool
    {
        return self::allowsModule($courseModuleId, $courseId);
    }
}
