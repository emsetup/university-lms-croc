<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Learner extends Model
{
    protected $fillable = ['email'];

    public function moduleProgresses(): HasMany
    {
        return $this->hasMany(ModuleProgress::class);
    }

    public function finalLabResult(): HasOne
    {
        return $this->hasOne(FinalLabResult::class);
    }

    /**
     * Только существующая запись (без INSERT). Для отображения и отчётов по заблокированным модулям.
     */
    public function progressExisting(int $moduleId): ?ModuleProgress
    {
        if ($this->relationLoaded('moduleProgresses')) {
            return $this->moduleProgresses->firstWhere('module_id', $moduleId);
        }

        return $this->moduleProgresses()->where('module_id', $moduleId)->first();
    }

    public function progressFor(int $moduleId): ModuleProgress
    {
        return $this->moduleProgresses()->firstOrCreate(
            ['module_id' => $moduleId],
            ['difficulty_flags' => [
                'theory' => false,
                'theory_quiz' => false,
                'practice' => false,
                'module_exam' => false,
            ]]
        );
    }
}
