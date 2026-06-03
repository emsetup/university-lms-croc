<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_staff_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('portal_staff_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_staff_group_id')->constrained('portal_staff_groups')->cascadeOnDelete();
            $table->foreignId('portal_staff_id')->constrained('portal_staff')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['portal_staff_group_id', 'portal_staff_id']);
        });

        Schema::create('portal_staff_group_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_staff_group_id')->constrained('portal_staff_groups')->cascadeOnDelete();
            $table->string('permission', 64);
            $table->timestamps();
            $table->unique(['portal_staff_group_id', 'permission']);
        });

        Schema::create('portal_staff_group_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_staff_group_id')->constrained('portal_staff_groups')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['portal_staff_group_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_staff_group_courses');
        Schema::dropIfExists('portal_staff_group_permissions');
        Schema::dropIfExists('portal_staff_group_members');
        Schema::dropIfExists('portal_staff_groups');
    }
};
