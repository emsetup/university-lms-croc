<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/** Эффективный обучающийся и курс при просмотре портала (?preview=…). */
final class LearnerPreviewContext
{
    public static function isActive(?Request $request = null): bool
    {
        return (int) self::request($request)->attributes->get('preview_learner_id', 0) > 0;
    }

    public static function learnerId(?Request $request = null): int
    {
        $request = self::request($request);
        $previewId = (int) $request->attributes->get('preview_learner_id', 0);

        return $previewId > 0 ? $previewId : (int) session('learner_id', 0);
    }

    public static function courseId(?Request $request = null): int
    {
        if (self::isActive($request)) {
            return (int) session(StaffImpersonation::SESSION_COURSE_ID, 0);
        }

        if (CourseStaffPreview::isActive($request)) {
            $fromSession = CourseStaffPreview::courseIdFromSession();

            return $fromSession > 0 ? $fromSession : CourseStaffPreview::previewCourseId($request);
        }

        return (int) session('course_id', 0);
    }

    public static function courseTitle(?Request $request = null): ?string
    {
        if (self::isActive($request)) {
            $title = session(StaffImpersonation::SESSION_COURSE_TITLE);

            return is_string($title) && $title !== '' ? $title : null;
        }

        if (CourseStaffPreview::isActive($request)) {
            return CourseStaffPreview::courseTitleFromSession();
        }

        $title = session('course_title');

        return is_string($title) && $title !== '' ? $title : null;
    }

    public static function selectCourse(int $courseId, ?string $courseTitle = null): void
    {
        if (self::isActive()) {
            $payload = [StaffImpersonation::SESSION_COURSE_ID => $courseId];
            if ($courseTitle !== null) {
                $payload[StaffImpersonation::SESSION_COURSE_TITLE] = $courseTitle;
            }
            session($payload);

            return;
        }

        if (CourseStaffPreview::isActive()) {
            CourseStaffPreview::selectCourse($courseId, $courseTitle);

            return;
        }

        $payload = ['course_id' => $courseId];
        if ($courseTitle !== null) {
            $payload['course_title'] = $courseTitle;
        }
        session($payload);
    }

    private static function request(?Request $request): Request
    {
        return $request ?? request();
    }
}
