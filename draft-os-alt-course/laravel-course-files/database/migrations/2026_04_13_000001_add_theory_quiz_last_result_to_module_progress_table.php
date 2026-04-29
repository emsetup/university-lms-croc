<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            $table->json('theory_quiz_last_result')->nullable()->after('theory_quiz_best_score');
        });
    }

    public function down(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            $table->dropColumn('theory_quiz_last_result');
        });
    }
};
