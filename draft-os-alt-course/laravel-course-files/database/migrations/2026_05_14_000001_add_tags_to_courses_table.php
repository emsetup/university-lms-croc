<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }
        if (! Schema::hasColumn('courses', 'tags')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->json('tags')->nullable()->after('summary');
            });
        }

        if (Schema::hasColumn('courses', 'tags')) {
            DB::table('courses')
                ->where('slug', 'alt-os-features')
                ->whereNull('tags')
                ->update([
                    'tags' => json_encode(['Linux', 'Администрирование', 'Сети', 'Безопасность'], JSON_UNESCAPED_UNICODE),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'tags')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('tags');
            });
        }
    }
};
