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

        if (Schema::hasTable('course_share_links')) {
            return;
        }

        Schema::create('course_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id');
            $table->string('token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'is_active']);
            $table->index(['target_type', 'target_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_share_links');
    }
};
