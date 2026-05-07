<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Learner extends Model
{
    protected $fillable = ['email'];

    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function moduleProgresses(): HasMany
    {
        return $this->hasMany(ModuleProgress::class);
    }

    public function finalLabResult(): HasOne
    {
        $courseId = (int) session('course_id', 0);
        $q = $this->hasOne(FinalLabResult::class);
        if ($courseId > 0) {
            $q->where('course_id', $courseId);
        }

        return $q;
    }

    public function finalLabResults(): HasMany
    {
        return $this->hasMany(FinalLabResult::class);
    }

    /**
     * Только существующая запись (без INSERT). Для отображения и отчётов по заблокированным модулям.
     */
    public function progressExisting(int $courseModuleId, ?int $courseId = null): ?ModuleProgress
    {
        $courseId = $courseId ?? (int) session('course_id', 0);
        if ($this->relationLoaded('moduleProgresses')) {
            return $this->moduleProgresses->first(function (ModuleProgress $p) use ($courseId, $courseModuleId) {
                return (int) $p->course_id === $courseId && (int) $p->course_module_id === $courseModuleId;
            });
        }

        return $this->moduleProgresses()
            ->where('course_id', $courseId)
            ->where('course_module_id', $courseModuleId)
            ->first();
    }

    public function progressFor(int $courseModuleId, ?int $courseId = null): ModuleProgress
    {
        $courseId = $courseId ?? (int) session('course_id', 0);
        $defaults = [
            'difficulty_flags' => [
                'theory' => false,
                'theory_quiz' => false,
                'practice' => false,
                'module_exam' => false,
            ],
        ];

        return $this->moduleProgresses()->firstOrCreate(
            ['course_id' => $courseId, 'course_module_id' => $courseModuleId],
            $defaults
        );
    }
}
