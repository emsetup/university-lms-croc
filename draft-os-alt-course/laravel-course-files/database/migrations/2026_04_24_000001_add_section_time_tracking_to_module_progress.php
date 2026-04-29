<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            if (! Schema::hasColumn('module_progress', 'seconds_theory')) {
                $table->unsignedInteger('seconds_theory')->default(0);
            }
            if (! Schema::hasColumn('module_progress', 'seconds_theory_quiz')) {
                $table->unsignedInteger('seconds_theory_quiz')->default(0);
            }
            if (! Schema::hasColumn('module_progress', 'seconds_practice')) {
                $table->unsignedInteger('seconds_practice')->default(0);
            }
            if (! Schema::hasColumn('module_progress', 'seconds_module_exam')) {
                $table->unsignedInteger('seconds_module_exam')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('module_progress')) {
            return;
        }
        $drops = [];
        foreach (['seconds_theory', 'seconds_theory_quiz', 'seconds_practice', 'seconds_module_exam'] as $col) {
            if (Schema::hasColumn('module_progress', $col)) {
                $drops[] = $col;
            }
        }
        if ($drops !== []) {
            Schema::table('module_progress', function (Blueprint $table) use ($drops) {
                $table->dropColumn($drops);
            });
        }
    }
};
