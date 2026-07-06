<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Course;
use App\Services\PortalStaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Предпросмотр курса сотрудником через learner-трек (?course_preview=…).
 * Отдельная сессия курса; черновики и архив доступны; блокировки прогресса сняты.
 */
final class CourseStaffPreview
{
    public const QUERY_PARAM = 'course_preview';

    public const SESSION_TOKEN = 'course_staff_preview_token';

    public const SESSION_COURSE_ID = 'course_staff_preview_course_id';

    public const SESSION_COURSE_TITLE = 'course_staff_preview_course_title';

    private const CACHE_PREFIX = 'portal_course_staff_preview:';

    private const TTL_HOURS = 8;

    public static function createPreviewToken(int $staffLearnerId, int $courseId): string
    {
        $token = bin2hex(random_bytes(16));
        Cache::put(self::CACHE_PREFIX.$token, [
            'staff_learner_id' => $staffLearnerId,
            'course_id' => $courseId,
        ], now()->addHours(self::TTL_HOURS));

        return $token;
    }

    /**
     * @return array{staff_learner_id: int, course_id: int}|null
     */
    public static function resolvePreview(?string $token): ?array
    {
        if ($token === null || $token === '' || ! preg_match('/^[a-f0-9]{32}$/D', $token)) {
            return null;
        }

        $data = Cache::get(self::CACHE_PREFIX.$token);
        if (! is_array($data)) {
            return null;
        }

        $staffId = (int) ($data['staff_learner_id'] ?? 0);
        $courseId = (int) ($data['course_id'] ?? 0);
        if ($staffId <= 0 || $courseId <= 0) {
            return null;
        }

        $access = PortalStaffAccess::fromLearnerId($staffId);
        if ($access === null || ! $access->canPreviewCourse($courseId)) {
            return null;
        }

        if (Course::query()->whereKey($courseId)->doesntExist()) {
            return null;
        }

        return [
            'staff_learner_id' => $staffId,
            'course_id' => $courseId,
        ];
    }

    public static function previewTokenFromRequest(Request $request): ?string
    {
        $t = $request->query(self::QUERY_PARAM);

        return is_string($t) && $t !== '' ? $t : null;
    }

    public static function activeToken(?Request $request = null): ?string
    {
        $request = $request ?? request();
        $fromQuery = self::previewTokenFromRequest($request);
        if ($fromQuery !== null) {
            return $fromQuery;
        }

        $fromSession = session(self::SESSION_TOKEN);

        return is_string($fromSession) && $fromSession !== '' ? $fromSession : null;
    }

    public static function persistToken(string $token): void
    {
        session([self::SESSION_TOKEN => $token]);
    }

    public static function selectCourse(int $courseId, ?string $courseTitle = null): void
    {
        $payload = [self::SESSION_COURSE_ID => $courseId];
        if ($courseTitle !== null) {
            $payload[self::SESSION_COURSE_TITLE] = $courseTitle;
        }
        session($payload);
    }

    public static function clearSession(): void
    {
        session()->forget([
            self::SESSION_TOKEN,
            self::SESSION_COURSE_ID,
            self::SESSION_COURSE_TITLE,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function routeQueryParams(?Request $request = null): array
    {
        $token = self::activeToken($request);

        return $token !== null ? [self::QUERY_PARAM => $token] : [];
    }

    public static function isPreviewRequest(?Request $request = null): bool
    {
        return self::resolvePreview(self::activeToken($request)) !== null;
    }

    public static function isActive(?Request $request = null): bool
    {
        $request = $request ?? request();

        return (bool) $request->attributes->get('course_staff_preview_active', false);
    }

    public static function courseIdFromSession(): int
    {
        return (int) session(self::SESSION_COURSE_ID, 0);
    }

    public static function courseTitleFromSession(): ?string
    {
        $title = session(self::SESSION_COURSE_TITLE);

        return is_string($title) && $title !== '' ? $title : null;
    }

    public static function previewCourseId(?Request $request = null): int
    {
        $ctx = self::resolvePreview(self::activeToken($request));

        return $ctx !== null ? (int) $ctx['course_id'] : 0;
    }

    public static function isActiveForCourse(int $courseId, ?Request $request = null): bool
    {
        if (! self::isActive($request)) {
            return false;
        }

        $previewCourseId = self::previewCourseId($request);
        if ($previewCourseId > 0) {
            return $previewCourseId === $courseId;
        }

        return self::courseIdFromSession() === $courseId;
    }

    public static function assertCanPreview(Course $course, int $staffLearnerId): void
    {
        $access = PortalStaffAccess::fromLearnerId($staffLearnerId);
        abort_unless($access !== null && $access->canPreviewCourse((int) $course->id), 403);
    }
}
