<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }
        if (! Schema::hasTable('course_modules')) {
            return;
        }

        if (! Schema::hasTable('course_quiz_banks')) {
            Schema::create('course_quiz_banks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('course_module_id')->nullable()->constrained('course_modules')->cascadeOnDelete();
                $table->string('kind', 32); // theory_quiz | module_exam | final_lab

                $table->unsignedSmallInteger('pass_percent')->default(70);
                $table->unsignedSmallInteger('time_limit_minutes')->nullable();
                $table->unsignedSmallInteger('attempt_limit')->nullable();
                $table->boolean('shuffle')->default(false);
                $table->boolean('one_by_one')->default(true);
                $table->unsignedSmallInteger('breakdown_visible_minutes')->default(15);
                $table->json('penalties_json')->nullable();

                $table->timestamps();

                $table->unique(['course_id', 'course_module_id', 'kind']);
                $table->index(['course_id', 'kind']);
            });
        }

        if (! Schema::hasTable('course_quiz_questions')) {
            Schema::create('course_quiz_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_bank_id')->constrained('course_quiz_banks')->cascadeOnDelete();
                $table->unsignedInteger('sort')->default(100);
                $table->text('question_text');
                $table->string('type', 24)->default('single'); // single|multi|match_drag
                $table->unsignedSmallInteger('points')->nullable();
                $table->timestamps();

                $table->index(['quiz_bank_id', 'sort']);
            });
        }

        if (! Schema::hasTable('course_quiz_options')) {
            Schema::create('course_quiz_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('question_id')->constrained('course_quiz_questions')->cascadeOnDelete();
                $table->unsignedInteger('sort')->default(100);
                $table->text('option_text');
                $table->timestamps();

                $table->index(['question_id', 'sort']);
            });
        }

        if (! Schema::hasTable('course_quiz_correct_answers')) {
            Schema::create('course_quiz_correct_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('question_id')->constrained('course_quiz_questions')->cascadeOnDelete();
                $table->foreignId('option_id')->constrained('course_quiz_options')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['question_id', 'option_id']);
                $table->index(['question_id']);
            });
        }

        if (! Schema::hasTable('course_quiz_match_pairs')) {
            Schema::create('course_quiz_match_pairs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('question_id')->constrained('course_quiz_questions')->cascadeOnDelete();
                $table->unsignedInteger('sort')->default(100);
                $table->text('left_text');
                $table->text('right_text');
                $table->timestamps();

                $table->index(['question_id', 'sort']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_quiz_match_pairs');
        Schema::dropIfExists('course_quiz_correct_answers');
        Schema::dropIfExists('course_quiz_options');
        Schema::dropIfExists('course_quiz_questions');
        Schema::dropIfExists('course_quiz_banks');
    }
};

