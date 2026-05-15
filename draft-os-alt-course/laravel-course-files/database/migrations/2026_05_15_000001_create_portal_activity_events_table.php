<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->nullable()->constrained('learners')->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('path', 255)->nullable();
            $table->timestamp('occurred_at');
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_activity_events');
    }
};
