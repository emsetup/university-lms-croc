<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_progress', function (Blueprint $table) {
            if (! Schema::hasColumn('module_progress', 'module_access_started_at')) {
                $table->timestamp('module_access_started_at')->nullable();
            }
            if (! Schema::hasColumn('module_progress', 'module_cleared_at')) {
                $table->timestamp('module_cleared_at')->nullable();
            }
            if (! Schema::hasColumn('module_progress', 'hub_briefing_acknowledged_at')) {
                $table->timestamp('hub_briefing_acknowledged_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('module_progress')) {
            return;
        }
        $drops = [];
        foreach (['module_access_started_at', 'module_cleared_at', 'hub_briefing_acknowledged_at'] as $c) {
            if (Schema::hasColumn('module_progress', $c)) {
                $drops[] = $c;
            }
        }
        if ($drops !== []) {
            Schema::table('module_progress', function (Blueprint $table) use ($drops) {
                $table->dropColumn($drops);
            });
        }
    }
};
