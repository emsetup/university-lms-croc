<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\PortalIncidentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PortalIncidentLogger
{
    private const DETAIL_MAX = 65535;

    private const THROTTLE_SECONDS = 60;

    public static function recordHttp(Request $request, Response $response, ?Throwable $throwable = null): void
    {
        if (! Schema::hasTable('portal_incident_logs')) {
            return;
        }

        $status = $response->getStatusCode();
        if (! self::shouldLogHttp($request, $status)) {
            return;
        }

        $summary = self::httpSummary($request, $response, $status, $throwable);
        $throttleKey = 'portal_incident:'.md5($status.'|'.$request->path().'|'.$summary);
        if (! Cache::add($throttleKey, 1, now()->addSeconds(self::THROTTLE_SECONDS))) {
            return;
        }

        $detail = self::buildDetail($request, $response, $throwable);

        self::insert(
            source: $throwable !== null ? PortalIncidentLog::SOURCE_EXCEPTION : PortalIncidentLog::SOURCE_HTTP,
            statusCode: $status,
            severity: $status >= 500 ? PortalIncidentLog::SEVERITY_ERROR : PortalIncidentLog::SEVERITY_WARNING,
            summary: $summary,
            detail: $detail,
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function recordClient(Request $request, array $payload): void
    {
        if (! Schema::hasTable('portal_incident_logs')) {
            return;
        }

        $message = trim((string) ($payload['message'] ?? 'Ошибка в браузере'));
        $summary = Str::limit($message !== '' ? $message : 'Ошибка в браузере', 500, '');
        $throttleKey = 'portal_incident:client:'.md5($summary.'|'.($payload['url'] ?? ''));
        if (! Cache::add($throttleKey, 1, now()->addSeconds(self::THROTTLE_SECONDS))) {
            return;
        }

        $detail = json_encode([
            'stack' => (string) ($payload['stack'] ?? ''),
            'line' => $payload['line'] ?? null,
            'column' => $payload['column'] ?? null,
            'filename' => $payload['filename'] ?? null,
            'page_url' => $payload['url'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        self::insert(
            source: PortalIncidentLog::SOURCE_CLIENT,
            statusCode: null,
            severity: PortalIncidentLog::SEVERITY_ERROR,
            summary: $summary,
            detail: $detail !== false ? $detail : null,
            request: $request,
            urlOverride: isset($payload['url']) ? (string) $payload['url'] : null,
        );
    }

    private static function shouldLogHttp(Request $request, int $status): bool
    {
        if ($status < 400 || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return false;
        }

        if ($request->routeIs('admin.incidents.*', 'portal.incident.store')) {
            return false;
        }

        $path = '/'.trim($request->path(), '/');
        if (self::isStaticAssetPath($path)) {
            return false;
        }

        if ($status === 404 && self::isBenignNotFound($path)) {
            return false;
        }

        return true;
    }

    private static function isStaticAssetPath(string $path): bool
    {
        return (bool) preg_match('#\.(css|js|map|ico|png|jpe?g|gif|webp|svg|woff2?|ttf|eot)$#i', $path);
    }

    private static function isBenignNotFound(string $path): bool
    {
        return in_array($path, ['/favicon.ico', '/robots.txt', '/apple-touch-icon.png'], true)
            || str_starts_with($path, '/vendor/')
            || str_starts_with($path, '/build/');
    }

    private static function httpSummary(Request $request, Response $response, int $status, ?Throwable $throwable): string
    {
        if ($throwable !== null) {
            return Str::limit($throwable::class.': '.$throwable->getMessage(), 500, '');
        }

        $phrase = Response::$statusTexts[$status] ?? 'Ошибка';
        $path = '/'.trim($request->path(), '/');

        return Str::limit("HTTP {$status} {$phrase} — {$path}", 500, '');
    }

    private static function buildDetail(Request $request, Response $response, ?Throwable $throwable): ?string
    {
        $parts = [
            'request' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'query' => $request->query(),
                'route' => $request->route()?->getName(),
            ],
            'response' => [
                'status' => $response->getStatusCode(),
                'content_type' => $response->headers->get('Content-Type'),
            ],
            'session' => [
                'learner_id' => session('learner_id'),
                'course_id' => session('course_id'),
            ],
        ];

        if ($throwable !== null) {
            $parts['exception'] = [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ];
        }

        $body = json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($body === false) {
            return null;
        }

        return Str::limit($body, self::DETAIL_MAX, "\n… [обрезано]");
    }

    private static function insert(
        string $source,
        ?int $statusCode,
        string $severity,
        string $summary,
        ?string $detail,
        Request $request,
        ?string $urlOverride = null,
    ): void {
        $learnerId = (int) session('learner_id', 0);
        $email = null;
        if ($learnerId > 0) {
            $email = Learner::query()->whereKey($learnerId)->value('email');
        }

        PortalIncidentLog::query()->create([
            'source' => $source,
            'status_code' => $statusCode,
            'severity' => $severity,
            'summary' => $summary,
            'detail' => $detail,
            'url' => Str::limit($urlOverride ?? $request->fullUrl(), 500, ''),
            'http_method' => $request->method(),
            'learner_id' => $learnerId > 0 ? $learnerId : null,
            'user_email' => $email !== null ? (string) $email : null,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'occurred_at' => now(),
        ]);
    }
}
