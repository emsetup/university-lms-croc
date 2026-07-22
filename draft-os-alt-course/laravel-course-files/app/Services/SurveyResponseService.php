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

    /**
     * Завершённая отправка: есть хотя бы один ответ.
     * Пустая оболочка (ответы стёрты каскадом при пересохранении вопросов) не считается прохождением.
     */
    public function completeSubmissionForLearner(int $sectionId, int $learnerId): ?CourseSurveySubmission
    {
        $sub = $this->submissionForLearner($sectionId, $learnerId);
        if ($sub === null) {
            return null;
        }
        if (! $this->submissionHasAnswers($sub)) {
            return null;
        }

        return $sub;
    }

    public function hasSubmission(int $sectionId, int $learnerId): bool
    {
        return $this->completeSubmissionForLearner($sectionId, $learnerId) !== null;
    }

    public function submissionHasAnswers(CourseSurveySubmission $submission): bool
    {
        if ($submission->relationLoaded('answers')) {
            return $submission->answers->isNotEmpty();
        }

        return CourseSurveyAnswer::query()
            ->where('submission_id', (int) $submission->id)
            ->exists();
    }

    /**
     * Удаляет пустые оболочки submission (без ответов), чтобы можно было пройти опрос снова.
     */
    public function purgeEmptySubmission(int $sectionId, int $learnerId): bool
    {
        $sub = $this->submissionForLearner($sectionId, $learnerId);
        if ($sub === null || $this->submissionHasAnswers($sub)) {
            return false;
        }
        $sub->delete();

        return true;
    }

    /**
     * @return int число удалённых пустых оболочек
     */
    public function purgeEmptySubmissionsForSection(int $sectionId): int
    {
        if (! Schema::hasTable('course_survey_submissions') || $sectionId < 1) {
            return 0;
        }

        $subs = CourseSurveySubmission::query()
            ->where('course_section_id', $sectionId)
            ->with('answers')
            ->get();
        $n = 0;
        foreach ($subs as $sub) {
            if ($this->submissionHasAnswers($sub)) {
                continue;
            }
            $sub->delete();
            $n++;
        }

        return $n;
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
            if ($type === 'multi_other') {
                $sel = $request->input($key, []);
                $other = trim((string) $request->input($key.'_other', ''));
                $hasSel = is_array($sel) && $sel !== [];
                if (! $hasSel && $other === '') {
                    return ['ok' => false, 'message' => 'Ответьте на вопрос '.($i + 1).'.'];
                }
                $settings = is_array($qModel->settings_json) ? $qModel->settings_json : [];
                $max = isset($settings['max_length']) ? (int) $settings['max_length'] : 8000;
                if ($other !== '' && $max > 0 && mb_strlen($other) > $max) {
                    return ['ok' => false, 'message' => 'Свой вариант в вопросе '.($i + 1).' слишком длинный (макс. '.$max.' символов).'];
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
            // Пустая оболочка после каскадного удаления ответов — убрать, иначе unique мешает повторной сдаче.
            $this->purgeEmptySubmission((int) $section->id, (int) $learner->id);

            $existing = $this->completeSubmissionForLearner((int) $section->id, (int) $learner->id);
            if ($existing !== null) {
                throw new \RuntimeException('Ответы на этот опрос уже отправлены.');
            }

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
                } elseif ($type === 'multi_other') {
                    $sel = $request->input($key, []);
                    $other = trim((string) $request->input($key.'_other', ''));
                    $answerJson = [
                        'indexes' => array_values(array_map('intval', is_array($sel) ? $sel : [])),
                    ];
                    if ($other !== '') {
                        $answerJson['other'] = $other;
                        $answerText = $other;
                    }
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
            ->whereHas('answers')
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
        if ($type === 'multi' || $type === 'multi_other') {
            $idxs = is_array($json['indexes'] ?? null) ? $json['indexes'] : [];
            $picked = [];
            foreach ($idxs as $ix) {
                $picked[] = (string) ($opts[(int) $ix] ?? '#'.(int) $ix);
            }
            if ($type === 'multi') {
                return $picked !== [] ? implode(', ', $picked) : '—';
            }
            $other = trim((string) ($json['other'] ?? $ans->answer_text ?? ''));
            $parts = [];
            if ($picked !== []) {
                $parts[] = implode(', ', $picked);
            }
            if ($other !== '') {
                $parts[] = 'свой вариант: '.$other;
            }

            return $parts !== [] ? implode('; ', $parts) : '—';
        }
        $ix = (int) ($json['index'] ?? -1);

        return (string) ($opts[$ix] ?? '—');
    }
}
