<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->unsignedTinyInteger('module_id');
            $table->string('daemon_lab_id', 72)->nullable()->comment('UUID от lab-daemon');
            $table->string('status', 24)->default('none');
            $table->text('terminal_url')->nullable();
            $table->longText('last_check_log')->nullable();
            $table->boolean('last_check_passed')->nullable();
            $table->timestamp('last_check_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_sessions');
    }
};
