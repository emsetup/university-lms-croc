<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_quiz_banks')) {
            return;
        }

        $this->dropUniqueIfExists(
            'course_quiz_banks',
            'course_quiz_banks_course_id_course_module_id_kind_unique',
            ['course_id', 'course_module_id', 'kind']
        );

        if (! Schema::hasColumn('course_quiz_banks', 'course_section_id')) {
            return;
        }

        if (! $this->hasIndex('course_quiz_banks_course_id_course_module_id_course_section_id_kind_unique')) {
            Schema::table('course_quiz_banks', function (Blueprint $table) {
                $table->unique(
                    ['course_id', 'course_module_id', 'course_section_id', 'kind'],
                    'course_quiz_banks_course_id_course_module_id_course_section_id_kind_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_quiz_banks')) {
            return;
        }

        $this->dropUniqueIfExists(
            'course_quiz_banks',
            'course_quiz_banks_course_id_course_module_id_course_section_id_kind_unique',
            ['course_id', 'course_module_id', 'course_section_id', 'kind']
        );

        if (! $this->hasIndex('course_quiz_banks_course_id_course_module_id_kind_unique')) {
            Schema::table('course_quiz_banks', function (Blueprint $table) {
                $table->unique(['course_id', 'course_module_id', 'kind']);
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropUniqueIfExists(string $table, string $preferredName, array $columns): void
    {
        if ($this->hasIndex($preferredName)) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                    $blueprint->dropUnique($columns);
                });

                return;
            } catch (\Throwable) {
            }
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($preferredName) {
                $blueprint->dropIndex($preferredName);
            });
        } catch (\Throwable) {
        }
    }

    private function hasIndex(string $name): bool
    {
        $rows = Schema::getIndexes('course_quiz_banks');

        foreach ($rows as $row) {
            if (($row['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }
};
