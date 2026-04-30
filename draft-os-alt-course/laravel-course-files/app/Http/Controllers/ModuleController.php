<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\ModuleProgress;
use App\Models\PracticeSession;
use App\Services\CourseScoringService;
use App\Services\ModuleAccessGate;
use App\Services\PracticeLabService;
use App\Support\CourseModuleMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __construct(
        private CourseScoringService $scoring,
        private ModuleAccessGate $accessGate
    ) {}

    protected function learner(): Learner
    {
        return Learner::findOrFail(session('learner_id'));
    }

    /**
     * @return array<int, array{q:string,a:array,c:int|array<int>}>
     */
    protected function questions(int $moduleId, string $kind): array
    {
        return config('course.module_quizzes.'.$moduleId.'.'.$kind, []);
    }

    /**
     * Лимит времени на итоговый тест модуля (мин.): из course.modules[N].module_exam_time_limit_minutes или константа.
     */
    protected function moduleExamTimeLimitMinutes(int $module): int
    {
        $v = config('course.modules.'.$module.'.module_exam_time_limit_minutes');

        return (is_numeric($v) && (int) $v > 0)
            ? (int) $v
            : CourseScoringService::MODULE_EXAM_TIME_LIMIT_MINUTES;
    }

    protected function examQuestionIsMulti(array $q): bool
    {
        return isset($q['c']) && is_array($q['c']);
    }

    /**
     * Итоговый тест: одиночный выбор (c — int) или несколько верных (c — список индексов).
     * Если у вопросов задано поле points (>0), сырой процент считается по сумме баллов, иначе — по числу верных вопросов.
     *
     * @param  array<int, array{q:string,a:array,c:int|array<int>,points?:int}>  $questions
     * @return array{correct_count:int, wrong_count:int, total:int, raw_percent:int, items: array<int, array<string, mixed>>, max_points:?int, earned_points:?int}
     */
    protected function scoreModuleExamBreakdown(array $questions, Request $request): array
    {
        $items = [];
        $correctCount = 0;
        $weighted = false;
        foreach ($questions as $q) {
            if (isset($q['points']) && (int) $q['points'] > 0) {
                $weighted = true;
                break;
            }
        }
        $maxPoints = 0;
        $earnedPoints = 0;
        foreach (array_keys($questions) as $i) {
            $q = $questions[$i];
            $pts = 0;
            if ($weighted) {
                $pts = max(0, (int) ($q['points'] ?? 0));
                if ($pts <= 0) {
                    $pts = 1;
                }
                $maxPoints += $pts;
            }
            if (! empty($q['match_drag'])) {
                $left = is_array($q['left'] ?? null) ? $q['left'] : [];
                $right = is_array($q['right'] ?? null) ? $q['right'] : [];
                $n = count($left);
                $malformed = $n < 1 || count($right) !== $n;
                $rawOrder = (string) $request->input('e'.$i.'_order', '');
                $parts = array_values(array_map('intval', array_filter(explode(',', $rawOrder), static function ($s) {
                    return $s !== '';
                })));
                $skipped = $malformed || $rawOrder === '' || count($parts) !== $n;
                $expectedOrder = range(0, max(0, $n - 1));
                $ok = ! $skipped && ! $malformed && $parts === $expectedOrder;
                $row = [
                    'n' => $i + 1,
                    'question' => $q['q'],
                    'match_drag' => true,
                    'left' => $left,
                    'right' => $right,
                    'chosen_order' => $skipped ? [] : $parts,
                    'expected_order' => $expectedOrder,
                    'multi' => false,
                    'correct' => $ok,
                    'skipped' => $skipped,
                ];
                if ($weighted) {
                    $row['points'] = $pts;
                    $row['earned_points'] = $ok ? $pts : 0;
                }
                $items[] = $row;
                if ($ok) {
                    $correctCount++;
                    if ($weighted) {
                        $earnedPoints += $pts;
                    }
                }

                continue;
            }
            $expectedRaw = $q['c'] ?? null;
            $isMulti = is_array($expectedRaw);
            if ($isMulti) {
                $expSet = array_values(array_unique(array_map('intval', $expectedRaw)));
                sort($expSet);
                $chosen = $request->input('e'.$i, []);
                if (! is_array($chosen)) {
                    $chosen = [];
                }
                $chosenSet = array_values(array_unique(array_map('intval', $chosen)));
                sort($chosenSet);
                $ok = $expSet === $chosenSet;
                $skipped = $chosenSet === [];
                $row = [
                    'n' => $i + 1,
                    'question' => $q['q'],
                    'options' => $q['a'],
                    'chosen' => $chosenSet,
                    'expected' => $expSet,
                    'multi' => true,
                    'correct' => $ok,
                    'skipped' => $skipped,
                ];
                if ($weighted) {
                    $row['points'] = $pts;
                    $row['earned_points'] = $ok ? $pts : 0;
                }
                $items[] = $row;
            } else {
                $exp = (int) $expectedRaw;
                $has = $request->has('e'.$i);
                $chosenOne = $has ? (int) $request->input('e'.$i) : null;
                $ok = $has && $chosenOne === $exp;
                $row = [
                    'n' => $i + 1,
                    'question' => $q['q'],
                    'options' => $q['a'],
                    'chosen' => $chosenOne,
                    'expected' => $exp,
                    'multi' => false,
                    'correct' => $ok,
                    'skipped' => ! $has,
                ];
                if ($weighted) {
                    $row['points'] = $pts;
                    $row['earned_points'] = $ok ? $pts : 0;
                }
                $items[] = $row;
            }
            if ($ok) {
                $correctCount++;
                if ($weighted) {
                    $earnedPoints += $pts;
                }
            }
        }
        $total = count($questions);
        if ($weighted && $maxPoints > 0) {
            $rawPercent = (int) round(100 * $earnedPoints / $maxPoints);
        } else {
            $rawPercent = $total > 0 ? (int) round(100 * $correctCount / $total) : 0;
        }

        return [
            'correct_count' => $correctCount,
            'wrong_count' => $total - $correctCount,
            'total' => $total,
            'raw_percent' => $rawPercent,
            'items' => $items,
            'max_points' => $weighted ? $maxPoints : null,
            'earned_points' => $weighted ? $earnedPoints : null,
        ];
    }

    /**
     * @param  array<int, array{q:string,a:array,c:int}>  $questions
     */
    protected function scorePercent(array $questions, Request $request, string $prefix): int
    {
        if (count($questions) === 0) {
            return 0;
        }
        $correct = 0;
        foreach (array_keys($questions) as $i) {
            if (! $request->has($prefix.$i)) {
                continue;
            }
            $v = (int) $request->input($prefix.$i);
            if (isset($questions[$i]['c']) && ! is_array($questions[$i]['c']) && $v === (int) $questions[$i]['c']) {
                $correct++;
            }
        }

        return (int) round(100 * $correct / count($questions));
    }

    /**
     * Детальный разбор теста по теории (для страницы результата).
     * Одиночный выбор: c — int; несколько верных: c — int[] (чекбоксы в форме).
     *
     * @param  array<int, array{q:string,a:array,c:int|array<int>}>  $questions
     * @return array{correct_count:int, wrong_count:int, total:int, raw_percent:int, items: array<int, array<string, mixed>>}
     */
    protected function scoreTheoryQuizBreakdown(array $questions, Request $request, string $prefix): array
    {
        $items = [];
        $correctCount = 0;
        foreach (array_keys($questions) as $i) {
            $q = $questions[$i];
            $key = $prefix.$i;
            $expectedRaw = $q['c'] ?? null;

            if (is_array($expectedRaw)) {
                $expSet = array_values(array_unique(array_map('intval', $expectedRaw)));
                sort($expSet);

                $rawIn = $request->input($key);
                $chosenSet = [];
                if (is_array($rawIn)) {
                    $chosenSet = array_values(array_unique(array_map('intval', $rawIn)));
                } elseif ($rawIn !== null && $rawIn !== '') {
                    $chosenSet = [(int) $rawIn];
                }
                sort($chosenSet);

                $has = $request->has($key);
                $skipped = ! $has;
                $ok = $expSet === $chosenSet;
                if ($ok) {
                    $correctCount++;
                }
                $items[] = [
                    'n' => $i + 1,
                    'question' => $q['q'],
                    'options' => $q['a'],
                    'chosen' => $chosenSet,
                    'expected' => $expSet,
                    'multi' => true,
                    'correct' => $ok,
                    'skipped' => $skipped,
                ];
            } else {
                $expected = (int) $expectedRaw;
                $has = $request->has($key);
                $chosen = $has ? (int) $request->input($key) : null;
                $ok = $has && $chosen === $expected;
                if ($ok) {
                    $correctCount++;
                }
                $items[] = [
                    'n' => $i + 1,
                    'question' => $q['q'],
                    'options' => $q['a'],
                    'chosen' => $chosen,
                    'expected' => $expected,
                    'multi' => false,
                    'correct' => $ok,
                    'skipped' => ! $has,
                ];
            }
        }
        $total = count($questions);
        $rawPercent = $total > 0 ? (int) round(100 * $correctCount / $total) : 0;

        return [
            'correct_count' => $correctCount,
            'wrong_count' => $total - $correctCount,
            'total' => $total,
            'raw_percent' => $rawPercent,
            'items' => $items,
        ];
    }

    public function hub(int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        $meta = CourseModuleMeta::resolved($module);
        $p = $learner->progressFor($module);
        if (Schema::hasColumn('module_progress', 'module_access_started_at') && $p->module_access_started_at === null) {
            $p->module_access_started_at = now();
            $p->save();
        }
        // Итог % на хабе = из последнего результата; колонка могла отставать у старых записей — выравниваем.
        $p->syncModuleExamBestScoreFromLastResult();

        $showBriefing = Schema::hasColumn('module_progress', 'hub_briefing_acknowledged_at')
            && $p->hub_briefing_acknowledged_at === null;

        return view('modules.hub', [
            'module' => $module,
            'meta' => $meta,
            'progress' => $p,
            'percent' => $this->scoring->moduleProgressPercent($p),
            'modulePoints' => $this->scoring->modulePointsForProgress($p),
            'passThreshold' => CourseScoringService::PASS_THRESHOLD,
            'showHubBriefing' => $showBriefing,
        ]);
    }

    public function ackHubBriefing(int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        $p = $learner->progressFor($module);
        if (Schema::hasColumn('module_progress', 'hub_briefing_acknowledged_at')) {
            $p->hub_briefing_acknowledged_at = now();
            $p->save();
        }

        return redirect()->route('modules.hub', $module);
    }

    public function theory(int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        if ($r = $this->accessGate->redirectIfModuleLocked($this->learner(), $module)) {
            return $r;
        }
        $meta = CourseModuleMeta::resolved($module);
        $p = $this->learner()->progressFor($module);
        if (! $p->theory_read_at) {
            $sk = 'theory_time_start_'.$module;
            if (! session()->has($sk)) {
                session([$sk => now()->getTimestamp()]);
            }
        }

        return view('modules.theory', [
            'module' => $module,
            'meta' => $meta,
            'progress' => $p,
        ]);
    }

    public function markTheoryRead(int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        if ($r = $this->accessGate->redirectIfModuleLocked($this->learner(), $module)) {
            return $r;
        }
        $p = $this->learner()->progressFor($module);
        $sk = 'theory_time_start_'.$module;
        $ts = session()->pull($sk);
        if ($ts !== null && is_numeric($ts)) {
            $elapsed = max(0, min(86400 * 7, now()->getTimestamp() - (int) $ts));
            if ($elapsed > 0) {
                $p->seconds_theory = (int) ($p->seconds_theory ?? 0) + $elapsed;
            }
        }
        $p->theory_read_at = now();
        $p->save();

        return redirect()->route('modules.hub', $module)->with('ok', 'Теория отмечена как просмотренная.');
    }

    public function theoryQuizShow(int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryNotRead($learner, $module)) {
            return $r;
        }
        $qs = $this->questions($module, 'theory_quiz');

        $deadlineKey = 'theory_quiz_deadline_'.$module;
        $ts = session($deadlineKey);
        if ($ts !== null && is_numeric($ts) && (int) $ts <= now()->getTimestamp()) {
            session()->forget($deadlineKey);
            $ts = null;
        }

        $quizActive = $ts !== null && is_numeric($ts) && (int) $ts > now()->getTimestamp();
        $expiresAtMs = $quizActive ? ((int) $ts) * 1000 : null;

        return view('modules.theory-quiz', [
            'module' => $module,
            'meta' => CourseModuleMeta::resolved($module),
            'questions' => $qs,
            'progress' => $this->learner()->progressFor($module),
            'quizActive' => $quizActive,
            'expiresAtMs' => $expiresAtMs,
            'timeLimitMinutes' => CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES,
        ]);
    }

    public function theoryQuizStart(int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryNotRead($learner, $module)) {
            return $r;
        }
        $qs = $this->questions($module, 'theory_quiz');
        if (count($qs) === 0) {
            return redirect()->route('modules.hub', $module)->with('err', 'Тест по теории для этого модуля не настроен.');
        }

        $deadlineKey = 'theory_quiz_deadline_'.$module;
        session([
            $deadlineKey => now()->addMinutes(CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES)->getTimestamp(),
            'theory_quiz_wall_start_'.$module => now()->getTimestamp(),
        ]);

        $p = $this->learner()->progressFor($module);
        if ($p->theory_quiz_attempts >= 1 || $p->theory_quiz_best_score > 0 || $p->theory_quiz_passed) {
            $p->theory_quiz_passed = false;
            // Лучший процент не обнуляем: при пересдаче доступ к практике не блокируется до завершения новой попытки.
            $p->save();
        }

        return redirect()->route('modules.theory-quiz', $module);
    }

    public function theoryQuizResult(int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryNotRead($learner, $module)) {
            return $r;
        }
        $data = session('theory_quiz_result');
        if (! is_array($data) || (int) ($data['module'] ?? 0) !== $module) {
            $p = $this->learner()->progressFor($module);
            $data = $p->theory_quiz_last_result;
        }
        if (! is_array($data) || (int) ($data['module'] ?? 0) !== $module) {
            return redirect()->route('modules.hub', $module)->with('err', 'Нет сохранённого разбора. Сначала завершите тест с отправкой ответов.');
        }

        return view('modules.theory-quiz-result', [
            'module' => $module,
            'meta' => CourseModuleMeta::resolved($module),
            'result' => $data,
        ]);
    }

    public function theoryQuizSubmit(Request $request, int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryNotRead($learner, $module)) {
            return $r;
        }
        $deadlineKey = 'theory_quiz_deadline_'.$module;
        $ts = session($deadlineKey);
        if ($ts === null || ! is_numeric($ts) || now()->getTimestamp() > (int) $ts) {
            session()->forget($deadlineKey);

            return redirect()->route('modules.theory-quiz', $module)->with('err', 'Время на прохождение теста истекло. Начните попытку снова.');
        }

        $qs = $this->questions($module, 'theory_quiz');
        $p = $this->learner()->progressFor($module);
        $attemptsBefore = (int) $p->theory_quiz_attempts;
        $penalty = $attemptsBefore >= 1 ? CourseScoringService::THEORY_QUIZ_RETAKE_PENALTY_POINTS : 0;

        $breakdown = $this->scoreTheoryQuizBreakdown($qs, $request, 'q');
        $rawPercent = $breakdown['raw_percent'];
        $finalPercent = max(0, $rawPercent - $penalty);

        $wallStart = session()->pull('theory_quiz_wall_start_'.$module);
        if ($wallStart !== null && is_numeric($wallStart)) {
            $cap = CourseScoringService::THEORY_QUIZ_TIME_LIMIT_MINUTES * 60;
            $elapsed = min($cap, max(0, now()->getTimestamp() - (int) $wallStart));
            if ($elapsed > 0) {
                $p->seconds_theory_quiz = (int) ($p->seconds_theory_quiz ?? 0) + $elapsed;
            }
        }

        $payload = [
            'module' => $module,
            'raw_percent' => $rawPercent,
            'penalty_points' => $penalty,
            'final_percent' => $finalPercent,
            'passed' => $finalPercent >= CourseScoringService::PASS_THRESHOLD,
            'threshold' => CourseScoringService::PASS_THRESHOLD,
            'correct_count' => $breakdown['correct_count'],
            'wrong_count' => $breakdown['wrong_count'],
            'total' => $breakdown['total'],
            'items' => $breakdown['items'],
            'recorded_at' => now()->toIso8601String(),
            'attempt_no' => $attemptsBefore + 1,
        ];

        $p->theory_quiz_last_result = $payload;
        $hist = $p->theory_quiz_history ?? [];
        $hist[] = $payload;
        $p->theory_quiz_history = $hist;
        $p->theory_quiz_attempts = $attemptsBefore + 1;
        $p->theory_quiz_best_score = max((int) $p->theory_quiz_best_score, $finalPercent);
        if ($finalPercent >= CourseScoringService::PASS_THRESHOLD) {
            $p->theory_quiz_passed = true;
        }
        $p->save();
        session()->forget($deadlineKey);

        return redirect()->route('modules.theory-quiz.result', $module)->with('theory_quiz_result', $payload);
    }

    public function practiceShow(int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryQuizNotPassed($learner, $module)) {
            return $r;
        }
        $meta = CourseModuleMeta::resolved($module);
        if (CourseModuleMeta::shouldSkipPractice($module)) {
            return redirect()->route('modules.hub', $module)->with(
                'ok',
                'В этом модуле нет практического занятия — перейдите к итоговому тесту (шаг «Экзамен» на странице модуля).'
            );
        }
        $practiceSession = null;
        if (Schema::hasTable('practice_sessions')) {
            $practiceSession = PracticeSession::query()
                ->where('learner_id', $learner->id)
                ->where('module_id', $module)
                ->first();
        }
        $lab = PracticeLabService::make();
        $this->ensurePracticeSegmentStarted($module);

        return view('modules.practice', [
            'module' => $module,
            'meta' => $meta,
            'progress' => $learner->progressFor($module),
            'practiceSession' => $practiceSession,
            'labConfigured' => $lab->isConfigured(),
            'labImage' => $lab->imageForModule($module),
            'labEnabled' => (bool) config('practice_lab.enabled'),
            'allowManualDone' => (bool) config('practice_lab.allow_manual_done'),
        ]);
    }

    public function practiceDone(int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfTheoryQuizNotPassed($learner, $module)) {
            return $r;
        }
        if (CourseModuleMeta::shouldSkipPractice($module)) {
            return redirect()->route('modules.hub', $module)->with(
                'ok',
                'В этом модуле нет практического занятия — перейдите к итоговому тесту.'
            );
        }
        if (config('practice_lab.enabled') && ! config('practice_lab.allow_manual_done')) {
            return redirect()->route('modules.practice', $module)->with(
                'err',
                'Отметка вручную отключена. Завершите практику через кнопку после проверки или включите PRACTICE_LAB_ALLOW_MANUAL_DONE для методиста.'
            );
        }
        $p = $this->learner()->progressFor($module);
        $this->accumulatePracticeSegmentSeconds($p, $module);
        $p->practice_done_at = now();
        $p->save();

        return redirect()->route('modules.hub', $module)->with('ok', 'Практика отмечена как выполненная.');
    }

    public function examShow(int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfExamPrerequisitesMissing($learner, $module)) {
            return $r;
        }
        $p = $learner->progressFor($module);
        $qs = $this->questions($module, 'module_exam');
        if (count($qs) === 0) {
            return redirect()->route('modules.hub', $module)->with('err', 'Итоговый тест для этого модуля не настроен.');
        }
        if ((int) $p->module_exam_attempts >= CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS) {
            return redirect()->route('modules.exam.result', $module)->with(
                'err',
                'Исчерпаны все попытки итогового теста ('.CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS.'). Ниже сохранённый результат.'
            );
        }

        $attemptNo = (int) $p->module_exam_attempts + 1;
        $deadline = $p->module_exam_deadline_at;
        $deadlineFor = $p->module_exam_deadline_for_attempt;

        $deadlineValidForAttempt = $deadline !== null
            && $deadlineFor !== null
            && (int) $deadlineFor === $attemptNo
            && $deadline->isFuture();

        if ($deadline !== null && $deadlineFor !== null && (int) $deadlineFor === $attemptNo && $deadline->isPast()) {
            $p->module_exam_deadline_at = null;
            $p->module_exam_deadline_for_attempt = null;
            $p->save();

            return redirect()->route('modules.hub', $module)->with(
                'err',
                'Отведённое на итоговый тест время ('.$this->moduleExamTimeLimitMinutes($module).' мин.) истекло. Начните попытку снова.'
            );
        }

        $examActive = $deadlineValidForAttempt;
        $expiresAtMs = $examActive && $deadline !== null ? ($deadline->getTimestamp() * 1000) : null;

        return view('modules.exam', [
            'module' => $module,
            'meta' => CourseModuleMeta::resolved($module),
            'questions' => $qs,
            'progress' => $p,
            'attemptNumber' => $attemptNo,
            'maxAttempts' => CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS,
            'retakePenalty' => CourseScoringService::MODULE_EXAM_RETAKE_PENALTY_POINTS,
            'passThreshold' => CourseScoringService::PASS_THRESHOLD,
            'timeLimitMinutes' => $this->moduleExamTimeLimitMinutes($module),
            'examActive' => $examActive,
            'expiresAtMs' => $expiresAtMs,
            'needsRetakeAck' => (int) $p->module_exam_attempts >= 1,
        ]);
    }

    public function examStart(int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfExamPrerequisitesMissing($learner, $module)) {
            return $r;
        }
        $p = $learner->progressFor($module);
        $qs = $this->questions($module, 'module_exam');
        if (count($qs) === 0) {
            return redirect()->route('modules.hub', $module)->with('err', 'Итоговый тест для этого модуля не настроен.');
        }
        if ((int) $p->module_exam_attempts >= CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS) {
            return redirect()->route('modules.exam.result', $module)->with(
                'err',
                'Исчерпаны все попытки итогового теста ('.CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS.').'
            );
        }

        $attemptNo = (int) $p->module_exam_attempts + 1;
        $deadline = $p->module_exam_deadline_at;
        $deadlineFor = $p->module_exam_deadline_for_attempt;
        $alreadyActive = $deadline !== null
            && $deadlineFor !== null
            && (int) $deadlineFor === $attemptNo
            && $deadline->isFuture();

        if ($alreadyActive) {
            return redirect()->route('modules.exam', $module);
        }

        $p->module_exam_deadline_at = now()->addMinutes($this->moduleExamTimeLimitMinutes($module));
        $p->module_exam_deadline_for_attempt = $attemptNo;
        $p->save();

        return redirect()->route('modules.exam', $module);
    }

    public function examResult(int $module): View|RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        $p = $learner->progressFor($module);
        $data = session('module_exam_result');
        if (! is_array($data)) {
            $data = $p->module_exam_last_result;
        }
        if (empty($data) || ! is_array($data)) {
            return redirect()->route('modules.hub', $module)->with('err', 'Пока нет результата итогового теста. Пройдите тест с шага4.');
        }

        $until = isset($data['breakdown_visible_until']) ? (int) $data['breakdown_visible_until'] : 0;
        $showExamBreakdown = $until > 0 && $until > now()->getTimestamp();
        $breakdownExpired = $until > 0 && ! $showExamBreakdown;

        $r = $data;
        if (! $showExamBreakdown) {
            unset($r['items'], $r['breakdown_visible_until']);
        }

        return view('modules.exam-result', [
            'module' => $module,
            'meta' => CourseModuleMeta::resolved($module),
            'r' => $r,
            'progress' => $p,
            'showExamBreakdown' => $showExamBreakdown,
            'breakdownUntilTs' => $showExamBreakdown ? $until : null,
            'breakdownExpired' => $breakdownExpired,
        ]);
    }

    public function examSubmit(Request $request, int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        $learner = $this->learner();
        if ($r = $this->accessGate->redirectIfModuleLocked($learner, $module)) {
            return $r;
        }
        if ($r = $this->accessGate->redirectIfExamPrerequisitesMissing($learner, $module)) {
            return $r;
        }
        $qs = $this->questions($module, 'module_exam');
        $p = $learner->progressFor($module);
        if (count($qs) === 0) {
            return redirect()->route('modules.hub', $module)->with('err', 'Тест не настроен.');
        }
        if ((int) $p->module_exam_attempts >= CourseScoringService::MODULE_EXAM_MAX_ATTEMPTS) {
            return redirect()->route('modules.exam.result', $module)->with('err', 'Попытки исчерпаны.');
        }

        $attemptNo = (int) $p->module_exam_attempts + 1;
        $deadline = $p->module_exam_deadline_at;
        $deadlineFor = $p->module_exam_deadline_for_attempt;
        if ($deadline === null
            || $deadlineFor === null
            || (int) $deadlineFor !== $attemptNo
            || $deadline->isPast()) {
            $p->module_exam_deadline_at = null;
            $p->module_exam_deadline_for_attempt = null;
            $p->save();

            return redirect()->route('modules.hub', $module)->with(
                'err',
                'Время на прохождение итогового теста истекло. Начните попытку снова.'
            );
        }

        $breakdown = $this->scoreModuleExamBreakdown($qs, $request);
        $attemptAfter = (int) $p->module_exam_attempts + 1;
        $examDeadline = $p->module_exam_deadline_at;
        $raw = $breakdown['raw_percent'];
        $penaltyApplied = $attemptAfter >= 2;
        $final = $penaltyApplied
            ? max(0, $raw - CourseScoringService::MODULE_EXAM_RETAKE_PENALTY_POINTS)
            : $raw;
        $wasPassed = $p->module_exam_passed;
        $passedThisAttempt = $final >= CourseScoringService::PASS_THRESHOLD;
        $modulePassed = $wasPassed || $passedThisAttempt;

        $payload = [
            'module' => $module,
            'raw_percent' => $raw,
            'final_percent' => $final,
            'passed' => $modulePassed,
            'passed_this_attempt' => $passedThisAttempt,
            'threshold' => CourseScoringService::PASS_THRESHOLD,
            'attempt' => $attemptAfter,
            'penalty_applied' => $penaltyApplied,
            'penalty_points' => $penaltyApplied ? CourseScoringService::MODULE_EXAM_RETAKE_PENALTY_POINTS : 0,
            'items' => $breakdown['items'],
            'correct_count' => $breakdown['correct_count'],
            'wrong_count' => $breakdown['wrong_count'],
            'total' => $breakdown['total'],
            'max_points' => $breakdown['max_points'] ?? null,
            'earned_points' => $breakdown['earned_points'] ?? null,
            'breakdown_visible_until' => now()->addMinutes(CourseScoringService::MODULE_EXAM_BREAKDOWN_VISIBLE_MINUTES)->getTimestamp(),
            'recorded_at' => now()->toIso8601String(),
        ];

        $p->module_exam_attempts = $attemptAfter;
        // Итог для зачёта и UI — только последняя завершённая попытка (пересдача заменяет предыдущий %, без max с прошлым).
        $p->module_exam_best_score = (int) $final;
        $p->module_exam_passed = $modulePassed;
        $p->module_exam_last_result = $payload;
        $examHist = $p->module_exam_history ?? [];
        $examHist[] = $payload;
        $p->module_exam_history = $examHist;
        if ($examDeadline !== null) {
            $lim = $this->moduleExamTimeLimitMinutes($module);
            $start = $examDeadline->copy()->subMinutes($lim);
            $cap = $lim * 60;
            $elapsed = min($cap, max(0, now()->diffInSeconds($start)));
            if ($elapsed > 0) {
                $p->seconds_module_exam = (int) ($p->seconds_module_exam ?? 0) + $elapsed;
            }
        }
        if ($modulePassed && $p->module_cleared_at === null && Schema::hasColumn('module_progress', 'module_cleared_at')) {
            $p->module_cleared_at = now();
        }
        $p->module_exam_deadline_at = null;
        $p->module_exam_deadline_for_attempt = null;
        $p->save();

        return redirect()->route('modules.exam.result', $module)->with('module_exam_result', $payload);
    }

    private function practiceSegmentSessionKey(int $module): string
    {
        return 'practice_segment_start_'.$module;
    }

    private function ensurePracticeSegmentStarted(int $module): void
    {
        $k = $this->practiceSegmentSessionKey($module);
        if (! session()->has($k)) {
            session([$k => now()->getTimestamp()]);
        }
    }

    private function accumulatePracticeSegmentSeconds(ModuleProgress $p, int $module): void
    {
        $k = $this->practiceSegmentSessionKey($module);
        $ts = session()->pull($k);
        if ($ts === null || ! is_numeric($ts)) {
            return;
        }
        $elapsed = max(0, min(86400 * 14, now()->getTimestamp() - (int) $ts));
        if ($elapsed > 0) {
            $p->seconds_practice = (int) ($p->seconds_practice ?? 0) + $elapsed;
        }
    }

    public function saveDifficulties(Request $request, int $module): RedirectResponse
    {
        abort_unless($module >= 1 && $module <= 9, 404);
        if ($r = $this->accessGate->redirectIfModuleLocked($this->learner(), $module)) {
            return $r;
        }
        $p = $this->learner()->progressFor($module);
        $p->difficulty_flags = [
            'theory' => $request->boolean('d_theory'),
            'theory_quiz' => $request->boolean('d_theory_quiz'),
            'practice' => $request->boolean('d_practice'),
            'module_exam' => $request->boolean('d_module_exam'),
        ];
        $p->save();

        return redirect()->route('modules.hub', $module)->with('ok', 'Отметки о сложностях сохранены.');
    }
}
