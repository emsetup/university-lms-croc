<?php

use App\Services\LegacyAltCourseContentBootstrap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_module_contents') && ! Schema::hasTable('course_quiz_banks')) {
            return;
        }

        LegacyAltCourseContentBootstrap::sync();
    }

    public function down(): void
    {
        // Не удаляем перенесённые данные: они могли редактироваться в БД.
    }
};
