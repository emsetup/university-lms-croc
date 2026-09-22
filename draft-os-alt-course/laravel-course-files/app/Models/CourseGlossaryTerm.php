<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseGlossaryTerm extends Model
{
    protected $fillable = [
        'course_id',
        'term',
        'abbreviation',
        'definition',
        'sort',
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

    /** Формы для поиска в тексте: термин и аббревиатура (если задана). */
    public function matchForms(): array
    {
        $forms = [];
        $term = trim((string) $this->term);
        if ($term !== '') {
            $forms[] = $term;
        }
        $abbr = trim((string) ($this->abbreviation ?? ''));
        if ($abbr !== '' && mb_strtolower($abbr) !== mb_strtolower($term)) {
            $forms[] = $abbr;
        }

        return $forms;
    }
}
