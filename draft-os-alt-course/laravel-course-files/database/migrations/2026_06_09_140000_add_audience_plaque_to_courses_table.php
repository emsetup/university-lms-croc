<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }

        if (! Schema::hasColumn('courses', 'audience_plaque_enabled')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->boolean('audience_plaque_enabled')->default(false)->after('assessment_enabled');
                $table->string('audience_plaque_kicker', 80)->nullable()->after('audience_plaque_enabled');
                $table->string('audience_plaque_title', 200)->nullable()->after('audience_plaque_kicker');
                $table->text('audience_plaque_teaser')->nullable()->after('audience_plaque_title');
                $table->text('audience_plaque_body')->nullable()->after('audience_plaque_teaser');
            });
        }

        if (! Schema::hasColumn('courses', 'audience_plaque_enabled')) {
            return;
        }

        $teaser = 'Практикум по ОС «Альт» для администраторов с опытом Linux (ориентир — уровень RHCSA). Нажмите, чтобы прочитать полное описание.';
        $body = <<<'MD'
Изначально этот материал планировался как **лабораторная работа и практикум**, а не как первое знакомство с операционной системой. Мы исходим из того, что вы **уже владеете администрированием типичного корпоративного Linux** — на уровне, который можно сопоставить с подготовкой к сертификации RHCSA: командная строка, пользователи и права, службы и автозапуск, сеть и файрвол, хранилища, журналы и базовая диагностика.

**Задача трека другая:** относительно быстро и по делу разобраться с **ОС «Альт»** как с отечественным дистрибутивом — менеджером пакетов и репозиториями, особенностями инструментов и политик обновлений, типичными отличиями от привычных Enterprise Linux. Повторять общие азы мы не ставим целью: фокус на том, что отличает или специфично для «Альт» в реальной работе.

Если ваш опыт с Linux пока небольшой, содержание может оказаться слишком насыщенным — это нормально: имеет смысл сначала пройти вводный курс по Linux, а затем вернуться к этому треку, чтобы получить от него максимум пользы.
MD;

        DB::table('courses')
            ->where('slug', 'alt-os-features')
            ->update([
                'audience_plaque_enabled' => true,
                'audience_plaque_kicker' => 'О курсе',
                'audience_plaque_title' => 'Для кого этот материал',
                'audience_plaque_teaser' => $teaser,
                'audience_plaque_body' => $body,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'audience_plaque_enabled')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'audience_plaque_enabled',
                'audience_plaque_kicker',
                'audience_plaque_title',
                'audience_plaque_teaser',
                'audience_plaque_body',
            ]);
        });
    }
};
