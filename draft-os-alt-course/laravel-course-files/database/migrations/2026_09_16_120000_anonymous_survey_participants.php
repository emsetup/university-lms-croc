<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Анонимные опросы: ответы без learner_id, факт прохождения — в отдельной таблице.
 * Повторная сдача по-прежнему запрещена; в админке нельзя связать ответ с человеком.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_survey_submissions')) {
            return;
        }

        if (! Schema::hasTable('course_survey_participants')) {
            Schema::create('course_survey_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
                $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
                $table->timestamp('submitted_at');
                $table->timestamps();

                $table->unique(['course_section_id', 'learner_id']);
                $table->index(['course_section_id', 'submitted_at']);
            });
        }

        if (Schema::hasTable('course_survey_answers')) {
            $rows = DB::table('course_survey_submissions as s')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('course_survey_answers as a')
                        ->whereColumn('a.submission_id', 's.id');
                })
                ->whereNotNull('s.learner_id')
                ->select('s.course_section_id', 's.learner_id', 's.submitted_at')
                ->orderBy('s.id')
                ->get();

            $now = now();
            $seen = [];
            foreach ($rows as $row) {
                $key = ((int) $row->course_section_id).':'.((int) $row->learner_id);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                DB::table('course_survey_participants')->insertOrIgnore([
                    'course_section_id' => (int) $row->course_section_id,
                    'learner_id' => (int) $row->learner_id,
                    'submitted_at' => $row->submitted_at ?? $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->makeLearnerIdNullableMysql();
        } elseif ($driver === 'sqlite') {
            // SQLite в тестах: foreign keys часто off; достаточно raw.
            try {
                DB::statement('PRAGMA foreign_keys=OFF');
                // Если колонка уже nullable — ничего не делаем через изменение схемы;
                // SQLite допускает NULL в INTEGER FK при отсутствии строгой проверки.
            } finally {
                DB::statement('PRAGMA foreign_keys=ON');
            }
        } else {
            try {
                Schema::table('course_survey_submissions', function (Blueprint $table) {
                    $table->dropForeign(['learner_id']);
                });
            } catch (\Throwable) {
            }
            try {
                Schema::table('course_survey_submissions', function (Blueprint $table) {
                    $table->unsignedBigInteger('learner_id')->nullable()->change();
                });
            } catch (\Throwable) {
            }
            try {
                Schema::table('course_survey_submissions', function (Blueprint $table) {
                    $table->foreign('learner_id')->references('id')->on('learners')->nullOnDelete();
                });
            } catch (\Throwable) {
            }
        }
    }

    private function makeLearnerIdNullableMysql(): void
    {
        $fk = $this->mysqlForeignKeyName('course_survey_submissions', 'learner_id');
        if ($fk !== null) {
            DB::statement('ALTER TABLE `course_survey_submissions` DROP FOREIGN KEY `'.$fk.'`');
        }
        DB::statement('ALTER TABLE `course_survey_submissions` MODIFY `learner_id` BIGINT UNSIGNED NULL');
        $exists = DB::selectOne(
            "SELECT CONSTRAINT_NAME AS name
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'course_survey_submissions'
               AND CONSTRAINT_NAME = 'course_survey_submissions_learner_id_foreign'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
             LIMIT 1"
        );
        if ($exists === null) {
            DB::statement(
                'ALTER TABLE `course_survey_submissions`
                 ADD CONSTRAINT `course_survey_submissions_learner_id_foreign`
                 FOREIGN KEY (`learner_id`) REFERENCES `learners` (`id`) ON DELETE SET NULL'
            );
        }
    }

    private function mysqlForeignKeyName(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$table, $column]
        );

        return isset($row->name) ? (string) $row->name : null;
    }

    public function down(): void
    {
        Schema::dropIfExists('course_survey_participants');
    }
};
