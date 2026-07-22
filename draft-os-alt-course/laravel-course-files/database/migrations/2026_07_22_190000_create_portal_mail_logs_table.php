<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_mail_logs')) {
            return;
        }

        Schema::create('portal_mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('status', 16)->default('pending');
            $table->string('to_email', 255);
            $table->string('to_name', 255)->nullable();
            $table->string('subject', 500);
            $table->mediumText('body_html');
            $table->mediumText('body_text')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('learner_id')->nullable()->constrained('learners')->nullOnDelete();
            $table->foreignId('sent_by_learner_id')->nullable()->constrained('learners')->nullOnDelete();
            $table->string('sent_by_email', 255)->nullable();
            $table->unsignedBigInteger('resend_of_id')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['to_email', 'created_at']);
            $table->index(['learner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_mail_logs');
    }
};
