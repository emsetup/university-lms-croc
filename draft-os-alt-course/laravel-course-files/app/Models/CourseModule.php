<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class CourseModule extends Model
{
    public const VIEW_AUDIENCE_ALL = 'all';

    public const VIEW_AUDIENCE_RESTRICTED = 'restricted';

    protected $fillable = [
        'course_id',
        'sort',
        'title',
        'summary',
        'letter',
        'content_source_index',
        'view_audience',
        'show_score_percents',
        'show_score_points',
        'quiz_breakdown_mode',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'int',
            'sort' => 'int',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class, 'course_module_id')->orderBy('sort')->orderBy('id');
    }

    public function practiceSetting(): HasOne
    {
        return $this->hasOne(CourseModulePracticeSetting::class, 'course_module_id');
    }

    /**
     * Индекс контента в config/course.php и файлах вопросов/теории.
     */
    public function effectiveContentIndex(): int
    {
        $v = $this->content_source_index;

        return is_numeric($v) && (int) $v > 0 ? (int) $v : 1;
    }
}
