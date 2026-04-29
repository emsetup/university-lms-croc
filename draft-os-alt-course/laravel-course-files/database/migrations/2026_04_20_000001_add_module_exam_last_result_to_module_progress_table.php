<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            $table->json('module_exam_last_result')->nullable()->after('module_exam_best_score');
        });
    }

    public function down(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            $table->dropColumn('module_exam_last_result');
        });
    }
};
