<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_bug_reports')) {
            return;
        }

        Schema::create('portal_bug_reports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->default('bug');
            $table->string('status', 32)->default('new');
            $table->string('title', 255)->nullable();
            $table->text('message');
            $table->string('page_url', 1000)->nullable();
            $table->string('page_title', 500)->nullable();
            $table->foreignId('learner_id')->nullable()->constrained('learners')->nullOnDelete();
            $table->string('user_email', 255)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('screenshots')->nullable();
            $table->unsignedTinyInteger('screenshot_count')->default(0);
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['learner_id', 'created_at']);
            $table->index(['user_email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_bug_reports');
    }
};
