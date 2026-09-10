<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ad_mail_groups')) {
            return;
        }

        Schema::create('ad_mail_groups', function (Blueprint $table) {
            $table->id();
            $table->string('object_guid', 64)->nullable()->unique();
            $table->string('cn', 255)->nullable();
            $table->string('display_name', 255)->nullable();
            $table->string('mail', 255);
            $table->string('sam_account_name', 255)->nullable();
            $table->string('dn', 1024)->nullable();
            $table->integer('group_type')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique('mail');
            $table->index(['is_active', 'display_name']);
            $table->index(['is_active', 'mail']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_mail_groups');
    }
};
