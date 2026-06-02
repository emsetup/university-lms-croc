<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PracticeImage;
use App\Services\PortalStaffAccess;
use Illuminate\Database\Eloquent\Builder;

trait ScopesPracticeImagesForStaff
{
    protected function portalStaffGate(): PortalStaffAccess
    {
        return app(PortalStaffAccess::class);
    }

    /** @param  Builder<PracticeImage>  $query */
    protected function scopePracticeImagesForStaff(Builder $query): Builder
    {
        return $this->portalStaffGate()->scopePracticeImagesForStaff($query);
    }

    protected function assertCanEditPracticeImage(PracticeImage $row): void
    {
        $this->portalStaffGate()->assertCanEditPracticeImage($row);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function withPracticeImageOwner(array $attributes): array
    {
        $ownerId = $this->portalStaffGate()->practiceImageOwnerStaffId();
        if ($ownerId !== null) {
            $attributes['created_by_portal_staff_id'] = $ownerId;
        }

        return $attributes;
    }
}
