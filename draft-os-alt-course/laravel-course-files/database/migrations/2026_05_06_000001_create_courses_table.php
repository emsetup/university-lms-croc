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
            'summary' => 'Практикум по администрированию ОС «Альт»: теория, тесты, практика в контейнере и итоговая лабораторная.',
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

