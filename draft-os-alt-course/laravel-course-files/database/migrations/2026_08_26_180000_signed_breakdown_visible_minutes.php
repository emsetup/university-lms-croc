<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Разрешить -1 = разбор без ограничения по времени (раньше unsigned).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_quiz_banks') || ! Schema::hasColumn('course_quiz_banks', 'breakdown_visible_minutes')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE course_quiz_banks MODIFY breakdown_visible_minutes SMALLINT NOT NULL DEFAULT 15');

            return;
        }

        Schema::table('course_quiz_banks', function (Blueprint $table) {
            $table->smallInteger('breakdown_visible_minutes')->default(15)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_quiz_banks') || ! Schema::hasColumn('course_quiz_banks', 'breakdown_visible_minutes')) {
            return;
        }

        DB::table('course_quiz_banks')
            ->where('breakdown_visible_minutes', '<', 0)
            ->update(['breakdown_visible_minutes' => 15]);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE course_quiz_banks MODIFY breakdown_visible_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15');

            return;
        }

        Schema::table('course_quiz_banks', function (Blueprint $table) {
            $table->unsignedSmallInteger('breakdown_visible_minutes')->default(15)->change();
        });
    }
};
