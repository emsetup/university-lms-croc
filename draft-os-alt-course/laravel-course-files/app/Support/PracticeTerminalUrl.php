<?php

namespace App\Support;

/**
 * Веб-терминал (ttyd) на стенде слушает localhost:40xxx;
 * браузер ходит через HTTPS nginx: https://practice…/ttyd/40xxx/
 * Legacy URL http://IP:port/ даёт mixed content на HTTPS-портале.
 */
final class PracticeTerminalUrl
{
    public static function toHttpsProxy(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $trimmed = trim($url);
        if ($trimmed === '') {
            return $url;
        }

        if (preg_match('#^https?://[^/]+/ttyd/(\d+)/?\z#i', $trimmed, $m)
            || preg_match('#^/ttyd/(\d+)/?\z#i', $trimmed, $m)
        ) {
            return self::proxyUrl((int) $m[1]);
        }

        if (preg_match('#^https?://[^/:]+?:(\d+)/?\z#i', $trimmed, $m)) {
            $port = (int) $m[1];
            if (self::isTtyPort($port)) {
                return self::proxyUrl($port);
            }
        }

        return $url;
    }

    private static function isTtyPort(int $port): bool
    {
        $min = (int) env('PRACTICE_LAB_TTY_PORT_MIN', env('LAB_TTY_PORT_MIN', 40000));
        $max = (int) env('PRACTICE_LAB_TTY_PORT_MAX', env('LAB_TTY_PORT_MAX', 41000));

        return $port >= $min && $port <= $max;
    }

    private static function proxyUrl(int $port): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if ($base === '') {
            return '/ttyd/'.$port.'/';
        }

        return $base.'/ttyd/'.$port.'/';
    }
}
