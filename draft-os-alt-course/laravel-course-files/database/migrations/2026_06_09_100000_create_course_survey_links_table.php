<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_sections')) {
            return;
        }

        if (Schema::hasTable('course_survey_links')) {
            return;
        }

        Schema::create('course_survey_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['course_section_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_survey_links');
    }
};
