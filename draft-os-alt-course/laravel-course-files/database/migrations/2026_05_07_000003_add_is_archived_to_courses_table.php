<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || Schema::hasColumn('courses', 'is_archived')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('is_published');
            $table->index(['is_archived', 'sort']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'is_archived')) {
            return;
        }
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['is_archived', 'sort']);
            $table->dropColumn('is_archived');
        });
    }
};

