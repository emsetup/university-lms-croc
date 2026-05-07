<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_modules')) {
            return;
        }

        if (Schema::hasTable('course_module_contents')) {
            return;
        }

        Schema::create('course_module_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_module_id')
                ->constrained('course_modules')
                ->cascadeOnDelete();
            $table->longText('theory_markdown')->default('');
            $table->longText('practice_markdown')->default('');
            $table->timestamps();

            $table->unique('course_module_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_module_contents');
    }
};

