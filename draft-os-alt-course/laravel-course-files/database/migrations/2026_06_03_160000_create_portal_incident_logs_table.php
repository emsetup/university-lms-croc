<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_incident_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('severity', 16)->default('error');
            $table->string('summary', 500);
            $table->text('detail')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('http_method', 16)->nullable();
            $table->foreignId('learner_id')->nullable()->constrained('learners')->nullOnDelete();
            $table->string('user_email', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('occurred_at');
            $table->index(['occurred_at']);
            $table->index(['status_code', 'occurred_at']);
            $table->index(['learner_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_incident_logs');
    }
};
