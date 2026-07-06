<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_learner_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#6366f1');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('portal_learner_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_learner_group_id')->constrained('portal_learner_groups')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['portal_learner_group_id', 'learner_id']);
        });

        Schema::create('course_learner_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#0ea5e9');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('course_learner_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_learner_group_id')->constrained('course_learner_groups')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_learner_group_id', 'learner_id']);
        });

        if (Schema::hasTable('courses') && ! Schema::hasColumn('courses', 'view_audience')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('view_audience', 16)->default('all');
            });
        }
        if (Schema::hasTable('course_modules') && ! Schema::hasColumn('course_modules', 'view_audience')) {
            Schema::table('course_modules', function (Blueprint $table) {
                $table->string('view_audience', 16)->default('all');
            });
        }
        if (Schema::hasTable('course_sections') && ! Schema::hasColumn('course_sections', 'view_audience')) {
            Schema::table('course_sections', function (Blueprint $table) {
                $table->string('view_audience', 16)->default('all');
            });
        }

        Schema::create('content_view_audience_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('resource_type', 16);
            $table->unsignedBigInteger('resource_id');
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();
            $table->unique(
                ['course_id', 'resource_type', 'resource_id', 'subject_type', 'subject_id'],
                'content_view_audience_rules_unique'
            );
            $table->index(['course_id', 'resource_type', 'resource_id'], 'content_view_audience_rules_resource');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_view_audience_rules');

        foreach (['course_sections', 'course_modules', 'courses'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'view_audience')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('view_audience');
                });
            }
        }

        Schema::dropIfExists('course_learner_group_members');
        Schema::dropIfExists('course_learner_groups');
        Schema::dropIfExists('portal_learner_group_members');
        Schema::dropIfExists('portal_learner_groups');
    }
};
