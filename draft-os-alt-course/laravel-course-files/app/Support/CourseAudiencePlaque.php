<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Course;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CourseAudiencePlaque
{
    /**
     * @return array{
     *   kicker:string,
     *   title:string,
     *   teaser:string,
     *   bodyHtml:?string,
     *   hasModal:bool
     * }|null
     */
    public static function forCourse(?Course $course): ?array
    {
        if ($course === null || ! self::isEnabledForCourse($course)) {
            return null;
        }

        $teaser = trim((string) ($course->audience_plaque_teaser ?? ''));
        $body = trim((string) ($course->audience_plaque_body ?? ''));
        if ($teaser === '' && $body === '') {
            return null;
        }

        $kicker = trim((string) ($course->audience_plaque_kicker ?? ''));
        $title = trim((string) ($course->audience_plaque_title ?? ''));

        return [
            'kicker' => $kicker !== '' ? $kicker : 'О курсе',
            'title' => $title !== '' ? $title : 'Для кого этот материал',
            'teaser' => $teaser,
            'bodyHtml' => $body !== '' ? Str::markdown($body) : null,
            'hasModal' => $body !== '',
        ];
    }

    public static function isEnabledForCourse(Course $course): bool
    {
        if (! Schema::hasColumn('courses', 'audience_plaque_enabled')) {
            return $course->slug === 'alt-os-features';
        }

        return (bool) $course->audience_plaque_enabled;
    }
}
