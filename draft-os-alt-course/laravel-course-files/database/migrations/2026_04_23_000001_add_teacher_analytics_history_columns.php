<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            if (! Schema::hasColumn('module_progress', 'theory_quiz_history')) {
                $table->json('theory_quiz_history')->nullable();
            }
            if (! Schema::hasColumn('module_progress', 'module_exam_history')) {
                $table->json('module_exam_history')->nullable();
            }
        });

        if (Schema::hasTable('practice_sessions') && ! Schema::hasColumn('practice_sessions', 'terminal_snapshots')) {
            Schema::table('practice_sessions', function (Blueprint $table) {
                $table->json('terminal_snapshots')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('module_progress', 'theory_quiz_history')) {
                $drops[] = 'theory_quiz_history';
            }
            if (Schema::hasColumn('module_progress', 'module_exam_history')) {
                $drops[] = 'module_exam_history';
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });

        if (Schema::hasTable('practice_sessions') && Schema::hasColumn('practice_sessions', 'terminal_snapshots')) {
            Schema::table('practice_sessions', function (Blueprint $table) {
                $table->dropColumn('terminal_snapshots');
            });
        }
    }
};
