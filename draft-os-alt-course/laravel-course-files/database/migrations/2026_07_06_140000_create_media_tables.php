<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learners')) {
            return;
        }

        if (! Schema::hasTable('media_assets')) {
            Schema::create('media_assets', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('uploaded_by_learner_id')->constrained('learners')->cascadeOnDelete();
                $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
                $table->string('storage_path', 512);
                $table->string('thumb_path', 512)->nullable();
                $table->string('original_filename', 255);
                $table->string('mime', 64)->default('image/webp');
                $table->unsignedSmallInteger('width')->default(0);
                $table->unsignedSmallInteger('height')->default(0);
                $table->unsignedInteger('bytes')->default(0);
                $table->timestamps();

                $table->index(['course_id', 'created_at']);
                $table->index(['uploaded_by_learner_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('learner_media_pins')) {
            Schema::create('learner_media_pins', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
                $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
                $table->string('source', 24)->default('upload'); // upload | course_import
                $table->timestamps();

                $table->unique(['learner_id', 'media_asset_id']);
                $table->index(['learner_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_media_pins');
        Schema::dropIfExists('media_assets');
    }
};
