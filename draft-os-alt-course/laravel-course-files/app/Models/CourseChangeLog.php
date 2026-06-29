<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseChangeLog extends Model
{
    protected $fillable = [
        'course_id',
        'portal_staff_id',
        'action',
        'area',
        'summary',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function portalStaff(): BelongsTo
    {
        return $this->belongsTo(PortalStaff::class);
    }
}
