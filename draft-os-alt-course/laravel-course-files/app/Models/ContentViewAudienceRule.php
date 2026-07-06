<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContentViewAudienceRule extends Model
{
    public const RESOURCE_COURSE = 'course';

    public const RESOURCE_MODULE = 'module';

    public const RESOURCE_SECTION = 'section';

    public const SUBJECT_LEARNER = 'learner';

    public const SUBJECT_PORTAL_GROUP = 'portal_group';

    public const SUBJECT_COURSE_GROUP = 'course_group';

    /** @var list<string> */
    public const RESOURCE_TYPES = [
        self::RESOURCE_COURSE,
        self::RESOURCE_MODULE,
        self::RESOURCE_SECTION,
    ];

    /** @var list<string> */
    public const SUBJECT_TYPES = [
        self::SUBJECT_LEARNER,
        self::SUBJECT_PORTAL_GROUP,
        self::SUBJECT_COURSE_GROUP,
    ];

    protected $fillable = [
        'course_id',
        'resource_type',
        'resource_id',
        'subject_type',
        'subject_id',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'int',
            'resource_id' => 'int',
            'subject_id' => 'int',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
