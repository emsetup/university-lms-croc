<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleProgress extends Model
{
    protected $table = 'module_progress';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'course_id' => 'int',
            'course_module_id' => 'int',
            'theory_read_at' => 'datetime',
            'theory_quiz_passed' => 'boolean',
            'theory_quiz_last_result' => 'array',
            'theory_quiz_history' => 'array',
            'practice_done_at' => 'datetime',
            'practice_m1_quest' => 'array',
            'module_exam_passed' => 'boolean',
            'module_exam_last_result' => 'array',
            'module_exam_history' => 'array',
            'module_exam_deadline_at' => 'datetime',
            'difficulty_flags' => 'array',
            'instructor_resets' => 'array',
            'module_access_started_at' => 'datetime',
            'module_cleared_at' => 'datetime',
            'hub_briefing_acknowledged_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function syncModuleExamBestScoreFromLastResult(): void
    {
        $last = $this->module_exam_last_result;
        if (! is_array($last)) {
            return;
        }
        $pct = (int) ($last['final_percent'] ?? $last['raw_percent'] ?? 0);
        if ($pct > (int) $this->module_exam_best_score) {
            $this->module_exam_best_score = min(100, max(0, $pct));
        }
    }
}
