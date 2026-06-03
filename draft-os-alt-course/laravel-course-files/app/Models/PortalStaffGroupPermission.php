<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalStaffGroupPermission extends Model
{
    protected $table = 'portal_staff_group_permissions';

    protected $fillable = ['portal_staff_group_id', 'permission'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PortalStaffGroup::class, 'portal_staff_group_id');
    }
}
