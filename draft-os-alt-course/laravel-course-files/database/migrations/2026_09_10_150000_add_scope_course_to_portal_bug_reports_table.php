<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_bug_reports')) {
            return;
        }

        Schema::table('portal_bug_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('portal_bug_reports', 'scope')) {
                $table->string('scope', 32)->default('portal')->after('type');
                $table->index(['scope', 'created_at']);
            }
            if (! Schema::hasColumn('portal_bug_reports', 'course_id')) {
                $table->foreignId('course_id')->nullable()->after('scope')->constrained('courses')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('portal_bug_reports')) {
            return;
        }

        Schema::table('portal_bug_reports', function (Blueprint $table) {
            if (Schema::hasColumn('portal_bug_reports', 'course_id')) {
                $table->dropConstrainedForeignId('course_id');
            }
            if (Schema::hasColumn('portal_bug_reports', 'scope')) {
                $table->dropIndex(['scope', 'created_at']);
                $table->dropColumn('scope');
            }
        });
    }
};
