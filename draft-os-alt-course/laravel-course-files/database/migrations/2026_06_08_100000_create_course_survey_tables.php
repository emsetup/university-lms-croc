<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasTable('course_modules')) {
            return;
        }

        if (Schema::hasTable('course_quiz_questions') && ! Schema::hasColumn('course_quiz_questions', 'settings_json')) {
            Schema::table('course_quiz_questions', function (Blueprint $table) {
                $table->json('settings_json')->nullable()->after('points');
            });
        }

        if (! Schema::hasTable('course_survey_submissions')) {
            Schema::create('course_survey_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('course_module_id')->constrained('course_modules')->cascadeOnDelete();
                $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
                $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
                $table->timestamp('submitted_at');
                $table->timestamps();

                $table->unique(['course_section_id', 'learner_id']);
                $table->index(['course_section_id', 'submitted_at']);
            });
        }

        if (! Schema::hasTable('course_survey_answers')) {
            Schema::create('course_survey_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_id')->constrained('course_survey_submissions')->cascadeOnDelete();
                $table->foreignId('question_id')->constrained('course_quiz_questions')->cascadeOnDelete();
                $table->string('question_type', 24);
                $table->text('answer_text')->nullable();
                $table->json('answer_json')->nullable();
                $table->timestamps();

                $table->unique(['submission_id', 'question_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_survey_answers');
        Schema::dropIfExists('course_survey_submissions');
        if (Schema::hasTable('course_quiz_questions') && Schema::hasColumn('course_quiz_questions', 'settings_json')) {
            Schema::table('course_quiz_questions', function (Blueprint $table) {
                $table->dropColumn('settings_json');
            });
        }
    }
};
