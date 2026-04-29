<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->unsignedTinyInteger('module_id');
            $table->timestamp('theory_read_at')->nullable();
            $table->unsignedSmallInteger('theory_quiz_attempts')->default(0);
            $table->boolean('theory_quiz_passed')->default(false);
            $table->unsignedTinyInteger('theory_quiz_best_score')->default(0);
            $table->timestamp('practice_done_at')->nullable();
            $table->unsignedSmallInteger('module_exam_attempts')->default(0);
            $table->boolean('module_exam_passed')->default(false);
            $table->unsignedTinyInteger('module_exam_best_score')->default(0);
            $table->json('difficulty_flags')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_progress');
    }
};
