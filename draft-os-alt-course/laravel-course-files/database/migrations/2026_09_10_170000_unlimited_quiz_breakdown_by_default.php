<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Убрать лимит времени на разбор ответов после теста/экзамена:
 * timed window (15/30 мин и т.п.) → без ограничения (−1).
 * 0 оставляем (разбор намеренно скрыт, напр. опросы).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_quiz_banks') && Schema::hasColumn('course_quiz_banks', 'breakdown_visible_minutes')) {
            DB::table('course_quiz_banks')
                ->where('breakdown_visible_minutes', '>', 0)
                ->update(['breakdown_visible_minutes' => -1]);
        }

        if (! Schema::hasTable('course_section_settings')) {
            return;
        }

        $rows = DB::table('course_section_settings')->select(['id', 'settings'])->get();
        foreach ($rows as $row) {
            $raw = $row->settings;
            if ($raw === null || $raw === '') {
                continue;
            }
            $settings = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
            if (! is_array($settings) || ! array_key_exists('breakdown_visible_minutes', $settings)) {
                continue;
            }
            $bv = $settings['breakdown_visible_minutes'];
            if (! is_numeric($bv) || (int) $bv <= 0) {
                continue;
            }
            $settings['breakdown_visible_minutes'] = -1;
            DB::table('course_section_settings')->where('id', $row->id)->update([
                'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Не восстанавливаем прежние 15/30 — решение продуктовое.
    }
};
