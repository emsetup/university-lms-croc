<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentViewAudienceRule;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseQuizBank;
use App\Models\CourseSection;
use App\Models\FinalLabResult;
use App\Models\Learner;
use App\Models\ModuleProgress;
use App\Models\PortalStaff;
use App\Models\PracticeImage;
use App\Support\PortalChangelog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сводная статистика портала для администратора и аудитора.
 */
final class PortalPlatformStatsService
{
    /** Роли, которым выдают права на создание/наполнение контента. */
    private const AUTHOR_ROLES = [
        PortalStaff::ROLE_COURSE_CREATOR,
        PortalStaff::ROLE_COURSE_EDITOR,
        PortalStaff::ROLE_COURSE_CONTRIBUTOR,
    ];

    /** Действия change_log, считающиеся реальным наполнением. */
    private const CONTENT_ACTIONS = [
        'section.panel_saved',
        'section.created',
        'section.deleted',
        'section.reordered',
        'module.created',
        'module.updated',
        'module.deleted',
        'module.reordered',
        'course.created',
        'course.updated',
        'course.published',
    ];

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $staffLearnerIds = $this->staffLearnerIds();

        $usersTotal = Schema::hasTable('learners') ? (int) Learner::query()->count() : 0;
        $usersLearnersOnly = $usersTotal - count($staffLearnerIds);
        if ($usersLearnersOnly < 0) {
            $usersLearnersOnly = 0;
        }

        $authors = $this->authorStats();
        $content = $this->contentStats();
        $participants = $this->participantStats($staffLearnerIds);
        $progress = $this->progressStats($staffLearnerIds);
        $changelog = $this->changelogStats();
        $courses = $this->courseActivityBreakdown($staffLearnerIds);

        $assigned = (int) $participants['assigned_excl_staff'];
        $withProgress = (int) $progress['with_progress_excl_staff'];
        $engagementPct = $assigned > 0
            ? (int) round(100 * $withProgress / $assigned)
            : 0;

        return [
            'generated_at' => now()->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'users' => [
                'total' => $usersTotal,
                'learners_only' => $usersLearnersOnly,
                'staff' => count($staffLearnerIds),
            ],
            'authors' => $authors,
            'content' => $content,
            'participants' => $participants,
            'progress' => $progress,
            'engagement_pct' => $engagementPct,
            'changelog' => $changelog,
            'courses' => $courses,
            'funnel' => [
                ['key' => 'users', 'label' => 'Пользователи', 'value' => $usersTotal],
                ['key' => 'authors_active', 'label' => 'Активные авторы', 'value' => (int) $authors['active']],
                ['key' => 'assigned', 'label' => 'Назначены на лабы', 'value' => $assigned],
                ['key' => 'progress', 'label' => 'С прогрессом', 'value' => $withProgress],
                ['key' => 'completed', 'label' => 'Завершили курс', 'value' => (int) $progress['completed_excl_staff']],
            ],
        ];
    }

    /**
     * @return list<int>
     */
    private function staffLearnerIds(): array
    {
        if (! Schema::hasTable('portal_staff')) {
            return [];
        }

        return PortalStaff::query()
            ->pluck('learner_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function authorStats(): array
    {
        if (! Schema::hasTable('portal_staff')) {
            return [
                'granted' => 0,
                'active' => 0,
                'dormant' => 0,
                'active_pct' => 0,
                'by_role' => [],
                'top_active' => [],
            ];
        }

        $grantedQuery = PortalStaff::query()->whereIn('role', self::AUTHOR_ROLES);
        $granted = (int) (clone $grantedQuery)->count();

        $byRole = (clone $grantedQuery)
            ->selectRaw('role, COUNT(*) as c')
            ->groupBy('role')
            ->pluck('c', 'role')
            ->map(static fn ($v) => (int) $v)
            ->all();

        $activeStaffIds = $this->activeAuthorStaffIds();
        $grantedStaffIds = (clone $grantedQuery)->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $activeAmongGranted = array_values(array_intersect($grantedStaffIds, $activeStaffIds));
        $active = count($activeAmongGranted);
        $dormant = max(0, $granted - $active);
        $activePct = $granted > 0 ? (int) round(100 * $active / $granted) : 0;

        $topActive = [];
        if (Schema::hasTable('course_change_logs') && $activeAmongGranted !== []) {
            $edits = DB::table('course_change_logs')
                ->whereIn('portal_staff_id', $activeAmongGranted)
                ->whereIn('action', self::CONTENT_ACTIONS)
                ->selectRaw('portal_staff_id, COUNT(*) as edits')
                ->groupBy('portal_staff_id')
                ->orderByDesc('edits')
                ->limit(8)
                ->get();

            $staffMap = PortalStaff::query()
                ->with('learner:id,email')
                ->whereIn('id', $edits->pluck('portal_staff_id'))
                ->get()
                ->keyBy('id');

            foreach ($edits as $row) {
                $staff = $staffMap->get((int) $row->portal_staff_id);
                $topActive[] = [
                    'email' => (string) ($staff?->learner?->email ?? '—'),
                    'role' => (string) ($staff?->role ?? ''),
                    'edits' => (int) $row->edits,
                ];
            }
        }

        return [
            'granted' => $granted,
            'active' => $active,
            'dormant' => $dormant,
            'active_pct' => $activePct,
            'by_role' => $byRole,
            'top_active' => $topActive,
        ];
    }

    /**
     * @return list<int>
     */
    private function activeAuthorStaffIds(): array
    {
        $ids = [];

        if (Schema::hasTable('course_change_logs')) {
            foreach (
                DB::table('course_change_logs')
                    ->whereNotNull('portal_staff_id')
                    ->whereIn('action', self::CONTENT_ACTIONS)
                    ->distinct()
                    ->pluck('portal_staff_id') as $id
            ) {
                $ids[(int) $id] = true;
            }
        }

        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'created_by_portal_staff_id')) {
            foreach (
                DB::table('courses')
                    ->whereNotNull('created_by_portal_staff_id')
                    ->distinct()
                    ->pluck('created_by_portal_staff_id') as $id
            ) {
                $ids[(int) $id] = true;
            }
        }

        if (Schema::hasTable('practice_images') && Schema::hasColumn('practice_images', 'created_by_portal_staff_id')) {
            foreach (
                DB::table('practice_images')
                    ->whereNotNull('created_by_portal_staff_id')
                    ->distinct()
                    ->pluck('created_by_portal_staff_id') as $id
            ) {
                $ids[(int) $id] = true;
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * @return array<string, mixed>
     */
    private function contentStats(): array
    {
        $coursesTotal = Schema::hasTable('courses') ? (int) Course::query()->count() : 0;
        $coursesPublished = Schema::hasTable('courses') && Schema::hasColumn('courses', 'is_published')
            ? (int) Course::query()->where('is_published', true)->where('is_archived', false)->count()
            : 0;
        $coursesDraft = Schema::hasTable('courses')
            ? (int) Course::query()->where('is_archived', false)->when(
                Schema::hasColumn('courses', 'is_published'),
                static fn ($q) => $q->where('is_published', false)
            )->count()
            : 0;

        $quizzes = 0;
        $exams = 0;
        $practices = 0;
        $surveys = 0;
        if (Schema::hasTable('course_sections')) {
            $byType = CourseSection::query()
                ->selectRaw('type, COUNT(*) as c')
                ->groupBy('type')
                ->pluck('c', 'type');
            $quizzes = (int) ($byType[CourseSection::TYPE_QUIZ] ?? 0);
            $exams = (int) ($byType[CourseSection::TYPE_EXAM] ?? 0);
            $practices = (int) ($byType[CourseSection::TYPE_PRACTICE] ?? 0);
            $surveys = (int) ($byType[CourseSection::TYPE_SURVEY] ?? 0);
        }

        $quizBanks = Schema::hasTable('course_quiz_banks') ? (int) CourseQuizBank::query()->count() : 0;
        $practiceImages = Schema::hasTable('practice_images') ? (int) PracticeImage::query()->count() : 0;

        $testsTotal = $quizzes + $exams + $surveys;
        $labsTotal = $practices + $practiceImages;
        $assessmentsTotal = $testsTotal + $practices;

        return [
            'courses_total' => $coursesTotal,
            'courses_published' => $coursesPublished,
            'courses_draft' => $coursesDraft,
            'tests_total' => $testsTotal,
            'labs_total' => $labsTotal,
            'assessments_total' => $assessmentsTotal,
            'quiz_sections' => $quizzes,
            'exam_sections' => $exams,
            'survey_sections' => $surveys,
            'practice_sections' => $practices,
            'quiz_banks' => $quizBanks,
            'practice_images' => $practiceImages,
            'mix' => [
                ['key' => 'quiz', 'label' => 'Тесты', 'value' => $quizzes, 'color' => '#00b956'],
                ['key' => 'exam', 'label' => 'Экзамены', 'value' => $exams, 'color' => '#0d9488'],
                ['key' => 'practice', 'label' => 'Практики', 'value' => $practices, 'color' => '#0284c7'],
                ['key' => 'survey', 'label' => 'Опросы', 'value' => $surveys, 'color' => '#65a30d'],
                ['key' => 'images', 'label' => 'Docker-образы', 'value' => $practiceImages, 'color' => '#147a4a'],
            ],
        ];
    }

    /**
     * @param  list<int>  $staffLearnerIds
     * @return array<string, mixed>
     */
    private function participantStats(array $staffLearnerIds): array
    {
        $assigned = [];

        if (Schema::hasTable('course_enrollments')) {
            foreach (CourseEnrollment::query()->pluck('learner_id') as $id) {
                $assigned[(int) $id] = true;
            }
        }

        if (Schema::hasTable('content_view_audience_rules')) {
            $rules = ContentViewAudienceRule::query()->get(['subject_type', 'subject_id']);
            $portalGroupIds = [];
            $courseGroupIds = [];
            foreach ($rules as $rule) {
                if ($rule->subject_type === ContentViewAudienceRule::SUBJECT_LEARNER) {
                    $assigned[(int) $rule->subject_id] = true;
                } elseif ($rule->subject_type === ContentViewAudienceRule::SUBJECT_PORTAL_GROUP) {
                    $portalGroupIds[] = (int) $rule->subject_id;
                } elseif ($rule->subject_type === ContentViewAudienceRule::SUBJECT_COURSE_GROUP) {
                    $courseGroupIds[] = (int) $rule->subject_id;
                }
            }
            if ($portalGroupIds !== [] && Schema::hasTable('portal_learner_group_members')) {
                foreach (
                    DB::table('portal_learner_group_members')
                        ->whereIn('portal_learner_group_id', array_unique($portalGroupIds))
                        ->pluck('learner_id') as $id
                ) {
                    $assigned[(int) $id] = true;
                }
            }
            if ($courseGroupIds !== [] && Schema::hasTable('course_learner_group_members')) {
                foreach (
                    DB::table('course_learner_group_members')
                        ->whereIn('course_learner_group_id', array_unique($courseGroupIds))
                        ->pluck('learner_id') as $id
                ) {
                    $assigned[(int) $id] = true;
                }
            }
        }

        $allIds = array_map('intval', array_keys($assigned));
        $exclStaff = array_values(array_filter(
            $allIds,
            static fn (int $id) => ! in_array($id, $staffLearnerIds, true)
        ));

        return [
            'assigned_total' => count($allIds),
            'assigned_excl_staff' => count($exclStaff),
            'assigned_staff' => count($allIds) - count($exclStaff),
        ];
    }

    /**
     * @param  list<int>  $staffLearnerIds
     * @return array<string, mixed>
     */
    private function progressStats(array $staffLearnerIds): array
    {
        $withProgress = [];
        $deepProgress = [];

        if (Schema::hasTable('module_progress')) {
            $rows = ModuleProgress::query()
                ->select([
                    'learner_id',
                    'theory_read_at',
                    'theory_quiz_attempts',
                    'theory_quiz_passed',
                    'practice_done_at',
                    'module_exam_attempts',
                    'module_exam_passed',
                    'module_cleared_at',
                    'theory_quiz_best_score',
                    'module_exam_best_score',
                ])
                ->get();

            foreach ($rows as $row) {
                $lid = (int) $row->learner_id;
                $any = $row->theory_read_at !== null
                    || (int) $row->theory_quiz_attempts > 0
                    || (bool) $row->theory_quiz_passed
                    || $row->practice_done_at !== null
                    || (int) $row->module_exam_attempts > 0
                    || (bool) $row->module_exam_passed
                    || $row->module_cleared_at !== null
                    || (int) $row->theory_quiz_best_score > 0
                    || (int) $row->module_exam_best_score > 0;
                if ($any) {
                    $withProgress[$lid] = true;
                }
                $deep = (int) $row->theory_quiz_attempts > 0
                    || (bool) $row->theory_quiz_passed
                    || $row->practice_done_at !== null
                    || (int) $row->module_exam_attempts > 0
                    || (bool) $row->module_exam_passed
                    || $row->module_cleared_at !== null;
                if ($deep) {
                    $deepProgress[$lid] = true;
                }
            }
        }

        if (Schema::hasTable('course_survey_submissions')) {
            foreach (DB::table('course_survey_submissions')->distinct()->pluck('learner_id') as $id) {
                $withProgress[(int) $id] = true;
                $deepProgress[(int) $id] = true;
            }
        }

        $completed = [];
        if (Schema::hasTable('final_lab_results')) {
            $q = FinalLabResult::query()
                ->whereNotNull('certificate_full_name')
                ->whereNotNull('certificate_serial');
            foreach ($q->pluck('learner_id') as $id) {
                $completed[(int) $id] = true;
            }
        }

        $filter = static fn (array $set): array => array_values(array_filter(
            array_map('intval', array_keys($set)),
            static fn (int $id) => ! in_array($id, $staffLearnerIds, true)
        ));

        $progressExcl = $filter($withProgress);
        $deepExcl = $filter($deepProgress);
        $completedExcl = $filter($completed);

        return [
            'with_progress_total' => count($withProgress),
            'with_progress_excl_staff' => count($progressExcl),
            'deep_progress_excl_staff' => count($deepExcl),
            'completed_excl_staff' => count($completedExcl),
            'theory_only_excl_staff' => max(0, count($progressExcl) - count($deepExcl)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function changelogStats(): array
    {
        $entries = PortalChangelog::entries();
        $byTag = [
            'feature' => 0,
            'improvement' => 0,
            'fix' => 0,
            'docs' => 0,
        ];
        foreach ($entries as $entry) {
            $tag = (string) ($entry['tag'] ?? 'feature');
            if (! isset($byTag[$tag])) {
                $byTag[$tag] = 0;
            }
            $byTag[$tag]++;
        }

        $timeline = [];
        foreach (array_reverse($entries) as $entry) {
            $month = substr((string) $entry['date'], 0, 7);
            if (! isset($timeline[$month])) {
                $timeline[$month] = 0;
            }
            $timeline[$month]++;
        }

        $timelineBars = [];
        foreach ($timeline as $month => $count) {
            $timelineBars[] = [
                'month' => $month,
                'label' => $this->formatMonthLabel($month),
                'value' => $count,
            ];
        }

        return [
            'total' => count($entries),
            'by_tag' => $byTag,
            'timeline' => $timelineBars,
            'entries' => $entries,
        ];
    }

    private function formatMonthLabel(string $ym): string
    {
        try {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m', $ym)
                ->locale('ru')
                ->isoFormat('MMM YY');
        } catch (\Throwable) {
            return $ym;
        }
    }

    /**
     * @param  list<int>  $staffLearnerIds
     * @return list<array<string, mixed>>
     */
    private function courseActivityBreakdown(array $staffLearnerIds): array
    {
        if (! Schema::hasTable('courses')) {
            return [];
        }

        $out = [];
        $courses = Course::query()
            ->orderByDesc('is_published')
            ->orderBy('is_archived')
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'title', 'is_published', 'is_archived']);

        foreach ($courses as $course) {
            $cid = (int) $course->id;
            $enrolled = Schema::hasTable('course_enrollments')
                ? CourseEnrollment::query()->where('course_id', $cid)->pluck('learner_id')->map(static fn ($id) => (int) $id)->all()
                : [];
            $enrolledExcl = array_values(array_filter(
                $enrolled,
                static fn (int $id) => ! in_array($id, $staffLearnerIds, true)
            ));

            $progressIds = [];
            if (Schema::hasTable('module_progress')) {
                $q = ModuleProgress::query()->where('course_id', $cid)->where(function ($w) {
                    $w->whereNotNull('theory_read_at')
                        ->orWhere('theory_quiz_attempts', '>', 0)
                        ->orWhere('theory_quiz_passed', true)
                        ->orWhereNotNull('practice_done_at')
                        ->orWhere('module_exam_attempts', '>', 0)
                        ->orWhere('module_exam_passed', true)
                        ->orWhereNotNull('module_cleared_at');
                });
                foreach ($q->pluck('learner_id') as $id) {
                    $progressIds[(int) $id] = true;
                }
            }
            if (Schema::hasTable('course_survey_submissions')) {
                foreach (
                    DB::table('course_survey_submissions')->where('course_id', $cid)->pluck('learner_id') as $id
                ) {
                    $progressIds[(int) $id] = true;
                }
            }
            $progressExcl = array_values(array_filter(
                array_map('intval', array_keys($progressIds)),
                static fn (int $id) => ! in_array($id, $staffLearnerIds, true)
            ));

            $assigned = count($enrolledExcl);
            $withProgress = count($progressExcl);
            if ($assigned === 0 && $withProgress === 0 && $course->is_archived) {
                continue;
            }

            $out[] = [
                'id' => $cid,
                'title' => (string) $course->title,
                'published' => (bool) $course->is_published,
                'archived' => (bool) $course->is_archived,
                'assigned' => $assigned,
                'with_progress' => $withProgress,
                'rate_pct' => $assigned > 0 ? (int) round(100 * $withProgress / $assigned) : ($withProgress > 0 ? 100 : 0),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return ($b['with_progress'] * 1000 + $b['assigned']) <=> ($a['with_progress'] * 1000 + $a['assigned']);
        });

        return array_slice($out, 0, 10);
    }
}
