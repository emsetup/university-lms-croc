<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\LearnerMediaPin;
use App\Models\MediaAsset;
use App\Models\PortalStaff;
use Illuminate\Support\Facades\Schema;

final class MediaAccessService
{
    public function __construct(
        private PortalStaffAccess $staffAccess
    ) {}

    public function currentLearnerId(): int
    {
        return (int) session('learner_id');
    }

    public function canUseMediaLibrary(): bool
    {
        $learnerId = $this->currentLearnerId();
        if ($learnerId <= 0) {
            return false;
        }

        if (! Schema::hasTable('portal_staff')) {
            return false;
        }

        $staff = PortalStaff::query()->where('learner_id', $learnerId)->first();
        if ($staff === null) {
            return false;
        }

        return $this->staffAccess->canUseCourseAdminTools();
    }

    public function assertCanUseMediaLibrary(): void
    {
        abort_unless($this->canUseMediaLibrary(), 403);
    }

    public function canUpload(?int $courseId): bool
    {
        if (! $this->canUseMediaLibrary()) {
            return false;
        }

        if ($courseId === null || $courseId <= 0) {
            return true;
        }

        return $this->staffAccess->canAccessCourseInAdmin($courseId);
    }

    public function assertCanUpload(?int $courseId): void
    {
        abort_unless($this->canUpload($courseId), 403);
    }

    public function canViewAsset(MediaAsset $asset): bool
    {
        $learnerId = $this->currentLearnerId();
        if ($learnerId <= 0) {
            return false;
        }

        if ((int) $asset->uploaded_by_learner_id === $learnerId) {
            return true;
        }

        if ($this->isStaffWithCourseAccess($asset)) {
            return true;
        }

        if ($this->isPinnedByLearner($asset, $learnerId)) {
            return true;
        }

        return $this->isEnrolledLearnerForCourse($asset, $learnerId);
    }

    public function assertCanViewAsset(MediaAsset $asset): void
    {
        abort_unless($this->canViewAsset($asset), 403);
    }

    public function canDeleteAsset(MediaAsset $asset): bool
    {
        if (! $this->canUseMediaLibrary()) {
            return false;
        }

        return (int) $asset->uploaded_by_learner_id === $this->currentLearnerId();
    }

    public function canPinAsset(MediaAsset $asset): bool
    {
        $learnerId = $this->currentLearnerId();
        if ($learnerId <= 0 || $asset->course_id === null) {
            return false;
        }

        if ((int) $asset->uploaded_by_learner_id === $learnerId) {
            return true;
        }

        return $this->staffAccess->canAccessCourseInAdmin((int) $asset->course_id);
    }

    private function isStaffWithCourseAccess(MediaAsset $asset): bool
    {
        if ($asset->course_id === null) {
            return $this->canUseMediaLibrary()
                && LearnerMediaPin::query()
                    ->where('learner_id', $this->currentLearnerId())
                    ->where('media_asset_id', (int) $asset->id)
                    ->exists();
        }

        return $this->canUseMediaLibrary()
            && $this->staffAccess->canAccessCourseInAdmin((int) $asset->course_id);
    }

    private function isPinnedByLearner(MediaAsset $asset, int $learnerId): bool
    {
        return LearnerMediaPin::query()
            ->where('learner_id', $learnerId)
            ->where('media_asset_id', (int) $asset->id)
            ->exists();
    }

    private function isEnrolledLearnerForCourse(MediaAsset $asset, int $learnerId): bool
    {
        if ($asset->course_id === null) {
            return false;
        }

        if (! Schema::hasTable('course_enrollments')) {
            return false;
        }

        return CourseEnrollment::query()
            ->where('learner_id', $learnerId)
            ->where('course_id', (int) $asset->course_id)
            ->exists();
    }
}
