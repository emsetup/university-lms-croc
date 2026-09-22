<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_glossary_terms')) {
            return;
        }

        Schema::create('course_glossary_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('term', 120);
            $table->string('abbreviation', 64)->nullable();
            $table->text('definition');
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();

            $table->unique(['course_id', 'term']);
            $table->index(['course_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_glossary_terms');
    }
};
