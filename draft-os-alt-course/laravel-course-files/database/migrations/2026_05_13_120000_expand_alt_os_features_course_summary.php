<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'alt-os-features';

    private const SUMMARY_LEGACY = 'Практикум по администрированию ОС «Альт»: теория, тесты, практика в контейнере и итоговая лабораторная.';

    private const SUMMARY_EXPANDED = <<<'TXT'
Интенсивный практикум для ИТ-специалистов: администрирование ОС «Альт» в типовых корпоративных сценариях — от установки, репозиториев и сети до журналирования, прав доступа и работы с контейнерами. Материал выстроен по модулям: короткие блоки теории, проверочные тесты и практика в изолированном окружении с нарастающей сложностью заданий. Вы работаете с живой системой, видите последствия конфигурации и учитесь устранять типовые ошибки. В конце — итоговая лабораторная по техническому заданию, близкая по формату к экзаменационным практическим заданиям по администрированию Linux, но в экосистеме «Альт». Прогресс и попытки сохраняются в личном кабинете; после успешного прохождения доступен сертификат.
TXT;

    public function up(): void
    {
        DB::table('courses')->where('slug', self::SLUG)->update([
            'summary' => trim(self::SUMMARY_EXPANDED),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('courses')->where('slug', self::SLUG)->update([
            'summary' => self::SUMMARY_LEGACY,
            'updated_at' => now(),
        ]);
    }
};
