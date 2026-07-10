<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseModuleContent;
use App\Models\CourseQuizBank;
use App\Models\CourseSection;
use App\Models\CourseQuizCorrectAnswer;
use App\Models\CourseQuizMatchPair;
use App\Models\CourseQuizOption;
use App\Models\CourseQuizQuestion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

final class CourseContentService
{
    /**
     * @return array{theory_markdown:string,practice_markdown:string}
     */
    public function contentForModule(CourseModule $cm): array
    {
        if (! Schema::hasTable('course_module_contents')) {
            return ['theory_markdown' => '', 'practice_markdown' => ''];
        }

        $row = CourseModuleContent::query()
            ->where('course_module_id', (int) $cm->id)
            ->first();

        return [
            'theory_markdown' => (string) ($row?->theory_markdown ?? ''),
            'practice_markdown' => (string) ($row?->practice_markdown ?? ''),
        ];
    }

    public function upsertContentForModule(CourseModule $cm, string $theory, string $practice): CourseModuleContent
    {
        /** @var CourseModuleContent $row */
        $row = CourseModuleContent::query()->firstOrNew(['course_module_id' => (int) $cm->id]);
        $row->theory_markdown = $theory;
        $row->practice_markdown = $practice;
        $row->save();

        return $row;
    }

    public function quizBankFor(Course $course, ?CourseModule $cm, string $kind): ?CourseQuizBank
    {
        if (! Schema::hasTable('course_quiz_banks')) {
            return null;
        }
        if (! in_array($kind, ['theory_quiz', 'module_exam', 'final_lab', 'survey'], true)) {
            return null;
        }

        $query = CourseQuizBank::query()
            ->where('course_id', (int) $course->id)
            ->when($cm !== null, fn ($q) => $q->where('course_module_id', (int) $cm->id))
            ->when($cm === null, fn ($q) => $q->whereNull('course_module_id'))
            ->where('kind', $kind);

        if (Schema::hasColumn('course_quiz_banks', 'course_section_id')) {
            $moduleLevel = (clone $query)->whereNull('course_section_id')->first();
            if ($moduleLevel !== null) {
                return $moduleLevel;
            }
        }

        return $query->orderBy('id')->first();
    }

    /**
     * Банк, принадлежащий только этому разделу (для редактора и сохранения).
     * Не подставляет банк соседнего раздела того же типа.
     */
    public function quizBankOwnedBySection(CourseSection $section): ?CourseQuizBank
    {
        if (! Schema::hasTable('course_quiz_banks')) {
            return null;
        }
        if ($section->quizBankKind() === null) {
            return null;
        }

        if (Schema::hasColumn('course_quiz_banks', 'course_section_id')) {
            $owned = CourseQuizBank::query()
                ->where('course_section_id', (int) $section->id)
                ->first();
            if ($owned !== null) {
                return $owned;
            }
        }

        return $this->quizBankForSection($section);
    }

    /**
     * Найти или создать банк для сохранения из редактора раздела (без дубликатов по UNIQUE).
     *
     * @param  array<string, mixed>  $defaults
     */
    public function ensureQuizBankForSection(
        Course $course,
        CourseModule $module,
        CourseSection $section,
        string $kind,
        array $defaults
    ): CourseQuizBank {
        $courseId = (int) $course->id;
        $moduleId = (int) $module->id;
        $sectionId = (int) $section->id;

        if (Schema::hasColumn('course_quiz_banks', 'course_section_id')) {
            $exact = CourseQuizBank::query()
                ->where('course_id', $courseId)
                ->where('course_module_id', $moduleId)
                ->where('course_section_id', $sectionId)
                ->where('kind', $kind)
                ->first();
            if ($exact !== null) {
                return $exact;
            }

            $bySection = CourseQuizBank::query()
                ->where('course_section_id', $sectionId)
                ->first();
            if ($bySection !== null) {
                return $bySection;
            }

            $moduleLevel = $this->quizBankForSection($section);
            if ($moduleLevel !== null && (int) ($moduleLevel->course_section_id ?? 0) < 1) {
                $moduleLevel->course_section_id = $sectionId;
                $moduleLevel->save();

                return $moduleLevel;
            }

            try {
                /** @var CourseQuizBank $bank */
                $bank = CourseQuizBank::query()->firstOrCreate(
                    [
                        'course_id' => $courseId,
                        'course_module_id' => $moduleId,
                        'course_section_id' => $sectionId,
                        'kind' => $kind,
                    ],
                    $defaults
                );

                return $bank;
            } catch (UniqueConstraintViolationException) {
                $retry = CourseQuizBank::query()
                    ->where('course_id', $courseId)
                    ->where('course_module_id', $moduleId)
                    ->where('course_section_id', $sectionId)
                    ->where('kind', $kind)
                    ->first();
                if ($retry !== null) {
                    return $retry;
                }

                throw new \RuntimeException(
                    'Не удалось создать банк вопросов для раздела: в модуле уже есть банк этого типа. '
                    .'Выполните миграции БД (course_quiz_banks_section_scoped_unique).'
                );
            }
        }

        $existing = $this->quizBankFor($course, $module, $kind);
        if ($existing !== null) {
            return $existing;
        }

        /** @var CourseQuizBank $bank */
        $bank = CourseQuizBank::query()->create([
            'course_id' => $courseId,
            'course_module_id' => $moduleId,
            'kind' => $kind,
            ...$defaults,
        ]);

        return $bank;
    }

    public function quizBankForSection(CourseSection $section): ?CourseQuizBank
    {
        if (! Schema::hasTable('course_quiz_banks')) {
            return null;
        }
        $kind = $section->quizBankKind();
        if ($kind === null) {
            return null;
        }

        if (! Schema::hasColumn('course_quiz_banks', 'course_section_id')) {
            $module = CourseModule::query()->find((int) $section->course_module_id);
            $course = Course::query()->find((int) $section->course_id);
            if (! $module || ! $course) {
                return null;
            }

            return $this->quizBankFor($course, $module, $kind);
        }

        $bySection = CourseQuizBank::query()
            ->where('course_section_id', (int) $section->id)
            ->first();
        if ($bySection !== null) {
            return $bySection;
        }

        // Общий банк модуля (course_section_id = null) — только для единственного раздела этого типа.
        // Иначе пустые разделы ошибочно получали бы вопросы соседнего теста.
        $moduleId = (int) $section->course_module_id;
        $sameTypeCount = app(CourseSectionService::class)
            ->enabledSectionsForCourseModule($moduleId)
            ->filter(fn (CourseSection $s): bool => (string) $s->type === (string) $section->type)
            ->count();
        if ($sameTypeCount !== 1) {
            return null;
        }

        return CourseQuizBank::query()
            ->where('course_id', (int) $section->course_id)
            ->where('course_module_id', $moduleId)
            ->where('kind', $kind)
            ->whereNull('course_section_id')
            ->first();
    }

    /**
     * Возвращает вопросы в формате, совместимом с текущими scorer’ами в ModuleController.
     *
     * @return array<int, array<string, mixed>>
     */
    public function questionsForBank(CourseQuizBank $bank): array
    {
        if (! Schema::hasTable('course_quiz_questions')) {
            return [];
        }

        $questions = CourseQuizQuestion::query()
            ->where('quiz_bank_id', (int) $bank->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($questions->isEmpty()) {
            return [];
        }

        $qIds = $questions->pluck('id')->map(fn ($v) => (int) $v)->all();

        $optionsByQ = [];
        if (Schema::hasTable('course_quiz_options')) {
            $opts = CourseQuizOption::query()
                ->whereIn('question_id', $qIds)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();
            foreach ($opts as $o) {
                $qid = (int) $o->question_id;
                $optionsByQ[$qid] ??= [];
                $optionsByQ[$qid][] = (string) $o->option_text;
            }
        }

        $correctByQ = [];
        if (Schema::hasTable('course_quiz_correct_answers') && Schema::hasTable('course_quiz_options')) {
            $ans = CourseQuizCorrectAnswer::query()
                ->whereIn('question_id', $qIds)
                ->get();
            if ($ans->isNotEmpty()) {
                $optIdToIndex = [];
                $opts = CourseQuizOption::query()
                    ->whereIn('question_id', $qIds)
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->get();
                $idxByQuestion = [];
                foreach ($opts as $o) {
                    $qid = (int) $o->question_id;
                    $idxByQuestion[$qid] ??= 0;
                    $optIdToIndex[(int) $o->id] = ['qid' => $qid, 'idx' => $idxByQuestion[$qid]];
                    $idxByQuestion[$qid]++;
                }
                foreach ($ans as $a) {
                    $m = $optIdToIndex[(int) $a->option_id] ?? null;
                    if (! $m) {
                        continue;
                    }
                    $qid = (int) $m['qid'];
                    $correctByQ[$qid] ??= [];
                    $correctByQ[$qid][] = (int) $m['idx'];
                }
                foreach ($correctByQ as $qid => $arr) {
                    $arr = array_values(array_unique(array_map('intval', $arr)));
                    sort($arr);
                    $correctByQ[$qid] = $arr;
                }
            }
        }

        $pairsByQ = [];
        if (Schema::hasTable('course_quiz_match_pairs')) {
            $pairs = CourseQuizMatchPair::query()
                ->whereIn('question_id', $qIds)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();
            foreach ($pairs as $p) {
                $qid = (int) $p->question_id;
                $pairsByQ[$qid] ??= ['left' => [], 'right' => []];
                $pairsByQ[$qid]['left'][] = (string) $p->left_text;
                $pairsByQ[$qid]['right'][] = (string) $p->right_text;
            }
        }

        $out = [];
        foreach ($questions as $q) {
            $qid = (int) $q->id;
            $type = (string) ($q->type ?? 'single');
            $row = [
                'q' => (string) $q->question_text,
            ];
            if (is_numeric($q->points) && (int) $q->points > 0) {
                $row['points'] = (int) $q->points;
            }
            if ($type === 'open_text') {
                $row['open_text'] = true;
                $settings = is_array($q->settings_json) ? $q->settings_json : [];
                if (! empty($settings['placeholder'])) {
                    $row['placeholder'] = (string) $settings['placeholder'];
                }
                if (! empty($settings['max_length']) && is_numeric($settings['max_length'])) {
                    $row['max_length'] = (int) $settings['max_length'];
                }
            } elseif ($type === 'match_drag') {
                $row['match_drag'] = true;
                $row['left'] = $pairsByQ[$qid]['left'] ?? [];
                $row['right'] = $pairsByQ[$qid]['right'] ?? [];
            } else {
                $row['a'] = $optionsByQ[$qid] ?? [];
                $corr = $correctByQ[$qid] ?? [];
                if ($type === 'multi') {
                    $row['c'] = $corr;
                } else {
                    $row['c'] = $corr !== [] ? (int) $corr[0] : 0;
                }
            }
            $out[] = $row;
        }

        return $out;
    }
}

