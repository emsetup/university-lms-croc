<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Картинки шаблона писем — встраиваются inline (CID), чтобы Outlook не блокировал внешние URL.
 */
final class PortalMailAssets
{
    /** @var list<string> */
    public const TEMPLATE_FILES = [
        'cap-top.png',
        'cap-bottom.png',
        'card-cap-top.png',
        'card-cap-bottom.png',
        'foot-cap-top.png',
        'foot-cap-bottom.png',
    ];

    /** label keyword => button asset */
    private const CTA_FILES = [
        'портал' => 'cta-open-portal.png',
        'панель' => 'cta-open-admin.png',
        'опрос' => 'cta-survey.png',
        'настройк' => 'cta-settings.png',
        'курс' => 'cta-open-course.png',
    ];

    public static function directory(): string
    {
        return public_path('images/email');
    }

    /**
     * @return array{file: string, cid: string, path: string, width: int, height: int}|null
     */
    public static function resolveCtaButton(string $label): ?array
    {
        $file = 'cta-open.png';
        $lower = mb_strtolower($label);
        foreach (self::CTA_FILES as $needle => $candidate) {
            if (str_contains($lower, $needle)) {
                $file = $candidate;
                break;
            }
        }

        $path = self::directory().DIRECTORY_SEPARATOR.$file;
        if (! is_file($path)) {
            $path = self::directory().DIRECTORY_SEPARATOR.'cta-open.png';
            $file = 'cta-open.png';
        }
        if (! is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);
        $cid = pathinfo($file, PATHINFO_FILENAME);

        return [
            'file' => $file,
            'cid' => $cid,
            'path' => $path,
            'width' => (int) ($size[0] ?? 220),
            'height' => (int) ($size[1] ?? 48),
        ];
    }

    /**
     * @return array<string, string> filename => cid:...
     */
    public static function cidMap(?string $ctaFile = null): array
    {
        $map = [];
        foreach (self::TEMPLATE_FILES as $file) {
            $map[$file] = 'cid:'.pathinfo($file, PATHINFO_FILENAME);
        }
        if ($ctaFile !== null && $ctaFile !== '') {
            $map['cta-button.png'] = 'cid:'.pathinfo($ctaFile, PATHINFO_FILENAME);
            $map[$ctaFile] = 'cid:'.pathinfo($ctaFile, PATHINFO_FILENAME);
        }

        return $map;
    }

    /**
     * @return list<array{cid: string, name: string, path: string, content_type: string}>
     */
    public static function inlineImages(?string $ctaButtonPath = null, ?string $ctaButtonCid = null): array
    {
        $dir = self::directory();
        $out = [];
        foreach (self::TEMPLATE_FILES as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (! is_file($path)) {
                continue;
            }
            $out[] = [
                'cid' => pathinfo($file, PATHINFO_FILENAME),
                'name' => $file,
                'path' => $path,
                'content_type' => 'image/png',
            ];
        }

        if ($ctaButtonPath !== null && is_file($ctaButtonPath)) {
            $cid = $ctaButtonCid ?: pathinfo($ctaButtonPath, PATHINFO_FILENAME);
            $out[] = [
                'cid' => $cid,
                'name' => basename($ctaButtonPath),
                'path' => $ctaButtonPath,
                'content_type' => 'image/png',
            ];
        }

        return $out;
    }
}
