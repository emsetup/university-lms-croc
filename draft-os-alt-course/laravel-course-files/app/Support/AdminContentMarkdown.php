<?php

namespace App\Support;

use Illuminate\Support\Str;
use Throwable;

/**
 * Markdown для админских превью (iframe): не роняет страницу при ошибке парсера/расширений.
 */
final class AdminContentMarkdown
{
    public static function toHtml(string $markdown): string
    {
        $markdown = str_replace("\0", '', $markdown);
        if ($markdown === '') {
            return '';
        }
        try {
            return Str::markdown($markdown);
        } catch (Throwable) {
            return '<p class="muted">Не удалось отрисовать разметку (ошибка конвертера Markdown).</p>'
                .'<pre class="check-log-pre" style="white-space:pre-wrap;word-break:break-word">'
                .e($markdown)
                .'</pre>';
        }
    }
}
