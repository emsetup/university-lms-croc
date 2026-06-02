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

        // SQLite на стенде иногда некорректно отвечает на hasColumn после alter table.
        // Делаем миграцию идемпотентной: повторное добавление столбца игнорируем.
        foreach ([
            'certificate_enabled' => static function (Blueprint $table): void {
                $table->boolean('certificate_enabled')->default(true)->after('final_lab_practice_image_id');
            },
            'certificate_title' => static function (Blueprint $table): void {
                $table->string('certificate_title', 200)->nullable()->after('certificate_enabled');
            },
            'certificate_tiers' => static function (Blueprint $table): void {
                $table->json('certificate_tiers')->nullable()->after('certificate_title');
            },
        ] as $col => $apply) {
            if (Schema::hasColumn('courses', $col)) {
                continue;
            }
            try {
                Schema::table('courses', $apply);
            } catch (\Throwable) {
                // ignore (duplicate column / already applied in another branch)
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            foreach (['certificate_tiers', 'certificate_title', 'certificate_enabled'] as $col) {
                if (Schema::hasColumn('courses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

