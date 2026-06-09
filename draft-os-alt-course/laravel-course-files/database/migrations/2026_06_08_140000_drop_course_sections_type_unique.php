<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_sections')) {
            return;
        }

        Schema::table('course_sections', function (Blueprint $table) {
            $table->dropUnique(['course_module_id', 'type']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_sections')) {
            return;
        }

        Schema::table('course_sections', function (Blueprint $table) {
            $table->unique(['course_module_id', 'type']);
        });
    }
};
