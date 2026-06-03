<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PortalStaffGroup extends Model
{
    protected $table = 'portal_staff_groups';

    protected $fillable = ['name', 'description', 'role', 'sort'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(PortalStaff::class, 'portal_staff_group_members')
            ->withTimestamps();
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'portal_staff_group_courses')
            ->withTimestamps();
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        return $this->permissions()
            ->orderBy('permission')
            ->pluck('permission')
            ->map(fn ($p) => (string) $p)
            ->values()
            ->all();
    }

    public function permissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PortalStaffGroupPermission::class, 'portal_staff_group_id');
    }
}
