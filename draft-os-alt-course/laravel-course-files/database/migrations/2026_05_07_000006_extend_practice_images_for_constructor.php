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

        Schema::table('practice_images', function (Blueprint $table) {
            if (! Schema::hasColumn('practice_images', 'base_os')) {
                $table->string('base_os', 24)->default('alt')->index();
            }
            if (! Schema::hasColumn('practice_images', 'base_image_ref')) {
                $table->string('base_image_ref')->default('');
            }
            if (! Schema::hasColumn('practice_images', 'package_add')) {
                $table->json('package_add')->nullable();
            }
            if (! Schema::hasColumn('practice_images', 'package_remove')) {
                $table->json('package_remove')->nullable();
            }
            if (! Schema::hasColumn('practice_images', 'features')) {
                $table->json('features')->nullable();
            }
            if (! Schema::hasColumn('practice_images', 'startup_script_text')) {
                $table->longText('startup_script_text')->default('');
            }
        });
    }

    public function down(): void
    {
        // Откат не поддерживаем: columns могут быть использованы в новых версиях UI.
    }
};

