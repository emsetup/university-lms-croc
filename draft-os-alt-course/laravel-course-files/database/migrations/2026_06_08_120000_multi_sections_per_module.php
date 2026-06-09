<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_quiz_banks') && ! Schema::hasColumn('course_quiz_banks', 'course_section_id')) {
            Schema::table('course_quiz_banks', function (Blueprint $table) {
                $table->foreignId('course_section_id')->nullable()->after('course_module_id')
                    ->constrained('course_sections')->nullOnDelete();
                $table->unique('course_section_id');
            });

            $this->linkExistingBanksToSections();
        }

        if (Schema::hasTable('module_progress') && ! Schema::hasColumn('module_progress', 'section_states')) {
            Schema::table('module_progress', function (Blueprint $table) {
                $table->json('section_states')->nullable()->after('difficulty_flags');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('course_quiz_banks') && Schema::hasColumn('course_quiz_banks', 'course_section_id')) {
            Schema::table('course_quiz_banks', function (Blueprint $table) {
                $table->dropConstrainedForeignId('course_section_id');
            });
        }
        if (Schema::hasTable('module_progress') && Schema::hasColumn('module_progress', 'section_states')) {
            Schema::table('module_progress', function (Blueprint $table) {
                $table->dropColumn('section_states');
            });
        }
    }

    private function linkExistingBanksToSections(): void
    {
        if (! Schema::hasTable('course_sections')) {
            return;
        }

        $typeByKind = [
            'theory_quiz' => 'quiz',
            'module_exam' => 'exam',
            'survey' => 'survey',
        ];

        $banks = DB::table('course_quiz_banks')
            ->whereNull('course_section_id')
            ->whereNotNull('course_module_id')
            ->get();

        foreach ($banks as $bank) {
            $secType = $typeByKind[(string) $bank->kind] ?? null;
            if ($secType === null) {
                continue;
            }
            $sectionId = DB::table('course_sections')
                ->where('course_module_id', (int) $bank->course_module_id)
                ->where('type', $secType)
                ->orderBy('sort')
                ->orderBy('id')
                ->value('id');
            if ($sectionId) {
                DB::table('course_quiz_banks')
                    ->where('id', (int) $bank->id)
                    ->update(['course_section_id' => (int) $sectionId]);
            }
        }
    }
};
