<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Окно «Перед началом проверки» перед тестом/экзаменом.
 * По умолчанию выкл. (опросы без баллов). Вкл. для последовательных курсов с процентами (Альт и аналоги).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || Schema::hasColumn('courses', 'show_quiz_start_intro')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $after = Schema::hasColumn('courses', 'show_score_points')
                ? 'show_score_points'
                : (Schema::hasColumn('courses', 'assessment_enabled') ? 'assessment_enabled' : 'show_module_progress');
            $table->boolean('show_quiz_start_intro')->default(false)->after($after);
        });

        // Только legacy-курс Альт: у остальных новых/опросов окно выкл., включают вручную при последовательном тестировании с баллами.
        DB::table('courses')->where('slug', 'alt-os-features')->update(['show_quiz_start_intro' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'show_quiz_start_intro')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('show_quiz_start_intro');
        });
    }
};
