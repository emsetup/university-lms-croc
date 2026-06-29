<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_content_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('portal_staff_id')->constrained('portal_staff')->cascadeOnDelete();
            $table->string('resource_type', 16); // course | module | section
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('permission', 16); // view | edit | manage
            $table->foreignId('granted_by_portal_staff_id')->nullable()->constrained('portal_staff')->nullOnDelete();
            $table->timestamps();

            $table->unique(['portal_staff_id', 'resource_type', 'resource_id'], 'course_content_grants_unique');
            $table->index(['course_id', 'portal_staff_id']);
            $table->index(['resource_type', 'resource_id']);
        });

        if (Schema::hasTable('courses') && ! Schema::hasColumn('courses', 'strict_grants')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->boolean('strict_grants')->default(true)->after('is_archived');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'strict_grants')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('strict_grants');
            });
        }

        Schema::dropIfExists('course_content_grants');
    }
};
