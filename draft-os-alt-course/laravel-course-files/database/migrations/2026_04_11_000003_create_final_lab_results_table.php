<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->boolean('passed')->default(false);
            $table->unsignedSmallInteger('best_score')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('learner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_lab_results');
    }
};
