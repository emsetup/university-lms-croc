<?php

namespace App\Support;

use App\Models\CourseSection;
use App\Models\ModuleProgress;
use Illuminate\Support\Facades\Schema;

/**
 * Прогресс по отдельным разделам модуля (JSON в module_progress.section_states).
 * Для модулей с одним разделом каждого типа читает legacy-колонки module_progress.
 */
final class SectionProgress
{
    /**
     * @return array<string, mixed>
     */
    public static function states(ModuleProgress $p): array
    {
        if (! Schema::hasColumn('module_progress', 'section_states')) {
            return [];
        }
        $raw = $p->section_states ?? null;

        return is_array($raw) ? $raw : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function stateFor(ModuleProgress $p, int $sectionId): array
    {
        $all = self::states($p);
        $key = (string) $sectionId;
        $st = $all[$key] ?? [];

        return is_array($st) ? $st : [];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function mergeState(ModuleProgress $p, int $sectionId, array $patch): void
    {
        if (! Schema::hasColumn('module_progress', 'section_states')) {
            return;
        }
        $all = self::states($p);
        $key = (string) $sectionId;
        $prev = self::stateFor($p, $sectionId);
        $all[$key] = array_merge($prev, $patch);
        $p->section_states = $all;
    }

    public static function isTextRead(ModuleProgress $p, CourseSection $section, bool $soleTextInModule): bool
    {
        $st = self::stateFor($p, (int) $section->id);
        if (! empty($st['read_at'])) {
            return true;
        }
        if ($soleTextInModule && $p->theory_read_at) {
            return true;
        }

        return false;
    }

    public static function markTextRead(ModuleProgress $p, CourseSection $section, bool $soleTextInModule): void
    {
        $now = now()->toIso8601String();
        self::mergeState($p, (int) $section->id, ['read_at' => $now]);
        if ($soleTextInModule) {
            $p->theory_read_at = now();
        }
    }

    /**
     * @return array{passed:bool,attempts:int,best_score:int,last_result:?array,history:array}
     */
    public static function quizState(ModuleProgress $p, CourseSection $section, bool $soleQuizInModule): array
    {
        $st = self::stateFor($p, (int) $section->id);
        if ($st !== [] && (isset($st['attempts']) || isset($st['best_score']) || isset($st['passed']))) {
            return [
                'passed' => (bool) ($st['passed'] ?? false),
                'attempts' => (int) ($st['attempts'] ?? 0),
                'best_score' => (int) ($st['best_score'] ?? 0),
                'last_result' => is_array($st['last_result'] ?? null) ? $st['last_result'] : null,
                'history' => is_array($st['history'] ?? null) ? $st['history'] : [],
            ];
        }
        if ($soleQuizInModule && $section->type === CourseSection::TYPE_QUIZ) {
            return [
                'passed' => (bool) $p->theory_quiz_passed,
                'attempts' => (int) ($p->theory_quiz_attempts ?? 0),
                'best_score' => (int) ($p->theory_quiz_best_score ?? 0),
                'last_result' => is_array($p->theory_quiz_last_result ?? null) ? $p->theory_quiz_last_result : null,
                'history' => is_array($p->theory_quiz_history ?? null) ? $p->theory_quiz_history : [],
            ];
        }
        if ($soleQuizInModule && $section->type === CourseSection::TYPE_EXAM) {
            return [
                'passed' => (bool) $p->module_exam_passed,
                'attempts' => (int) ($p->module_exam_attempts ?? 0),
                'best_score' => (int) ($p->module_exam_best_score ?? 0),
                'last_result' => is_array($p->module_exam_last_result ?? null) ? $p->module_exam_last_result : null,
                'history' => is_array($p->module_exam_history ?? null) ? $p->module_exam_history : [],
            ];
        }

        return [
            'passed' => false,
            'attempts' => 0,
            'best_score' => 0,
            'last_result' => null,
            'history' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $quizPatch
     */
    public static function saveQuizState(ModuleProgress $p, CourseSection $section, bool $soleQuizInModule, array $quizPatch): void
    {
        self::mergeState($p, (int) $section->id, $quizPatch);
        if (! $soleQuizInModule) {
            return;
        }
        if ($section->type === CourseSection::TYPE_QUIZ) {
            if (array_key_exists('passed', $quizPatch)) {
                $p->theory_quiz_passed = (bool) $quizPatch['passed'];
            }
            if (array_key_exists('attempts', $quizPatch)) {
                $p->theory_quiz_attempts = (int) $quizPatch['attempts'];
            }
            if (array_key_exists('best_score', $quizPatch)) {
                $p->theory_quiz_best_score = (int) $quizPatch['best_score'];
            }
            if (array_key_exists('last_result', $quizPatch)) {
                $p->theory_quiz_last_result = $quizPatch['last_result'];
            }
            if (array_key_exists('history', $quizPatch)) {
                $p->theory_quiz_history = $quizPatch['history'];
            }
        }
        if ($section->type === CourseSection::TYPE_EXAM) {
            if (array_key_exists('passed', $quizPatch)) {
                $p->module_exam_passed = (bool) $quizPatch['passed'];
            }
            if (array_key_exists('attempts', $quizPatch)) {
                $p->module_exam_attempts = (int) $quizPatch['attempts'];
            }
            if (array_key_exists('best_score', $quizPatch)) {
                $p->module_exam_best_score = (int) $quizPatch['best_score'];
            }
            if (array_key_exists('last_result', $quizPatch)) {
                $p->module_exam_last_result = $quizPatch['last_result'];
            }
            if (array_key_exists('history', $quizPatch)) {
                $p->module_exam_history = $quizPatch['history'];
            }
        }
    }

    public static function isPracticeDone(ModuleProgress $p, CourseSection $section, bool $solePracticeInModule): bool
    {
        $st = self::stateFor($p, (int) $section->id);
        if (! empty($st['done_at'])) {
            return true;
        }
        if ($solePracticeInModule && $p->practice_done_at) {
            return true;
        }

        return false;
    }

    public static function practicePercent(ModuleProgress $p, CourseSection $section, bool $solePracticeInModule): int
    {
        $st = self::stateFor($p, (int) $section->id);
        if (isset($st['lab_percent']) && is_numeric($st['lab_percent'])) {
            return (int) $st['lab_percent'];
        }
        if ($solePracticeInModule) {
            if ($p->practice_lab_percent !== null) {
                return (int) $p->practice_lab_percent;
            }

            return $p->practice_done_at ? 100 : 0;
        }

        return self::isPracticeDone($p, $section, false) ? 100 : 0;
    }

    /**
     * @return array{deadline: ?\Carbon\CarbonInterface, for_attempt: int}
     */
    public static function examDeadline(ModuleProgress $p, CourseSection $section, bool $soleExamInModule): array
    {
        $st = self::stateFor($p, (int) $section->id);
        if (! empty($st['exam_deadline_at'])) {
            try {
                $deadline = \Carbon\Carbon::parse((string) $st['exam_deadline_at']);

                return [
                    'deadline' => $deadline,
                    'for_attempt' => (int) ($st['exam_deadline_for_attempt'] ?? 0),
                ];
            } catch (\Throwable) {
                // fall through
            }
        }
        if ($soleExamInModule && $section->type === CourseSection::TYPE_EXAM) {
            return [
                'deadline' => $p->module_exam_deadline_at,
                'for_attempt' => (int) ($p->module_exam_deadline_for_attempt ?? 0),
            ];
        }

        return ['deadline' => null, 'for_attempt' => 0];
    }

    public static function setExamDeadline(ModuleProgress $p, CourseSection $section, bool $soleExamInModule, \Carbon\CarbonInterface $deadline, int $forAttempt): void
    {
        self::mergeState($p, (int) $section->id, [
            'exam_deadline_at' => $deadline->toIso8601String(),
            'exam_deadline_for_attempt' => $forAttempt,
        ]);
        if ($soleExamInModule && $section->type === CourseSection::TYPE_EXAM) {
            $p->module_exam_deadline_at = $deadline;
            $p->module_exam_deadline_for_attempt = $forAttempt;
        }
    }

    public static function clearExamDeadline(ModuleProgress $p, CourseSection $section, bool $soleExamInModule): void
    {
        self::mergeState($p, (int) $section->id, [
            'exam_deadline_at' => null,
            'exam_deadline_for_attempt' => null,
        ]);
        if ($soleExamInModule && $section->type === CourseSection::TYPE_EXAM) {
            $p->module_exam_deadline_at = null;
            $p->module_exam_deadline_for_attempt = null;
        }
    }
}
