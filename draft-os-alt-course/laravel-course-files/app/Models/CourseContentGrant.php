<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseContentGrant extends Model
{
    public const RESOURCE_COURSE = 'course';

    public const RESOURCE_MODULE = 'module';

    public const RESOURCE_SECTION = 'section';

    public const PERMISSION_VIEW = 'view';

    public const PERMISSION_EDIT = 'edit';

    public const PERMISSION_MANAGE = 'manage';

    /** @var list<string> */
    public const RESOURCE_TYPES = [
        self::RESOURCE_COURSE,
        self::RESOURCE_MODULE,
        self::RESOURCE_SECTION,
    ];

    /** @var list<string> */
    public const PERMISSIONS = [
        self::PERMISSION_VIEW,
        self::PERMISSION_EDIT,
        self::PERMISSION_MANAGE,
    ];

    protected $fillable = [
        'course_id',
        'portal_staff_id',
        'resource_type',
        'resource_id',
        'permission',
        'granted_by_portal_staff_id',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'int',
            'portal_staff_id' => 'int',
            'resource_id' => 'int',
            'granted_by_portal_staff_id' => 'int',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function portalStaff(): BelongsTo
    {
        return $this->belongsTo(PortalStaff::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(PortalStaff::class, 'granted_by_portal_staff_id');
    }

    public static function permissionRank(string $permission): int
    {
        return match ($permission) {
            self::PERMISSION_MANAGE => 3,
            self::PERMISSION_EDIT => 2,
            self::PERMISSION_VIEW => 1,
            default => 0,
        };
    }

    public static function permissionFromRank(int $rank): string
    {
        return match (true) {
            $rank >= 3 => self::PERMISSION_MANAGE,
            $rank >= 2 => self::PERMISSION_EDIT,
            $rank >= 1 => self::PERMISSION_VIEW,
            default => self::PERMISSION_VIEW,
        };
    }
}
