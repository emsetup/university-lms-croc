<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CourseSurveySubmission;
use App\Models\Learner;
use App\Models\ModuleProgress;
use App\Support\LearnerDisplay;
use App\Support\SectionProgress;
use Illuminate\Support\Facades\Schema;

/**
 * Аналитика участников раздела: кому доступен, кто прошёл, карточка результата.
 */
final class SectionParticipantsAnalyticsService
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ATTEMPTED = 'attempted';

    public const STATUS_PENDING = 'pending';

    public function __construct(
        private LearnerContentVisibilityService $visibility,
        private CourseSectionService $sections,
        private SurveyResponseExportService $surveyExport,
    ) {}

    /**
     * @return array{
     *     audience: string,
     *     anonymous: bool,
     *     section_type: string,
     *     counters: array{eligible: ?int, completed: int, pending: ?int, attempted: ?int},
     *     rows: list<array{
     *         learner_id: ?int,
     *         display_name: ?string,
     *         email: ?string,
     *         status: string,
     *         status_label: string,
     *         completed_at: ?string,
     *         meta: ?string,
     *         can_open_detail: bool
     *     }>
     * }
     */
    public function participantsForSection(CourseSection $section): array
    {
        $audience = $this->visibility->sectionAudienceMode($section);
        $anonymous = $this->isAnonymousSurvey($section);
        $completions = $this->completionMapForSection($section);

        if ($audience === Course::VIEW_AUDIENCE_RESTRICTED) {
            $eligibleIds = $this->visibility->eligibleLearnerIdsForSection($section);
            $idSet = [];
            foreach ($eligibleIds as $id) {
                $idSet[$id] = true;
            }
            foreach (array_keys($completions) as $id) {
                $idSet[(int) $id] = true;
            }
            $learnerIds = array_map('intval', array_keys($idSet));
            sort($learnerIds);

            $rows = $this->buildNamedRows($section, $learnerIds, $completions, $anonymous, true);
            $completed = 0;
            $attempted = 0;
            $pending = 0;
            foreach ($rows as $row) {
                if ($row['status'] === self::STATUS_COMPLETED) {
                    $completed++;
                } elseif ($row['status'] === self::STATUS_ATTEMPTED) {
                    $attempted++;
                } else {
                    $pending++;
                }
            }

            return [
                'audience' => $audience,
                'anonymous' => $anonymous,
                'section_type' => (string) $section->type,
                'counters' => [
                    'eligible' => count($eligibleIds),
                    'completed' => $completed,
                    'pending' => $pending,
                    'attempted' => $this->supportsAttempted($section) ? $attempted : null,
                ],
                'rows' => $rows,
            ];
        }

        // Открыт для всех — только прошедшие (и для анонимного опроса — обезличенные строки).
        if ($anonymous && $section->type === CourseSection::TYPE_SURVEY) {
            $rows = $this->buildAnonymousCompletedRows($completions);
        } else {
            $completedIds = [];
            foreach ($completions as $learnerId => $info) {
                if (($info['status'] ?? '') === self::STATUS_COMPLETED) {
                    $completedIds[] = (int) $learnerId;
                }
            }
            $completedIds = array_values(array_unique($completedIds));
            sort($completedIds);
            $rows = $this->buildNamedRows($section, $completedIds, $completions, false, false);
        }

        $completedCount = count($rows);

        return [
            'audience' => Course::VIEW_AUDIENCE_ALL,
            'anonymous' => $anonymous,
            'section_type' => (string) $section->type,
            'counters' => [
                'eligible' => null,
                'completed' => $completedCount,
                'pending' => null,
                'attempted' => null,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     section_type: string,
     *     anonymous: bool,
     *     status: string,
     *     status_label: string,
     *     learner: ?array{id: int, display_name: string, email: string},
     *     completed_at: ?string,
     *     survey: ?array,
     *     quiz: ?array,
     *     simple: ?array{message: string}
     * }
     */
    public function detailForLearner(CourseSection $section, int $learnerId): array
    {
        $anonymous = $this->isAnonymousSurvey($section);
        $completions = $this->completionMapForSection($section);
        $info = $completions[$learnerId] ?? [
            'status' => self::STATUS_PENDING,
            'completed_at' => null,
            'meta' => null,
        ];

        $learner = Learner::query()->find($learnerId);
        $learnerPayload = $learner instanceof Learner
            ? [
                'id' => (int) $learner->id,
                'display_name' => LearnerDisplay::portalDisplayName($learner) ?: (string) ($learner->email ?? ''),
                'email' => (string) ($learner->email ?? ''),
            ]
            : null;

        $base = [
            'ok' => true,
            'section_type' => (string) $section->type,
            'anonymous' => $anonymous,
            'status' => (string) $info['status'],
            'status_label' => $this->statusLabel((string) $info['status'], (string) $section->type),
            'learner' => $anonymous ? null : $learnerPayload,
            'completed_at' => $info['completed_at'] ?? null,
            'survey' => null,
            'quiz' => null,
            'simple' => null,
        ];

        if ($section->type === CourseSection::TYPE_SURVEY) {
            $card = $this->surveyExport->cardForLearner($section, $learnerId);
            $base['survey'] = $card;

            return $base;
        }

        if (in_array($section->type, [CourseSection::TYPE_QUIZ, CourseSection::TYPE_EXAM], true)) {
            $base['quiz'] = $this->quizDetail($section, $learnerId);

            return $base;
        }

        $done = ($info['status'] ?? '') === self::STATUS_COMPLETED;
        $base['simple'] = [
            'message' => $done
                ? ('Раздел выполнен'.($info['completed_at'] ? ' · '.$info['completed_at'] : ''))
                : 'Раздел ещё не выполнен',
        ];

        return $base;
    }

    private function supportsAttempted(CourseSection $section): bool
    {
        return in_array($section->type, [CourseSection::TYPE_QUIZ, CourseSection::TYPE_EXAM], true);
    }

    private function isAnonymousSurvey(CourseSection $section): bool
    {
        if ($section->type !== CourseSection::TYPE_SURVEY) {
            return false;
        }
        $settings = $this->sections->mergedSettings($section);

        return (bool) ($settings['anonymous'] ?? false);
    }

    /**
     * @return array<int, array{status: string, completed_at: ?string, meta: ?string}>
     */
    private function completionMapForSection(CourseSection $section): array
    {
        return match ($section->type) {
            CourseSection::TYPE_SURVEY => $this->surveyCompletions($section),
            CourseSection::TYPE_QUIZ, CourseSection::TYPE_EXAM => $this->quizCompletions($section),
            CourseSection::TYPE_TEXT => $this->textCompletions($section),
            CourseSection::TYPE_PRACTICE => $this->practiceCompletions($section),
            default => [],
        };
    }

    /**
     * @return array<int, array{status: string, completed_at: ?string, meta: ?string}>
     */
    private function surveyCompletions(CourseSection $section): array
    {
        if (! Schema::hasTable('course_survey_submissions')) {
            return [];
        }

        $rows = CourseSurveySubmission::query()
            ->where('course_section_id', (int) $section->id)
            ->orderBy('submitted_at')
            ->get(['learner_id', 'submitted_at']);

        $out = [];
        foreach ($rows as $row) {
            $lid = (int) $row->learner_id;
            $out[$lid] = [
                'status' => self::STATUS_COMPLETED,
                'completed_at' => $row->submitted_at?->format('d.m.Y H:i'),
                'meta' => null,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{status: string, completed_at: ?string, meta: ?string}>
     */
    private function quizCompletions(CourseSection $section): array
    {
        if (! Schema::hasTable('module_progress')) {
            return [];
        }

        $moduleId = (int) $section->course_module_id;
        $sole = $this->sections->isSoleSectionOfType($section);
        $progressRows = ModuleProgress::query()
            ->where('course_module_id', $moduleId)
            ->get();

        $out = [];
        foreach ($progressRows as $p) {
            $lid = (int) $p->learner_id;
            $st = SectionProgress::quizState($p, $section, $sole);
            $passed = $section->type === CourseSection::TYPE_EXAM
                ? $this->sections->isSectionExamPassed($p, $section, $sole)
                : $this->sections->isSectionQuizPassed($p, $section, $sole);
            $attempts = (int) ($st['attempts'] ?? 0);
            $best = (int) ($st['best_score'] ?? 0);

            if (! $passed && $attempts < 1 && $best < 1 && empty($st['last_result']) && empty($st['history'])) {
                continue;
            }

            $status = $passed ? self::STATUS_COMPLETED : self::STATUS_ATTEMPTED;
            $out[$lid] = [
                'status' => $status,
                'completed_at' => null,
                'meta' => $best > 0 || $attempts > 0
                    ? trim(($best > 0 ? $best.'%' : '').($attempts > 0 ? ($best > 0 ? ' · ' : '').$attempts.' попыт.' : ''))
                    : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{status: string, completed_at: ?string, meta: ?string}>
     */
    private function textCompletions(CourseSection $section): array
    {
        if (! Schema::hasTable('module_progress')) {
            return [];
        }

        $sole = $this->sections->isSoleSectionOfType($section);
        $progressRows = ModuleProgress::query()
            ->where('course_module_id', (int) $section->course_module_id)
            ->get();

        $out = [];
        foreach ($progressRows as $p) {
            if (! SectionProgress::isTextRead($p, $section, $sole)) {
                continue;
            }
            $st = SectionProgress::stateFor($p, (int) $section->id);
            $readAt = isset($st['read_at']) ? (string) $st['read_at'] : null;
            if ($readAt === null && $sole && $p->theory_read_at) {
                $readAt = $p->theory_read_at->format('d.m.Y H:i');
            } elseif ($readAt !== null) {
                $readAt = $this->formatIsoOrDate($readAt);
            }
            $out[(int) $p->learner_id] = [
                'status' => self::STATUS_COMPLETED,
                'completed_at' => $readAt,
                'meta' => null,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{status: string, completed_at: ?string, meta: ?string}>
     */
    private function practiceCompletions(CourseSection $section): array
    {
        if (! Schema::hasTable('module_progress')) {
            return [];
        }

        $sole = $this->sections->isSoleSectionOfType($section);
        $progressRows = ModuleProgress::query()
            ->where('course_module_id', (int) $section->course_module_id)
            ->get();

        $out = [];
        foreach ($progressRows as $p) {
            if (! SectionProgress::isPracticeDone($p, $section, $sole)) {
                continue;
            }
            $st = SectionProgress::stateFor($p, (int) $section->id);
            $doneAt = isset($st['done_at']) ? (string) $st['done_at'] : null;
            if ($doneAt === null && $sole && $p->practice_done_at) {
                $doneAt = $p->practice_done_at->format('d.m.Y H:i');
            } elseif ($doneAt !== null) {
                $doneAt = $this->formatIsoOrDate($doneAt);
            }
            $out[(int) $p->learner_id] = [
                'status' => self::STATUS_COMPLETED,
                'completed_at' => $doneAt,
                'meta' => null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $learnerIds
     * @param  array<int, array{status: string, completed_at: ?string, meta: ?string}>  $completions
     * @return list<array{
     *     learner_id: ?int,
     *     display_name: ?string,
     *     email: ?string,
     *     status: string,
     *     status_label: string,
     *     completed_at: ?string,
     *     meta: ?string,
     *     can_open_detail: bool
     * }>
     */
    private function buildNamedRows(
        CourseSection $section,
        array $learnerIds,
        array $completions,
        bool $anonymous,
        bool $includePending
    ): array {
        if ($learnerIds === []) {
            return [];
        }

        if ($anonymous) {
            // В ограниченном анонимном опросе показываем статус без ПДн.
            $rows = [];
            $n = 0;
            foreach ($learnerIds as $lid) {
                $info = $completions[$lid] ?? null;
                $status = $info['status'] ?? self::STATUS_PENDING;
                if (! $includePending && $status === self::STATUS_PENDING) {
                    continue;
                }
                if ($status !== self::STATUS_COMPLETED) {
                    $rows[] = [
                        'learner_id' => null,
                        'display_name' => 'Участник',
                        'email' => null,
                        'status' => $status,
                        'status_label' => $this->statusLabel($status, (string) $section->type),
                        'completed_at' => null,
                        'meta' => null,
                        'can_open_detail' => false,
                    ];

                    continue;
                }
                $n++;
                $rows[] = [
                    'learner_id' => null,
                    'display_name' => 'Ответ №'.$n,
                    'email' => null,
                    'status' => self::STATUS_COMPLETED,
                    'status_label' => $this->statusLabel(self::STATUS_COMPLETED, (string) $section->type),
                    'completed_at' => $info['completed_at'] ?? null,
                    'meta' => null,
                    'can_open_detail' => false,
                ];
            }

            return $rows;
        }

        $learners = Learner::query()->whereIn('id', $learnerIds)->get()->keyBy('id');
        $names = LearnerDisplay::portalDisplayNamesByLearnerIds($learnerIds);
        $rows = [];
        foreach ($learnerIds as $lid) {
            $info = $completions[$lid] ?? [
                'status' => self::STATUS_PENDING,
                'completed_at' => null,
                'meta' => null,
            ];
            $status = (string) $info['status'];
            if (! $includePending && $status === self::STATUS_PENDING) {
                continue;
            }
            $learner = $learners->get($lid);
            $email = $learner instanceof Learner ? (string) ($learner->email ?? '') : '';
            $display = $names[$lid] ?? ($email !== '' ? $email : 'Обучающийся #'.$lid);
            $canOpen = $status === self::STATUS_COMPLETED
                || ($this->supportsAttempted($section) && $status === self::STATUS_ATTEMPTED)
                || in_array($section->type, [CourseSection::TYPE_TEXT, CourseSection::TYPE_PRACTICE], true);

            $rows[] = [
                'learner_id' => $lid,
                'display_name' => $display,
                'email' => $email !== '' ? $email : null,
                'status' => $status,
                'status_label' => $this->statusLabel($status, (string) $section->type),
                'completed_at' => $info['completed_at'] ?? null,
                'meta' => $info['meta'] ?? null,
                'can_open_detail' => $canOpen,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $order = [
                self::STATUS_COMPLETED => 0,
                self::STATUS_ATTEMPTED => 1,
                self::STATUS_PENDING => 2,
            ];
            $cmp = ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param  array<int, array{status: string, completed_at: ?string, meta: ?string}>  $completions
     * @return list<array{
     *     learner_id: ?int,
     *     display_name: ?string,
     *     email: ?string,
     *     status: string,
     *     status_label: string,
     *     completed_at: ?string,
     *     meta: ?string,
     *     can_open_detail: bool
     * }>
     */
    private function buildAnonymousCompletedRows(array $completions): array
    {
        $rows = [];
        $n = 0;
        foreach ($completions as $info) {
            if (($info['status'] ?? '') !== self::STATUS_COMPLETED) {
                continue;
            }
            $n++;
            $rows[] = [
                'learner_id' => null,
                'display_name' => 'Ответ №'.$n,
                'email' => null,
                'status' => self::STATUS_COMPLETED,
                'status_label' => 'Прошёл',
                'completed_at' => $info['completed_at'] ?? null,
                'meta' => null,
                'can_open_detail' => false,
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     passed: bool,
     *     attempts: int,
     *     best_score: int,
     *     items: list<array<string, mixed>>
     * }
     */
    private function quizDetail(CourseSection $section, int $learnerId): array
    {
        $p = ModuleProgress::query()
            ->where('course_module_id', (int) $section->course_module_id)
            ->where('learner_id', $learnerId)
            ->first();

        if ($p === null) {
            return [
                'passed' => false,
                'attempts' => 0,
                'best_score' => 0,
                'items' => [],
            ];
        }

        $sole = $this->sections->isSoleSectionOfType($section);
        $st = SectionProgress::quizState($p, $section, $sole);
        $passed = $section->type === CourseSection::TYPE_EXAM
            ? $this->sections->isSectionExamPassed($p, $section, $sole)
            : $this->sections->isSectionQuizPassed($p, $section, $sole);

        $last = is_array($st['last_result'] ?? null) ? $st['last_result'] : null;
        $items = [];
        if (is_array($last) && isset($last['items']) && is_array($last['items'])) {
            $items = $last['items'];
        } elseif (is_array($last) && isset($last['breakdown']) && is_array($last['breakdown'])) {
            $items = $last['breakdown'];
        }

        return [
            'passed' => $passed,
            'attempts' => (int) ($st['attempts'] ?? 0),
            'best_score' => (int) ($st['best_score'] ?? 0),
            'items' => $items,
        ];
    }

    private function statusLabel(string $status, string $sectionType): string
    {
        return match ($status) {
            self::STATUS_COMPLETED => match ($sectionType) {
                CourseSection::TYPE_SURVEY => 'Прошёл',
                CourseSection::TYPE_QUIZ, CourseSection::TYPE_EXAM => 'Сдал',
                CourseSection::TYPE_TEXT => 'Прочитал',
                CourseSection::TYPE_PRACTICE => 'Выполнил',
                default => 'Готово',
            },
            self::STATUS_ATTEMPTED => 'Есть попытки',
            default => 'Не прошёл',
        };
    }

    private function formatIsoOrDate(string $raw): string
    {
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }

        return date('d.m.Y H:i', $ts);
    }
}
