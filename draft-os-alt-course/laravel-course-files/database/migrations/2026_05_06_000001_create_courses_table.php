<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('summary')->default('');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });

        // Курс №1: существующий трек модулей (совместимость).
        DB::table('courses')->insert([
            'slug' => 'alt-os-features',
            'title' => 'Особенности ОС «Альт»',
            'summary' => 'Интенсивный практикум для ИТ-специалистов: администрирование ОС «Альт» в типовых корпоративных сценариях — от установки, репозиториев и сети до журналирования, прав доступа и работы с контейнерами. Материал выстроен по модулям: короткие блоки теории, проверочные тесты и практика в изолированном окружении с нарастающей сложностью заданий. Вы работаете с живой системой, видите последствия конфигурации и учитесь устранять типовые ошибки. В конце — итоговая лабораторная по техническому заданию, близкая по формату к экзаменационным практическим заданиям по администрированию Linux, но в экосистеме «Альт». Прогресс и попытки сохраняются в личном кабинете; после успешного прохождения доступен сертификат.',
            'is_published' => 1,
            'sort' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};

