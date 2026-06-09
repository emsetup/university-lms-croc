<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseQuizBank;
use App\Models\CourseQuizQuestion;
use App\Models\CourseSection;
use App\Models\CourseSurveyAnswer;
use App\Models\CourseSurveySubmission;
use App\Models\Learner;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SurveyResponseService
{
    public function __construct(
        private CourseContentService $content,
    ) {}

    public function submissionForLearner(int $sectionId, int $learnerId): ?CourseSurveySubmission
    {
        if (! Schema::hasTable('course_survey_submissions') || $sectionId < 1 || $learnerId < 1) {
            return null;
        }

        return CourseSurveySubmission::query()
            ->where('course_section_id', $sectionId)
            ->where('learner_id', $learnerId)
            ->with(['answers.question.options', 'answers.question.matchPairs'])
            ->first();
    }

    public function hasSubmission(int $sectionId, int $learnerId): bool
    {
        return $this->submissionForLearner($sectionId, $learnerId) !== null;
    }

    /**
     * @return list<array{question_id:int,question_text:string,question_type:string,display:string}>
     */
    public function breakdownItems(CourseSurveySubmission $submission, bool $includeIdentity = true): array
    {
        $bank = $this->bankForSection((int) $submission->course_section_id);
        $runtimeList = $bank ? $this->content->questionsForBank($bank) : [];
        $qModels = $bank
            ? CourseQuizQuestion::query()->where('quiz_bank_id', (int) $bank->id)->orderBy('sort')->orderBy('id')->get()
            : collect();

        $answersByQ = $submission->answers->keyBy('question_id');
        $out = [];
        foreach ($qModels->values() as $i => $qModel) {
            $ans = $answersByQ->get((int) $qModel->id);
            $runtime = $runtimeList[$i] ?? null;
            $out[] = [
                'question_id' => (int) $qModel->id,
                'question_text' => (string) $qModel->question_text,
                'question_type' => (string) $qModel->type,
                'display' => $ans ? $this->formatAnswerDisplay($qModel, $ans, $runtime) : '—',
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array{ok:bool,message:string}
     */
    public function validateSubmission(Request $request, array $questions, CourseQuizBank $bank): array
    {
        $dbQuestions = CourseQuizQuestion::query()
            ->where('quiz_bank_id', (int) $bank->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($dbQuestions->isEmpty()) {
            return ['ok' => false, 'message' => 'В опросе нет вопросов.'];
        }

        foreach ($dbQuestions->values() as $i => $qModel) {
            $type = (string) $qModel->type;
            $key = 'q'.$i;
            if ($type === 'open_text') {
                $text = trim((string) $request->input($key, ''));
                if ($text === '') {
                    return ['ok' => false, 'message' => 'Ответьте на вопрос '.($i + 1).'.'];
                }
                $settings = is_array($qModel->settings_json) ? $qModel->settings_json : [];
                $max = isset($settings['max_length']) ? (int) $settings['max_length'] : 8000;
                if ($max > 0 && mb_strlen($text) > $max) {
                    return ['ok' => false, 'message' => 'Ответ на вопрос '.($i + 1).' слишком длинный (макс. '.$max.' символов).'];
                }
                continue;
            }
            $runtime = $questions[$i] ?? null;
            if ($type === 'match_drag') {
                $rawOrder = (string) $request->input($key.'_order', '');
                $parts = array_values(array_map('intval', array_filter(explode(',', $rawOrder), static fn ($v) => $v !== '')));
                $n = is_array($runtime['right'] ?? null) ? count($runtime['right']) : 0;
                if ($n < 1 || count($parts) !== $n) {
                    return ['ok' => false, 'message' => 'Заполните сопоставление в вопросе '.($i + 1).'.'];
                }
                continue;
            }
            if ($type === 'multi') {
                $sel = $request->input($key, []);
                if (! is_array($sel) || $sel === []) {
                    return ['ok' => false, 'message' => 'Ответьте на вопрос '.($i + 1).'.'];
                }
                continue;
            }
            if ($request->input($key) === null || $request->input($key) === '') {
                return ['ok' => false, 'message' => 'Ответьте на вопрос '.($i + 1).'.'];
            }
        }

        return ['ok' => true, 'message' => 'ok'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     */
    public function storeSubmission(
        Learner $learner,
        Course $course,
        CourseModule $module,
        CourseSection $section,
        CourseQuizBank $bank,
        array $questions,
        Request $request,
    ): CourseSurveySubmission {
        return DB::transaction(function () use ($learner, $course, $module, $section, $bank, $questions, $request): CourseSurveySubmission {
            $submission = CourseSurveySubmission::query()->create([
                'course_id' => (int) $course->id,
                'course_module_id' => (int) $module->id,
                'course_section_id' => (int) $section->id,
                'learner_id' => (int) $learner->id,
                'submitted_at' => now(),
            ]);

            $dbQuestions = CourseQuizQuestion::query()
                ->where('quiz_bank_id', (int) $bank->id)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            foreach ($dbQuestions->values() as $i => $qModel) {
                $type = (string) $qModel->type;
                $key = 'q'.$i;
                $answerText = null;
                $answerJson = null;

                if ($type === 'open_text') {
                    $answerText = trim((string) $request->input($key, ''));
                } elseif ($type === 'match_drag') {
                    $rawOrder = (string) $request->input($key.'_order', '');
                    $parts = array_values(array_map('intval', array_filter(explode(',', $rawOrder), static fn ($v) => $v !== '')));
                    $answerJson = ['order' => $parts];
                } elseif ($type === 'multi') {
                    $sel = $request->input($key, []);
                    $answerJson = ['indexes' => array_values(array_map('intval', is_array($sel) ? $sel : []))];
                } else {
                    $answerJson = ['index' => (int) $request->input($key, 0)];
                }

                CourseSurveyAnswer::query()->create([
                    'submission_id' => (int) $submission->id,
                    'question_id' => (int) $qModel->id,
                    'question_type' => $type,
                    'answer_text' => $answerText,
                    'answer_json' => $answerJson,
                ]);
            }

            return $submission->load(['answers.question.options', 'answers.question.matchPairs']);
        });
    }

    public function bankForSection(int $sectionId): ?CourseQuizBank
    {
        $section = CourseSection::query()->find($sectionId);
        if (! $section || $section->type !== CourseSection::TYPE_SURVEY) {
            return null;
        }
        $module = CourseModule::query()->find((int) $section->course_module_id);
        $course = Course::query()->find((int) $section->course_id);
        if (! $module || ! $course) {
            return null;
        }

        return $this->content->quizBankForSection($section);
    }

    /**
     * @return Collection<int, CourseSurveySubmission>
     */
    public function submissionsForSection(int $sectionId): Collection
    {
        if (! Schema::hasTable('course_survey_submissions')) {
            return collect();
        }

        return CourseSurveySubmission::query()
            ->where('course_section_id', $sectionId)
            ->with(['learner', 'answers.question.options', 'answers.question.matchPairs'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>|null  $runtime
     */
    public function formatAnswerDisplay(CourseQuizQuestion $qModel, CourseSurveyAnswer $ans, ?array $runtime): string
    {
        $type = (string) $qModel->type;
        if ($type === 'open_text') {
            return (string) ($ans->answer_text ?? '');
        }
        $json = is_array($ans->answer_json) ? $ans->answer_json : [];
        if ($type === 'match_drag') {
            $order = is_array($json['order'] ?? null) ? $json['order'] : [];
            $pairs = $qModel->matchPairs;
            $left = $pairs->pluck('left_text')->all();
            $right = $pairs->pluck('right_text')->all();
            $parts = [];
            foreach ($order as $pos => $ri) {
                $ri = (int) $ri;
                $lv = (string) ($left[$pos] ?? '');
                $rv = (string) ($right[$ri] ?? '');
                if ($lv !== '' || $rv !== '') {
                    $parts[] = $lv.' → '.$rv;
                }
            }

            return $parts !== [] ? implode('; ', $parts) : '—';
        }
        $opts = $qModel->options->pluck('option_text')->values()->all();
        if ($type === 'multi') {
            $idxs = is_array($json['indexes'] ?? null) ? $json['indexes'] : [];
            $picked = [];
            foreach ($idxs as $ix) {
                $picked[] = (string) ($opts[(int) $ix] ?? '#'.(int) $ix);
            }

            return $picked !== [] ? implode(', ', $picked) : '—';
        }
        $ix = (int) ($json['index'] ?? -1);

        return (string) ($opts[$ix] ?? '—');
    }
}
