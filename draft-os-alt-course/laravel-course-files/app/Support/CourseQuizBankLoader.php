<?php

namespace App\Support;

/**
 * Безопасная загрузка банков вопросов из JSON (для веб-редактора).
 * На переходный период поддерживает fallback на старые PHP-сниппеты.
 */
final class CourseQuizBankLoader
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function loadJsonBank(?string $jsonPath): array
    {
        if (! $jsonPath) {
            return [];
        }
        if (! is_file($jsonPath)) {
            return [];
        }
        $raw = @file_get_contents($jsonPath);
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return [];
        }
        // Нормализуем в list
        return array_values(array_filter($data, static fn ($q) => is_array($q)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function loadBankWithFallback(?string $jsonPath, ?string $phpRequirePath): array
    {
        $json = self::loadJsonBank($jsonPath);
        if ($json !== []) {
            return $json;
        }
        if (! $phpRequirePath || ! is_file($phpRequirePath)) {
            return [];
        }
        try {
            /** @var mixed $v */
            $v = require $phpRequirePath;
        } catch (\Throwable) {
            return [];
        }
        if (! is_array($v)) {
            return [];
        }

        return array_values($v);
    }
}

