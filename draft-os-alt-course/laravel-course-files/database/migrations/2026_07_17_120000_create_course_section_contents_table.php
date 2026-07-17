<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_section_contents')) {
            Schema::create('course_section_contents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_section_id')
                    ->constrained('course_sections')
                    ->cascadeOnDelete();
                $table->longText('body_markdown')->default('');
                $table->timestamps();

                $table->unique('course_section_id');
            });
        }

        if (! Schema::hasTable('course_module_contents') || ! Schema::hasTable('course_sections')) {
            return;
        }

        $now = now();
        $rows = DB::table('course_module_contents')->orderBy('id')->get();
        foreach ($rows as $row) {
            $moduleId = (int) $row->course_module_id;
            $theory = (string) ($row->theory_markdown ?? '');
            $practice = (string) ($row->practice_markdown ?? '');

            if ($theory !== '') {
                $textSectionId = DB::table('course_sections')
                    ->where('course_module_id', $moduleId)
                    ->where('type', 'text')
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->value('id');
                if ($textSectionId !== null) {
                    $this->upsertSectionContent((int) $textSectionId, $theory, $now);
                }
            }

            if ($practice !== '') {
                $practiceSectionId = DB::table('course_sections')
                    ->where('course_module_id', $moduleId)
                    ->where('type', 'practice')
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->value('id');
                if ($practiceSectionId !== null) {
                    $this->upsertSectionContent((int) $practiceSectionId, $practice, $now);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_section_contents');
    }

    private function upsertSectionContent(int $sectionId, string $markdown, mixed $now): void
    {
        $exists = DB::table('course_section_contents')
            ->where('course_section_id', $sectionId)
            ->exists();
        if ($exists) {
            return;
        }

        DB::table('course_section_contents')->insert([
            'course_section_id' => $sectionId,
            'body_markdown' => $markdown,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
