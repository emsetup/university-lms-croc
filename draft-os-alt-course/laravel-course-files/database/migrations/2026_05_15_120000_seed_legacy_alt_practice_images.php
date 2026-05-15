<?php

use App\Services\LegacyAltPracticeImagesBootstrap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('practice_images')) {
            return;
        }

        LegacyAltPracticeImagesBootstrap::sync();
    }

    public function down(): void
    {
        // Не удаляем импортированные образы: они могли редактироваться в админке.
    }
};
