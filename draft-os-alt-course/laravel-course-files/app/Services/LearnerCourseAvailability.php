<?php

namespace App\Services;

use App\Models\Course;
use App\Models\FinalLabResult;
use App\Models\Learner;

final class LearnerCourseAvailability
{
    public static function isOpenForLearning(Course $course): bool
    {
        return $course->is_published && ! $course->is_archived;
    }

    public static function learnerHasIssuedCertificate(Learner $learner, int $courseId): bool
    {
        if ($courseId < 1) {
            return false;
        }

        $final = $learner->finalLabResults
            ->first(fn (FinalLabResult $r) => (int) ($r->course_id ?? 0) === $courseId);

        if ($final === null && $learner->relationLoaded('finalLabResults')) {
            $final = FinalLabResult::query()
                ->where('learner_id', $learner->id)
                ->where('course_id', $courseId)
                ->first();
        }

        return $final !== null
            && ! empty($final->certificate_full_name)
            && ! empty($final->certificate_serial);
    }
}
