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

        if (! Schema::hasTable('practice_images')) {
            Schema::create('practice_images', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('docker_tag');
                $table->string('base_template')->default('');

                $table->longText('dockerfile_text')->default('');
                $table->longText('check_script_text')->default('');

                $table->boolean('is_built')->default(false);
                $table->string('last_build_status')->nullable(); // ok|fail|running
                $table->longText('last_build_log')->nullable();
                $table->timestamp('last_built_at')->nullable();

                $table->string('export_path')->nullable();
                $table->timestamps();

                $table->index(['is_built', 'updated_at']);
            });
        }

        if (! Schema::hasTable('course_module_practice_settings')) {
            Schema::create('course_module_practice_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_module_id')->constrained('course_modules')->cascadeOnDelete();
                $table->foreignId('practice_image_id')->nullable()->constrained('practice_images')->nullOnDelete();
                $table->unsignedSmallInteger('daemon_image_key_override')->nullable();
                $table->timestamps();
                $table->unique(['course_module_id']);
                $table->index(['practice_image_id']);
            });
        }
    }

    public function down(): void
    {
        // Откат безопасен: это новые таблицы.
        Schema::dropIfExists('course_module_practice_settings');
        Schema::dropIfExists('practice_images');
    }
};

