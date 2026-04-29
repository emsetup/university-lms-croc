<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            $table->unsignedTinyInteger('practice_lab_percent')->nullable()->after('practice_done_at');
        });

        if (! Schema::hasTable('practice_sessions')) {
            return;
        }

        $sessions = DB::table('practice_sessions')
            ->whereNotNull('accepted_at')
            ->get(['learner_id', 'module_id', 'accepted_practice_score', 'last_check_max_score']);

        foreach ($sessions as $s) {
            if ($s->accepted_practice_score === null) {
                continue;
            }
            $pct = $this->computePercent(
                (int) $s->accepted_practice_score,
                $s->last_check_max_score !== null ? (int) $s->last_check_max_score : null
            );
            if ($pct === null) {
                continue;
            }
            DB::table('module_progress')
                ->where('learner_id', $s->learner_id)
                ->where('module_id', $s->module_id)
                ->whereNotNull('practice_done_at')
                ->update(['practice_lab_percent' => $pct]);
        }
    }

    public function down(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            $table->dropColumn('practice_lab_percent');
        });
    }

    private function computePercent(int $score, ?int $max): ?int
    {
        $max = $max ?? 100;
        if ($max <= 0) {
            return min(100, max(0, $score));
        }

        return min(100, max(0, (int) round(100 * $score / $max)));
    }
};
