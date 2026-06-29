<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_staff_id')
                ->nullable()
                ->constrained('portal_staff')
                ->nullOnDelete();
            $table->string('action', 64);
            $table->string('area', 32)->default('course');
            $table->string('summary', 500);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_change_logs');
    }
};
