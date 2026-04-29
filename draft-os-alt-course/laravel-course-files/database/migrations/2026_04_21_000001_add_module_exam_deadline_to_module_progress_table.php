<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            $table->timestamp('module_exam_deadline_at')->nullable()->after('module_exam_last_result');
            $table->unsignedTinyInteger('module_exam_deadline_for_attempt')->nullable()->after('module_exam_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            $table->dropColumn(['module_exam_deadline_at', 'module_exam_deadline_for_attempt']);
        });
    }
};
