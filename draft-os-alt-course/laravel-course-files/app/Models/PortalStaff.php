<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PortalStaff extends Model
{
    public const ROLE_PORTAL_ADMIN = 'portal_admin';

    public const ROLE_COURSE_MODERATOR = 'course_moderator';

    public const ROLE_COURSE_CREATOR = 'course_creator';

    public const ROLE_COURSE_EDITOR = 'course_editor';

    public const ROLE_INSTRUCTOR = 'instructor';

    public const ROLE_COURSE_TESTER = 'course_tester';

    /** @var list<string> */
    public const ROLES = [
        self::ROLE_PORTAL_ADMIN,
        self::ROLE_COURSE_MODERATOR,
        self::ROLE_COURSE_CREATOR,
        self::ROLE_COURSE_EDITOR,
        self::ROLE_INSTRUCTOR,
        self::ROLE_COURSE_TESTER,
    ];

    protected $table = 'portal_staff';

    protected $fillable = ['learner_id', 'role', 'access_comment'];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'portal_staff_course')
            ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(PortalStaffGroup::class, 'portal_staff_group_members')
            ->withTimestamps();
    }

    public function isPortalAdmin(): bool
    {
        return $this->role === self::ROLE_PORTAL_ADMIN;
    }

    public function isCourseModerator(): bool
    {
        return $this->role === self::ROLE_COURSE_MODERATOR;
    }

    public function isCourseCreator(): bool
    {
        return $this->role === self::ROLE_COURSE_CREATOR;
    }

    public function isCourseEditor(): bool
    {
        return $this->role === self::ROLE_COURSE_EDITOR;
    }

    public function isInstructor(): bool
    {
        return $this->role === self::ROLE_INSTRUCTOR;
    }

    public function isCourseTester(): bool
    {
        return $this->role === self::ROLE_COURSE_TESTER;
    }
}
