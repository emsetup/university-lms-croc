<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_progress')) {
            return;
        }
        if (Schema::hasColumn('module_progress', 'practice_m1_quest')) {
            return;
        }
        Schema::table('module_progress', function (Blueprint $table) {
            $table->json('practice_m1_quest')->nullable()->after('practice_lab_percent');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('module_progress')) {
            return;
        }
        if (Schema::hasColumn('module_progress', 'practice_m1_quest')) {
            Schema::table('module_progress', function (Blueprint $table) {
                $table->dropColumn('practice_m1_quest');
            });
        }
    }
};
