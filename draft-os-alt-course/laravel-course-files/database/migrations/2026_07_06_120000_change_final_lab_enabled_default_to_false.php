<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'final_lab_enabled')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE courses MODIFY final_lab_enabled TINYINT(1) NOT NULL DEFAULT 0');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE courses ALTER COLUMN final_lab_enabled SET DEFAULT false');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'final_lab_enabled')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE courses MODIFY final_lab_enabled TINYINT(1) NOT NULL DEFAULT 1');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE courses ALTER COLUMN final_lab_enabled SET DEFAULT true');
        }
    }
};
