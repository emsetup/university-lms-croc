<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ContentViewAudienceRule;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\Learner;
use App\Services\LearnerContentVisibilityService;
use App\Services\PortalStaffAccess;
use App\Support\LearnerDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AdminContentVisibilityController extends Controller
{
    public function __construct(private LearnerContentVisibilityService $visibility) {}

    public function showCourse(Course $adminCourse): JsonResponse
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $course->id);

        return $this->jsonPayload(
            ContentViewAudienceRule::RESOURCE_COURSE,
            (int) $course->id,
            (int) $course->id,
            $this->groupsCatalog((int) $course->id),
        );
    }

    public function updateCourse(Request $request, Course $adminCourse): JsonResponse
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanEditCourseMeta((int) $course->id);

        return $this->savePayload(
            $request,
            ContentViewAudienceRule::RESOURCE_COURSE,
            (int) $course->id,
            (int) $course->id,
        );
    }

    public function showModule(Course $adminCourse, CourseModule $courseModule): JsonResponse
    {
        $this->assertModuleInCourse($adminCourse, $courseModule);
        app(PortalStaffAccess::class)->assertCanEditModuleContent((int) $courseModule->id);

        return $this->jsonPayload(
            ContentViewAudienceRule::RESOURCE_MODULE,
            (int) $courseModule->id,
            (int) $adminCourse->id,
            $this->groupsCatalog((int) $adminCourse->id),
        );
    }

    public function updateModule(Request $request, Course $adminCourse, CourseModule $courseModule): JsonResponse
    {
        $this->assertModuleInCourse($adminCourse, $courseModule);
        app(PortalStaffAccess::class)->assertCanEditModuleContent((int) $courseModule->id);

        return $this->savePayload(
            $request,
            ContentViewAudienceRule::RESOURCE_MODULE,
            (int) $courseModule->id,
            (int) $adminCourse->id,
        );
    }

    public function showSection(Course $adminCourse, CourseModule $courseModule, CourseSection $section): JsonResponse
    {
        $this->assertSectionInModule($courseModule, $section);
        app(PortalStaffAccess::class)->assertCanEditSection((int) $section->id);

        return $this->jsonPayload(
            ContentViewAudienceRule::RESOURCE_SECTION,
            (int) $section->id,
            (int) $adminCourse->id,
            $this->groupsCatalog((int) $adminCourse->id),
        );
    }

    public function updateSection(Request $request, Course $adminCourse, CourseModule $courseModule, CourseSection $section): JsonResponse
    {
        $this->assertSectionInModule($courseModule, $section);
        app(PortalStaffAccess::class)->assertCanEditSection((int) $section->id);

        return $this->savePayload(
            $request,
            ContentViewAudienceRule::RESOURCE_SECTION,
            (int) $section->id,
            (int) $adminCourse->id,
        );
    }

    public function searchLearners(Request $request, Course $adminCourse): JsonResponse
    {
        $course = $adminCourse;
        $gate = app(PortalStaffAccess::class);
        $gate->assertCanAccessCourseInAdmin((int) $course->id);

        $q = trim((string) $request->query('q', ''));
        $enrolledIds = $course->enrollments()->pluck('learner_id')->map(fn ($id) => (int) $id)->all();
        $enrolledFlip = array_flip($enrolledIds);

        if ($q === '') {
            return response()->json([
                'items' => $this->learnerItemsForIds(
                    $enrolledIds !== []
                        ? Learner::query()->whereIn('id', $enrolledIds)->orderBy('email')->limit(50)->pluck('id')->map(fn ($id) => (int) $id)->all()
                        : [],
                    $enrolledFlip,
                ),
            ]);
        }

        $minLen = str_contains($q, '@') ? 1 : 2;
        if (mb_strlen($q) < $minLen) {
            return response()->json(['items' => []]);
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $qLower = mb_strtolower($q);
        $query = Learner::query()
            ->where(function ($w) use ($like, $qLower): void {
                $w->where('email', 'like', $like)
                    ->orWhere('sso_display_name', 'like', $like);
                if (str_contains($qLower, '@')) {
                    $w->orWhereRaw('LOWER(email) = ?', [$qLower]);
                }
            });

        if ($enrolledIds !== []) {
            $idList = implode(',', array_map('intval', $enrolledIds));
            $query->orderByRaw("CASE WHEN id IN ({$idList}) THEN 0 ELSE 1 END");
        }

        $rows = $query
            ->orderBy('email')
            ->limit(30)
            ->get(['id', 'email', 'sso_display_name']);

        return response()->json([
            'items' => $this->learnerItemsForIds(
                $rows->pluck('id')->map(fn ($id) => (int) $id)->all(),
                $enrolledFlip,
                $rows,
            ),
        ]);
    }

    public function resolveLearners(Request $request, Course $adminCourse): JsonResponse
    {
        $course = $adminCourse;
        app(PortalStaffAccess::class)->assertCanAccessCourseInAdmin((int) $course->id);

        $data = $request->validate([
            'emails' => ['required', 'array', 'min:1', 'max:200'],
            'emails.*' => ['required', 'string', 'max:255'],
        ]);

        $enrolledFlip = array_flip(
            $course->enrollments()->pluck('learner_id')->map(fn ($id) => (int) $id)->all()
        );

        $invalid = [];
        $created = [];
        $enrolled = [];
        $learners = [];

        foreach ($data['emails'] as $raw) {
            $email = mb_strtolower(trim((string) $raw));
            if ($email === '') {
                continue;
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = trim((string) $raw);

                continue;
            }

            $result = $this->findOrCreateLearnerByEmail($email);
            $learner = $result['learner'];
            if ($learner === null) {
                $invalid[] = trim((string) $raw);

                continue;
            }
            if ($result['created']) {
                $created[] = $email;
            }

            $enrollment = CourseEnrollment::query()->firstOrCreate(
                ['course_id' => (int) $course->id, 'learner_id' => (int) $learner->id],
            );
            if ($enrollment->wasRecentlyCreated) {
                $enrolled[] = $email;
                $enrolledFlip[(int) $learner->id] = (int) $learner->id;
            }

            $learners[(int) $learner->id] = $learner;
        }

        $learnerCollection = collect(array_values($learners));

        return response()->json([
            'items' => $this->learnerItemsForIds(
                $learnerCollection->pluck('id')->map(fn ($id) => (int) $id)->all(),
                $enrolledFlip,
                $learnerCollection,
            ),
            'created' => $created,
            'enrolled' => $enrolled,
            'invalid' => $invalid,
            'not_found' => [],
        ]);
    }

    /**
     * @return array{learner: ?Learner, created: bool}
     */
    private function findOrCreateLearnerByEmail(string $email): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['learner' => null, 'created' => false];
        }

        $existing = Learner::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing !== null) {
            return ['learner' => $existing, 'created' => false];
        }

        try {
            $learner = Learner::query()->create(['email' => $email]);

            return ['learner' => $learner, 'created' => true];
        } catch (\Throwable) {
            $learner = Learner::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            return ['learner' => $learner, 'created' => false];
        }
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, int>  $enrolledFlip
     * @param  \Illuminate\Support\Collection<int, Learner>|null  $prefetched
     * @return list<array<string, mixed>>
     */
    private function learnerItemsForIds(array $ids, array $enrolledFlip, $prefetched = null): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $prefetched ?? Learner::query()
            ->whereIn('id', $ids)
            ->orderBy('email')
            ->get(['id', 'email', 'sso_display_name']);

        $byId = $rows->keyBy('id');
        $items = [];
        foreach ($ids as $id) {
            $learner = $byId->get($id);
            if ($learner === null) {
                continue;
            }
            $name = LearnerDisplay::portalDisplayName($learner);
            $enrolled = isset($enrolledFlip[(int) $learner->id]);
            $items[] = [
                'subject_type' => ContentViewAudienceRule::SUBJECT_LEARNER,
                'subject_id' => (int) $learner->id,
                'label' => $name !== '' ? $name : (string) $learner->email,
                'email' => (string) $learner->email,
                'color' => null,
                'scope' => null,
                'enrolled' => $enrolled,
            ];
        }

        return $items;
    }

    /**
     * @return array{
     *     ok: true,
     *     view_audience: string,
     *     rules: list<array<string, mixed>>,
     *     groups: array{portal: list<array<string, mixed>>, course: list<array<string, mixed>>},
     *     summary: ?string
     * }
     */
    private function jsonPayload(string $resourceType, int $resourceId, int $courseId, array $groups): JsonResponse
    {
        $audience = $this->visibility->audiencePayloadForResource($resourceType, $resourceId, $courseId);

        return response()->json([
            'ok' => true,
            'view_audience' => $audience['view_audience'],
            'rules' => $audience['rules'],
            'groups' => $groups,
            'summary' => $this->visibility->audienceSummaryForResource($resourceType, $resourceId, $courseId),
        ]);
    }

    private function savePayload(Request $request, string $resourceType, int $resourceId, int $courseId): JsonResponse
    {
        $data = $request->validate([
            'view_audience' => ['required', 'string', Rule::in([Course::VIEW_AUDIENCE_ALL, Course::VIEW_AUDIENCE_RESTRICTED])],
            'rules' => ['nullable', 'array'],
            'rules.*.subject_type' => ['required', 'string', Rule::in(ContentViewAudienceRule::SUBJECT_TYPES)],
            'rules.*.subject_id' => ['required', 'integer', 'min:1'],
        ]);

        $viewAudience = (string) $data['view_audience'];
        $rules = array_values($data['rules'] ?? []);
        if ($viewAudience === Course::VIEW_AUDIENCE_RESTRICTED && $rules === []) {
            throw ValidationException::withMessages([
                'rules' => 'При ограниченном доступе выберите хотя бы одного обучающегося или группу.',
            ]);
        }

        $this->visibility->syncAudienceForResource($resourceType, $resourceId, $courseId, $viewAudience, $rules);

        return $this->jsonPayload($resourceType, $resourceId, $courseId, $this->groupsCatalog($courseId));
    }

    /**
     * @return array{portal: list<array<string, mixed>>, course: list<array<string, mixed>>}
     */
    private function groupsCatalog(int $courseId): array
    {
        return app(LearnerContentVisibilityService::class)->groupsCatalogForCourse($courseId);
    }

    private function assertModuleInCourse(Course $course, CourseModule $module): void
    {
        abort_unless((int) $module->course_id === (int) $course->id, 404);
    }

    private function assertSectionInModule(CourseModule $module, CourseSection $section): void
    {
        abort_unless((int) $section->course_module_id === (int) $module->id, 404);
    }
}
