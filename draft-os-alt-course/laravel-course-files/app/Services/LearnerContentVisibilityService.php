<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentViewAudienceRule;
use App\Models\Course;
use App\Models\CourseLearnerGroup;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\Learner;
use App\Models\PortalLearnerGroup;
use App\Support\CourseStaffPreview;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Видимость курса/модуля/раздела для обучающихся (view_audience + правила).
 */
final class LearnerContentVisibilityService
{
    /** @var array<string, bool> */
    private array $visibilityCache = [];

    /** @var array<int, list<int>> */
    private array $portalGroupMembersCache = [];

    /** @var array<int, list<int>> */
    private array $courseGroupMembersCache = [];

    /** @var array<string, Collection<int, ContentViewAudienceRule>> */
    private array $rulesCache = [];

    public function bypassForStaffPreview(): bool
    {
        return CourseStaffPreview::isActive();
    }

    public function isCourseVisibleToLearner(int $courseId, int $learnerId): bool
    {
        if ($this->bypassForStaffPreview()) {
            return true;
        }

        return $this->checkResource(
            ContentViewAudienceRule::RESOURCE_COURSE,
            $courseId,
            $courseId,
            $learnerId
        );
    }

    public function isModuleVisibleToLearner(int $courseModuleId, int $learnerId, ?int $courseId = null): bool
    {
        if ($this->bypassForStaffPreview()) {
            return true;
        }

        $courseId = $courseId ?? $this->resolveCourseIdForModule($courseModuleId);
        if ($courseId < 1) {
            return false;
        }
        if (! $this->isCourseVisibleToLearner($courseId, $learnerId)) {
            return false;
        }

        return $this->checkResource(
            ContentViewAudienceRule::RESOURCE_MODULE,
            $courseModuleId,
            $courseId,
            $learnerId
        );
    }

    public function isSectionVisibleToLearner(int $sectionId, int $learnerId, ?int $courseModuleId = null): bool
    {
        if ($this->bypassForStaffPreview()) {
            return true;
        }

        if (! Schema::hasTable('course_sections')) {
            return true;
        }

        $section = CourseSection::query()->find($sectionId);
        if ($section === null || ! $section->is_enabled) {
            return false;
        }

        $courseModuleId = $courseModuleId ?? (int) $section->course_module_id;
        $courseId = (int) $section->course_id;

        if (! $this->isModuleVisibleToLearner($courseModuleId, $learnerId, $courseId)) {
            return false;
        }

        return $this->checkResource(
            ContentViewAudienceRule::RESOURCE_SECTION,
            $sectionId,
            $courseId,
            $learnerId
        );
    }

    /**
     * @return Collection<int, CourseModule>
     */
    public function visibleModulesForLearner(int $courseId, int $learnerId, ?Collection $modules = null): Collection
    {
        $modules ??= app(CourseModuleService::class)->orderedModulesForCourse($courseId);

        return $modules->filter(
            fn (CourseModule $m) => $this->isModuleVisibleToLearner((int) $m->id, $learnerId, $courseId)
        )->values();
    }

    /**
     * @return list<int>
     */
    public function visibleModuleIdsForLearner(int $courseId, int $learnerId): array
    {
        return $this->visibleModulesForLearner($courseId, $learnerId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CourseSection>
     */
    public function visibleSectionsForLearner(int $courseModuleId, int $learnerId, ?Collection $sections = null): Collection
    {
        $sections ??= app(CourseSectionService::class)->enabledSectionsForCourseModule($courseModuleId);

        return $sections->filter(
            fn (CourseSection $s) => $this->isSectionVisibleToLearner((int) $s->id, $learnerId, $courseModuleId)
        )->values();
    }

    /**
     * @return array{
     *     view_audience: string,
     *     rules: list<array{subject_type: string, subject_id: int, label: string, color: ?string, scope: ?string}>
     * }
     */
    public function audiencePayloadForResource(string $resourceType, int $resourceId, int $courseId): array
    {
        $viewAudience = $this->viewAudienceForResource($resourceType, $resourceId);
        $rules = $this->rulesForResource($courseId, $resourceType, $resourceId);

        return [
            'view_audience' => $viewAudience,
            'rules' => $rules->map(fn (ContentViewAudienceRule $r) => $this->ruleToPayload($r))->values()->all(),
            'groups' => $this->groupsCatalogForCourse($courseId),
        ];
    }

    /**
     * @param list<array{subject_type: string, subject_id: int}> $rules
     */
    public function syncAudienceForResource(
        string $resourceType,
        int $resourceId,
        int $courseId,
        string $viewAudience,
        array $rules
    ): void {
        $viewAudience = $viewAudience === Course::VIEW_AUDIENCE_RESTRICTED
            ? Course::VIEW_AUDIENCE_RESTRICTED
            : Course::VIEW_AUDIENCE_ALL;

        $this->setViewAudienceOnResource($resourceType, $resourceId, $viewAudience);

        if (! Schema::hasTable('content_view_audience_rules')) {
            return;
        }

        ContentViewAudienceRule::query()
            ->where('course_id', $courseId)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->delete();

        if ($viewAudience !== Course::VIEW_AUDIENCE_RESTRICTED) {
            $this->clearResourceCache($courseId, $resourceType, $resourceId);
            app(CourseChangeLogService::class)->logContentVisibilityChanged(
                $courseId,
                $resourceType,
                $resourceId,
                $viewAudience,
                0,
            );

            return;
        }

        foreach ($rules as $rule) {
            $subjectType = (string) ($rule['subject_type'] ?? '');
            $subjectId = (int) ($rule['subject_id'] ?? 0);
            if ($subjectId < 1 || ! in_array($subjectType, ContentViewAudienceRule::SUBJECT_TYPES, true)) {
                continue;
            }
            ContentViewAudienceRule::query()->create([
                'course_id' => $courseId,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ]);
        }

        $this->clearResourceCache($courseId, $resourceType, $resourceId);

        if (class_exists(CourseChangeLogService::class)) {
            app(CourseChangeLogService::class)->logContentVisibilityChanged(
                $courseId,
                $resourceType,
                $resourceId,
                $viewAudience,
                count($rules),
            );
        }
    }

    /**
     * @return array{portal: list<array<string, mixed>>, course: list<array<string, mixed>>}
     */
    public function groupsCatalogForCourse(int $courseId): array
    {
        if (! Schema::hasTable('portal_learner_groups')) {
            return ['portal' => [], 'course' => []];
        }

        $portal = PortalLearnerGroup::query()
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(fn (PortalLearnerGroup $g) => [
                'subject_type' => ContentViewAudienceRule::SUBJECT_PORTAL_GROUP,
                'subject_id' => (int) $g->id,
                'label' => (string) $g->name,
                'color' => (string) $g->color,
                'scope' => 'global',
                'member_count' => $g->members()->count(),
            ])
            ->values()
            ->all();

        $courseGroups = Schema::hasTable('course_learner_groups')
            ? CourseLearnerGroup::query()
                ->where('course_id', $courseId)
                ->orderBy('sort')
                ->orderBy('name')
                ->get()
                ->map(fn (CourseLearnerGroup $g) => [
                    'subject_type' => ContentViewAudienceRule::SUBJECT_COURSE_GROUP,
                    'subject_id' => (int) $g->id,
                    'label' => (string) $g->name,
                    'color' => (string) $g->color,
                    'scope' => 'course',
                    'member_count' => $g->members()->count(),
                ])
                ->values()
                ->all()
            : [];

        return ['portal' => $portal, 'course' => $courseGroups];
    }

    public function audienceSummaryForResource(string $resourceType, int $resourceId, int $courseId): ?string
    {
        if ($this->viewAudienceForResource($resourceType, $resourceId) !== Course::VIEW_AUDIENCE_RESTRICTED) {
            return null;
        }

        $rules = $this->rulesForResource($courseId, $resourceType, $resourceId);
        if ($rules->isEmpty()) {
            return 'Ограничено';
        }

        $learners = 0;
        $groups = 0;
        foreach ($rules as $rule) {
            if ($rule->subject_type === ContentViewAudienceRule::SUBJECT_LEARNER) {
                $learners++;
            } else {
                $groups++;
            }
        }

        $parts = [];
        if ($groups > 0) {
            $parts[] = $groups.' '.self::pluralGroups($groups);
        }
        if ($learners > 0) {
            $parts[] = $learners.' чел.';
        }

        return $parts !== [] ? implode(', ', $parts) : 'Ограничено';
    }

    /**
     * Режим аудитории раздела (без учёта курса/модуля).
     */
    public function sectionAudienceMode(CourseSection $section): string
    {
        return $this->viewAudienceForResource(
            ContentViewAudienceRule::RESOURCE_SECTION,
            (int) $section->id
        );
    }

    /**
     * Learner id, которым раздел реально виден при ограниченном доступе.
     * При view_audience=all возвращает [] — для отчётов используйте список прошедших.
     *
     * @return list<int>
     */
    public function eligibleLearnerIdsForSection(CourseSection $section): array
    {
        $sectionId = (int) $section->id;
        $courseId = (int) $section->course_id;
        $moduleId = (int) $section->course_module_id;

        if ($sectionId < 1 || $courseId < 1 || $moduleId < 1) {
            return [];
        }

        if ($this->sectionAudienceMode($section) !== Course::VIEW_AUDIENCE_RESTRICTED) {
            return [];
        }

        $candidateIds = $this->expandRuleLearnerIds(
            $courseId,
            ContentViewAudienceRule::RESOURCE_SECTION,
            $sectionId
        );

        if ($candidateIds === []) {
            return [];
        }

        // Без staff-preview bypass и без is_enabled: для отчёта важны правила аудитории.
        $out = [];
        foreach ($candidateIds as $learnerId) {
            if (! $this->checkResource(ContentViewAudienceRule::RESOURCE_COURSE, $courseId, $courseId, $learnerId)) {
                continue;
            }
            if (! $this->checkResource(ContentViewAudienceRule::RESOURCE_MODULE, $moduleId, $courseId, $learnerId)) {
                continue;
            }
            if (! $this->checkResource(ContentViewAudienceRule::RESOURCE_SECTION, $sectionId, $courseId, $learnerId)) {
                continue;
            }
            $out[] = $learnerId;
        }

        sort($out);

        return $out;
    }

    /**
     * Разворот правил аудитории в уникальные learner_id (без фильтра видимости родителя).
     *
     * @return list<int>
     */
    public function expandRuleLearnerIds(int $courseId, string $resourceType, int $resourceId): array
    {
        $rules = $this->rulesForResource($courseId, $resourceType, $resourceId);
        if ($rules->isEmpty()) {
            return [];
        }

        $ids = [];
        foreach ($rules as $rule) {
            foreach ($this->learnerIdsFromRule($rule) as $learnerId) {
                if ($learnerId > 0) {
                    $ids[$learnerId] = true;
                }
            }
        }

        $out = array_map('intval', array_keys($ids));
        sort($out);

        return $out;
    }

    /** @return list<int> */
    private function learnerIdsFromRule(ContentViewAudienceRule $rule): array
    {
        return match ($rule->subject_type) {
            ContentViewAudienceRule::SUBJECT_LEARNER => [(int) $rule->subject_id],
            ContentViewAudienceRule::SUBJECT_PORTAL_GROUP => $this->portalGroupMemberIds((int) $rule->subject_id),
            ContentViewAudienceRule::SUBJECT_COURSE_GROUP => $this->courseGroupMemberIds((int) $rule->subject_id),
            default => [],
        };
    }

    private static function pluralGroups(int $n): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        if ($mod10 === 1 && $mod100 !== 11) {
            return 'группа';
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            return 'группы';
        }

        return 'групп';
    }

    private function checkResource(string $resourceType, int $resourceId, int $courseId, int $learnerId): bool
    {
        $cacheKey = $resourceType.':'.$resourceId.':'.$learnerId;
        if (isset($this->visibilityCache[$cacheKey])) {
            return $this->visibilityCache[$cacheKey];
        }

        $audience = $this->viewAudienceForResource($resourceType, $resourceId);
        if ($audience !== Course::VIEW_AUDIENCE_RESTRICTED) {
            return $this->visibilityCache[$cacheKey] = true;
        }

        $rules = $this->rulesForResource($courseId, $resourceType, $resourceId);
        if ($rules->isEmpty()) {
            return $this->visibilityCache[$cacheKey] = false;
        }

        foreach ($rules as $rule) {
            if ($this->learnerMatchesRule($learnerId, $rule)) {
                return $this->visibilityCache[$cacheKey] = true;
            }
        }

        return $this->visibilityCache[$cacheKey] = false;
    }

    private function learnerMatchesRule(int $learnerId, ContentViewAudienceRule $rule): bool
    {
        return match ($rule->subject_type) {
            ContentViewAudienceRule::SUBJECT_LEARNER => (int) $rule->subject_id === $learnerId,
            ContentViewAudienceRule::SUBJECT_PORTAL_GROUP => in_array(
                $learnerId,
                $this->portalGroupMemberIds((int) $rule->subject_id),
                true
            ),
            ContentViewAudienceRule::SUBJECT_COURSE_GROUP => in_array(
                $learnerId,
                $this->courseGroupMemberIds((int) $rule->subject_id),
                true
            ),
            default => false,
        };
    }

    /** @return list<int> */
    private function portalGroupMemberIds(int $groupId): array
    {
        if (isset($this->portalGroupMembersCache[$groupId])) {
            return $this->portalGroupMembersCache[$groupId];
        }
        if (! Schema::hasTable('portal_learner_group_members')) {
            return $this->portalGroupMembersCache[$groupId] = [];
        }

        return $this->portalGroupMembersCache[$groupId] = DB::table('portal_learner_group_members')
            ->where('portal_learner_group_id', $groupId)
            ->pluck('learner_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function courseGroupMemberIds(int $groupId): array
    {
        if (isset($this->courseGroupMembersCache[$groupId])) {
            return $this->courseGroupMembersCache[$groupId];
        }
        if (! Schema::hasTable('course_learner_group_members')) {
            return $this->courseGroupMembersCache[$groupId] = [];
        }

        return $this->courseGroupMembersCache[$groupId] = DB::table('course_learner_group_members')
            ->where('course_learner_group_id', $groupId)
            ->pluck('learner_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function viewAudienceForResource(string $resourceType, int $resourceId): string
    {
        if (! Schema::hasColumn($this->tableForResourceType($resourceType), 'view_audience')) {
            return Course::VIEW_AUDIENCE_ALL;
        }

        $value = match ($resourceType) {
            ContentViewAudienceRule::RESOURCE_COURSE => Course::query()->whereKey($resourceId)->value('view_audience'),
            ContentViewAudienceRule::RESOURCE_MODULE => CourseModule::query()->whereKey($resourceId)->value('view_audience'),
            ContentViewAudienceRule::RESOURCE_SECTION => CourseSection::query()->whereKey($resourceId)->value('view_audience'),
            default => Course::VIEW_AUDIENCE_ALL,
        };

        return $value === Course::VIEW_AUDIENCE_RESTRICTED
            ? Course::VIEW_AUDIENCE_RESTRICTED
            : Course::VIEW_AUDIENCE_ALL;
    }

    private function setViewAudienceOnResource(string $resourceType, int $resourceId, string $viewAudience): void
    {
        $table = $this->tableForResourceType($resourceType);
        if (! Schema::hasColumn($table, 'view_audience')) {
            return;
        }

        match ($resourceType) {
            ContentViewAudienceRule::RESOURCE_COURSE => Course::query()->whereKey($resourceId)->update(['view_audience' => $viewAudience]),
            ContentViewAudienceRule::RESOURCE_MODULE => CourseModule::query()->whereKey($resourceId)->update(['view_audience' => $viewAudience]),
            ContentViewAudienceRule::RESOURCE_SECTION => CourseSection::query()->whereKey($resourceId)->update(['view_audience' => $viewAudience]),
            default => null,
        };
    }

    private function tableForResourceType(string $resourceType): string
    {
        return match ($resourceType) {
            ContentViewAudienceRule::RESOURCE_COURSE => 'courses',
            ContentViewAudienceRule::RESOURCE_MODULE => 'course_modules',
            ContentViewAudienceRule::RESOURCE_SECTION => 'course_sections',
            default => 'courses',
        };
    }

    /** @return Collection<int, ContentViewAudienceRule> */
    private function rulesForResource(int $courseId, string $resourceType, int $resourceId): Collection
    {
        $key = $courseId.':'.$resourceType.':'.$resourceId;
        if (isset($this->rulesCache[$key])) {
            return $this->rulesCache[$key];
        }
        if (! Schema::hasTable('content_view_audience_rules')) {
            return $this->rulesCache[$key] = collect();
        }

        return $this->rulesCache[$key] = ContentViewAudienceRule::query()
            ->where('course_id', $courseId)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->orderBy('id')
            ->get();
    }

    private function clearResourceCache(int $courseId, string $resourceType, int $resourceId): void
    {
        unset($this->rulesCache[$courseId.':'.$resourceType.':'.$resourceId]);
        $prefix = $resourceType.':'.$resourceId.':';
        foreach (array_keys($this->visibilityCache) as $k) {
            if (str_starts_with($k, $prefix)) {
                unset($this->visibilityCache[$k]);
            }
        }
    }

    private function resolveCourseIdForModule(int $courseModuleId): int
    {
        return (int) (CourseModule::query()->whereKey($courseModuleId)->value('course_id') ?? 0);
    }

    /** @return array{subject_type: string, subject_id: int, label: string, color: ?string, scope: ?string} */
    private function ruleToPayload(ContentViewAudienceRule $rule): array
    {
        return match ($rule->subject_type) {
            ContentViewAudienceRule::SUBJECT_LEARNER => $this->learnerRulePayload($rule),
            ContentViewAudienceRule::SUBJECT_PORTAL_GROUP => $this->portalGroupRulePayload($rule),
            ContentViewAudienceRule::SUBJECT_COURSE_GROUP => $this->courseGroupRulePayload($rule),
            default => [
                'subject_type' => $rule->subject_type,
                'subject_id' => (int) $rule->subject_id,
                'label' => '#'.(int) $rule->subject_id,
                'color' => null,
                'scope' => null,
            ],
        };
    }

    /** @return array{subject_type: string, subject_id: int, label: string, color: ?string, scope: ?string} */
    private function learnerRulePayload(ContentViewAudienceRule $rule): array
    {
        $learner = Learner::query()->find((int) $rule->subject_id);
        $label = $learner !== null
            ? (trim((string) ($learner->sso_display_name ?? '')) ?: (string) $learner->email)
            : 'Обучающийся #'.(int) $rule->subject_id;

        return [
            'subject_type' => ContentViewAudienceRule::SUBJECT_LEARNER,
            'subject_id' => (int) $rule->subject_id,
            'label' => $label,
            'color' => null,
            'scope' => null,
        ];
    }

    /** @return array{subject_type: string, subject_id: int, label: string, color: ?string, scope: ?string} */
    private function portalGroupRulePayload(ContentViewAudienceRule $rule): array
    {
        $group = PortalLearnerGroup::query()->find((int) $rule->subject_id);

        return [
            'subject_type' => ContentViewAudienceRule::SUBJECT_PORTAL_GROUP,
            'subject_id' => (int) $rule->subject_id,
            'label' => $group !== null ? (string) $group->name : 'Группа #'.(int) $rule->subject_id,
            'color' => $group?->color,
            'scope' => 'global',
        ];
    }

    /** @return array{subject_type: string, subject_id: int, label: string, color: ?string, scope: ?string} */
    private function courseGroupRulePayload(ContentViewAudienceRule $rule): array
    {
        $group = CourseLearnerGroup::query()->find((int) $rule->subject_id);

        return [
            'subject_type' => ContentViewAudienceRule::SUBJECT_COURSE_GROUP,
            'subject_id' => (int) $rule->subject_id,
            'label' => $group !== null ? (string) $group->name : 'Группа #'.(int) $rule->subject_id,
            'color' => $group?->color,
            'scope' => 'course',
        ];
    }
}
