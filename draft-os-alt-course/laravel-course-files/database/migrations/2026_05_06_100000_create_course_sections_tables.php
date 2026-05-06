<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('type', 32); // text | quiz | practice | exam
            $table->string('title');
            $table->unsignedInteger('sort')->default(100);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['course_id', 'type']);
            $table->index(['course_id', 'sort']);
        });

        Schema::create('course_section_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique('course_section_id');
        });

        $courseId = DB::table('courses')->where('slug', 'alt-os-features')->value('id');
        if ($courseId) {
            $now = now();
            $rows = [
                ['type' => 'text', 'title' => 'Теория', 'sort' => 10, 'settings' => json_encode([
                    'min_read_seconds' => 0,
                    'time_limit_minutes' => null,
                ])],
                ['type' => 'quiz', 'title' => 'Тестирование по теоретической части', 'sort' => 20, 'settings' => json_encode([
                    'time_limit_minutes' => 30,
                    'attempt_limit' => null,
                    'pass_percent' => 70,
                    'penalties' => ['2' => 10],
                    'shuffle' => false,
                ])],
                ['type' => 'practice', 'title' => 'Практическое занятие', 'sort' => 30, 'settings' => json_encode([
                    'attempt_limit' => null,
                    'time_limit_minutes' => null,
                ])],
                ['type' => 'exam', 'title' => 'Итоговый тест по модулю', 'sort' => 40, 'settings' => json_encode([
                    'time_limit_minutes' => 60,
                    'attempt_limit' => 2,
                    'pass_percent' => 70,
                    'penalties' => ['2' => 10],
                    'one_by_one' => true,
                    'breakdown_visible_minutes' => 30,
                ])],
            ];
            foreach ($rows as $r) {
                $sid = DB::table('course_sections')->insertGetId([
                    'course_id' => $courseId,
                    'type' => $r['type'],
                    'title' => $r['title'],
                    'sort' => $r['sort'],
                    'is_enabled' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('course_section_settings')->insert([
                    'course_section_id' => $sid,
                    'settings' => $r['settings'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_section_settings');
        Schema::dropIfExists('course_sections');
    }
};
