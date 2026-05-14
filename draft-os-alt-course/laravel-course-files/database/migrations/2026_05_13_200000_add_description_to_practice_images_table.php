<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('practice_images')) {
            return;
        }
        if (! Schema::hasColumn('practice_images', 'description')) {
            Schema::table('practice_images', function (Blueprint $table) {
                $table->text('description')->nullable()->after('title');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('practice_images')) {
            return;
        }
        if (Schema::hasColumn('practice_images', 'description')) {
            Schema::table('practice_images', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
