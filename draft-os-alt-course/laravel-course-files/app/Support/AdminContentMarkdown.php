<?php

namespace App\Support;

use Throwable;

/**
 * Markdown для админских превью (iframe): не роняет страницу при ошибке парсера/расширений.
 */
final class AdminContentMarkdown
{
    public static function toHtml(string $markdown): string
    {
        try {
            return CourseContentMarkdown::toHtml($markdown);
        } catch (Throwable) {
            $markdown = str_replace("\0", '', $markdown);

            return '<p class="muted">Не удалось отрисовать разметку (ошибка конвертера Markdown).</p>'
                .'<pre class="check-log-pre" style="white-space:pre-wrap;word-break:break-word">'
                .e($markdown)
                .'</pre>';
        }
    }
}
