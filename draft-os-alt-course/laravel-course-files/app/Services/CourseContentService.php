<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseModuleContent;
use App\Models\CourseQuizBank;
use App\Models\CourseQuizCorrectAnswer;
use App\Models\CourseQuizMatchPair;
use App\Models\CourseQuizOption;
use App\Models\CourseQuizQuestion;
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
        if (! in_array($kind, ['theory_quiz', 'module_exam', 'final_lab'], true)) {
            return null;
        }

        return CourseQuizBank::query()
            ->where('course_id', (int) $course->id)
            ->when($cm !== null, fn ($q) => $q->where('course_module_id', (int) $cm->id))
            ->when($cm === null, fn ($q) => $q->whereNull('course_module_id'))
            ->where('kind', $kind)
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
            if ($type === 'match_drag') {
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

