<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite: первая миграция анонимности не сняла NOT NULL с learner_id
 * (ALTER COLUMN недоступен) — пересоздаём таблицу.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_survey_submissions')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            // MySQL уже обработан в 2026_09_16_120000; на всякий случай проверим.
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $row = DB::selectOne(
                    "SELECT IS_NULLABLE AS n
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'course_survey_submissions'
                       AND COLUMN_NAME = 'learner_id'
                     LIMIT 1"
                );
                if ($row && strtoupper((string) ($row->n ?? '')) === 'NO') {
                    $fk = DB::selectOne(
                        'SELECT CONSTRAINT_NAME AS name
                         FROM information_schema.KEY_COLUMN_USAGE
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = ?
                           AND COLUMN_NAME = ?
                           AND REFERENCED_TABLE_NAME IS NOT NULL
                         LIMIT 1',
                        ['course_survey_submissions', 'learner_id']
                    );
                    if (! empty($fk->name)) {
                        DB::statement('ALTER TABLE `course_survey_submissions` DROP FOREIGN KEY `'.$fk->name.'`');
                    }
                    DB::statement('ALTER TABLE `course_survey_submissions` MODIFY `learner_id` BIGINT UNSIGNED NULL');
                    DB::statement(
                        'ALTER TABLE `course_survey_submissions`
                         ADD CONSTRAINT `course_survey_submissions_learner_id_foreign`
                         FOREIGN KEY (`learner_id`) REFERENCES `learners` (`id`) ON DELETE SET NULL'
                    );
                }
            }

            return;
        }

        $cols = DB::select('PRAGMA table_info(course_survey_submissions)');
        $learnerNotNull = false;
        foreach ($cols as $c) {
            if (($c->name ?? '') === 'learner_id' && (int) ($c->notnull ?? 0) === 1) {
                $learnerNotNull = true;
                break;
            }
        }
        if (! $learnerNotNull) {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');

        try {
            DB::statement('DROP TABLE IF EXISTS course_survey_submissions__anon_fix');
            DB::statement('
                CREATE TABLE course_survey_submissions__anon_fix (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    course_id INTEGER NOT NULL,
                    course_module_id INTEGER NOT NULL,
                    course_section_id INTEGER NOT NULL,
                    learner_id INTEGER NULL,
                    submitted_at DATETIME NOT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
                    FOREIGN KEY(course_module_id) REFERENCES course_modules(id) ON DELETE CASCADE,
                    FOREIGN KEY(course_section_id) REFERENCES course_sections(id) ON DELETE CASCADE,
                    FOREIGN KEY(learner_id) REFERENCES learners(id) ON DELETE SET NULL
                )
            ');

            DB::statement('
                INSERT INTO course_survey_submissions__anon_fix
                    (id, course_id, course_module_id, course_section_id, learner_id, submitted_at, created_at, updated_at)
                SELECT id, course_id, course_module_id, course_section_id, learner_id, submitted_at, created_at, updated_at
                FROM course_survey_submissions
            ');

            DB::statement('DROP TABLE course_survey_submissions');
            DB::statement('ALTER TABLE course_survey_submissions__anon_fix RENAME TO course_survey_submissions');

            DB::statement('CREATE UNIQUE INDEX course_survey_submissions_course_section_id_learner_id_unique
                ON course_survey_submissions (course_section_id, learner_id)');
            DB::statement('CREATE INDEX course_survey_submissions_course_section_id_submitted_at_index
                ON course_survey_submissions (course_section_id, submitted_at)');
        } finally {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    public function down(): void
    {
        // Не возвращаем NOT NULL — могли появиться анонимные строки.
    }
};
