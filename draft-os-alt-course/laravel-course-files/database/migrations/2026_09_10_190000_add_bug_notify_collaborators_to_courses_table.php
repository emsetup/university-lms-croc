<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Уведомления о баг-репортах по курсу: по умолчанию только автору;
 * при включении — ещё и соавторам (course_content_grants).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || Schema::hasColumn('courses', 'bug_notify_collaborators')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $after = Schema::hasColumn('courses', 'show_quiz_start_intro')
                ? 'show_quiz_start_intro'
                : (Schema::hasColumn('courses', 'assessment_enabled') ? 'assessment_enabled' : 'title');
            $table->boolean('bug_notify_collaborators')->default(false)->after($after);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'bug_notify_collaborators')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('bug_notify_collaborators');
        });
    }
};
