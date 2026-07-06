<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'default_attempt_limit')) {
                $table->unsignedSmallInteger('default_attempt_limit')->nullable()->after('sort');
            }
            if (! Schema::hasColumn('courses', 'default_quiz_time_minutes')) {
                $table->unsignedSmallInteger('default_quiz_time_minutes')->nullable()->after('default_attempt_limit');
            }
            if (! Schema::hasColumn('courses', 'default_pass_percent')) {
                $table->unsignedTinyInteger('default_pass_percent')->nullable()->after('default_quiz_time_minutes');
            }
            if (! Schema::hasColumn('courses', 'final_lab_enabled')) {
                $table->boolean('final_lab_enabled')->default(false)->after('default_pass_percent');
            }
            if (! Schema::hasColumn('courses', 'final_lab_practice_image_id')) {
                $table->unsignedBigInteger('final_lab_practice_image_id')->nullable()->after('final_lab_enabled');
            }
        });

        if (Schema::hasTable('practice_images') && Schema::hasColumn('courses', 'final_lab_practice_image_id')) {
            try {
                Schema::table('courses', function (Blueprint $table) {
                    $table->foreign('final_lab_practice_image_id')
                        ->references('id')
                        ->on('practice_images')
                        ->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK уже есть (повторный прогон / другая ветка миграций).
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'final_lab_practice_image_id')) {
                try {
                    $table->dropForeign(['final_lab_practice_image_id']);
                } catch (\Throwable) {
                    // ignore
                }
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            foreach ([
                'final_lab_practice_image_id',
                'final_lab_enabled',
                'default_pass_percent',
                'default_quiz_time_minutes',
                'default_attempt_limit',
            ] as $col) {
                if (Schema::hasColumn('courses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
