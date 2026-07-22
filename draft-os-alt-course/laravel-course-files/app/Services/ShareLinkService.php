<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseModule;
use App\Models\CourseSection;
use App\Models\CourseShareLink;
use App\Models\CourseSurveyLink;
use App\Models\Learner;
use App\Support\LearnerPreviewContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ShareLinkService
{
    public function __construct(
        private SurveyQuickLinkService $surveyLinks,
    ) {}

    public function tableReady(): bool
    {
        return Schema::hasTable('course_share_links');
    }

    /**
     * @return array{
     *   active:bool,
     *   url:?string,
     *   expires_at:?string,
     *   generate_url:string,
     *   revoke_url:string,
     *   kind:string,
     *   title:string
     * }
     */
    public function metaForCourse(Course $course, string $generateUrl, string $revokeUrl): array
    {
        $link = $this->activeLink(CourseShareLink::TARGET_COURSE, (int) $course->id);

        return $this->metaPayload($link, $generateUrl, $revokeUrl, 'course', (string) $course->title);
    }

    /**
     * @return array{
     *   active:bool,
     *   url:?string,
     *   expires_at:?string,
     *   generate_url:string,
     *   revoke_url:string,
     *   kind:string,
     *   title:string
     * }
     */
    public function metaForModule(CourseModule $module, string $generateUrl, string $revokeUrl): array
    {
        $link = $this->activeLink(CourseShareLink::TARGET_MODULE, (int) $module->id);

        return $this->metaPayload($link, $generateUrl, $revokeUrl, 'module', (string) $module->title);
    }

    /**
     * @return array{
     *   active:bool,
     *   url:?string,
     *   expires_at:?string,
     *   generate_url:string,
     *   revoke_url:string,
     *   kind:string,
     *   title:string
     * }|null
     */
    public function metaForSection(CourseSection $section, string $generateUrl, string $revokeUrl, ?string $inviteUrl = null): ?array
    {
        if ($section->type === CourseSection::TYPE_SURVEY) {
            $survey = $this->surveyLinks->metaForSection($section, $generateUrl, $revokeUrl, $inviteUrl);
            if ($survey === null) {
                return null;
            }

            return array_merge($survey, [
                'kind' => 'survey',
                'title' => (string) $section->title,
            ]);
        }

        if (! $this->tableReady()) {
            return null;
        }

        $link = $this->activeLink(CourseShareLink::TARGET_SECTION, (int) $section->id);

        return $this->metaPayload($link, $generateUrl, $revokeUrl, 'section', (string) $section->title);
    }

    public function learnerUrl(CourseShareLink $link): string
    {
        return route('share.quick', ['token' => $link->token]);
    }

    public function generateForCourse(Course $course): CourseShareLink
    {
        return $this->generate(CourseShareLink::TARGET_COURSE, (int) $course->id, (int) $course->id);
    }

    public function generateForModule(CourseModule $module): CourseShareLink
    {
        return $this->generate(CourseShareLink::TARGET_MODULE, (int) $module->id, (int) $module->course_id);
    }

    public function generateForSection(CourseSection $section): CourseShareLink
    {
        if ($section->type === CourseSection::TYPE_SURVEY) {
            throw new \InvalidArgumentException('Для опросов используйте SurveyQuickLinkService.');
        }

        return $this->generate(CourseShareLink::TARGET_SECTION, (int) $section->id, (int) $section->course_id);
    }

    public function revokeForCourse(Course $course): void
    {
        $this->revoke(CourseShareLink::TARGET_COURSE, (int) $course->id);
    }

    public function revokeForModule(CourseModule $module): void
    {
        $this->revoke(CourseShareLink::TARGET_MODULE, (int) $module->id);
    }

    public function revokeForSection(CourseSection $section): void
    {
        if ($section->type === CourseSection::TYPE_SURVEY) {
            $this->surveyLinks->revoke($section);

            return;
        }

        $this->revoke(CourseShareLink::TARGET_SECTION, (int) $section->id);
    }

    public function resolve(string $token): ?CourseShareLink
    {
        if (! $this->tableReady() || $token === '') {
            return null;
        }

        $link = CourseShareLink::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if ($link === null || ! $link->isUsable()) {
            return null;
        }

        return $link;
    }

    public function ensureEnrollment(Learner $learner, Course $course): void
    {
        $enroll = CourseEnrollment::query()->firstOrCreate(
            ['course_id' => $course->id, 'learner_id' => $learner->id],
            []
        );
        if ($enroll->started_at === null) {
            $enroll->started_at = now();
        }
        $enroll->last_seen_at = now();
        $enroll->save();

        LearnerPreviewContext::selectCourse((int) $course->id, (string) $course->title);
    }

    /**
     * @return list<array{
     *   kind:string,
     *   title:string,
     *   active:bool,
     *   url:?string,
     *   target_type:string,
     *   target_id:int,
     *   module_id:?int,
     *   module_title:?string,
     *   generate_url:string,
     *   revoke_url:string,
     *   edit_anchor:?string
     * }>
     */
    public function listForCourse(Course $course, string $adminCourseSlug): array
    {
        $courseId = (int) $course->id;
        $rp = ['adminCourse' => $adminCourseSlug];
        $items = [];

        $courseMeta = $this->metaForCourse(
            $course,
            route('admin.course.share-link.generate', $rp),
            route('admin.course.share-link.revoke', $rp),
        );
        $items[] = [
            'kind' => 'course',
            'title' => (string) $course->title,
            'active' => $courseMeta['active'],
            'url' => $courseMeta['url'],
            'target_type' => CourseShareLink::TARGET_COURSE,
            'target_id' => $courseId,
            'module_id' => null,
            'module_title' => null,
            'generate_url' => $courseMeta['generate_url'],
            'revoke_url' => $courseMeta['revoke_url'],
            'edit_anchor' => null,
        ];

        $modules = CourseModule::query()
            ->where('course_id', $courseId)
            ->with(['sections' => static function ($q): void {
                $q->orderBy('sort')->orderBy('id');
            }])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        foreach ($modules as $module) {
            $modRp = array_merge($rp, ['courseModule' => $module->id]);
            $modMeta = $this->metaForModule(
                $module,
                route('admin.course.module.share-link.generate', $modRp),
                route('admin.course.module.share-link.revoke', $modRp),
            );
            $items[] = [
                'kind' => 'module',
                'title' => (string) $module->title,
                'active' => $modMeta['active'],
                'url' => $modMeta['url'],
                'target_type' => CourseShareLink::TARGET_MODULE,
                'target_id' => (int) $module->id,
                'module_id' => (int) $module->id,
                'module_title' => (string) $module->title,
                'generate_url' => $modMeta['generate_url'],
                'revoke_url' => $modMeta['revoke_url'],
                'edit_anchor' => '#ap-mod-'.$module->id,
            ];

            foreach ($module->sections as $section) {
                $secRp = array_merge($rp, ['courseModule' => $module->id, 'section' => $section->id]);
                if ($section->type === CourseSection::TYPE_SURVEY) {
                    $secMeta = $this->metaForSection(
                        $section,
                        route('admin.course.section.quick-link.generate', $secRp),
                        route('admin.course.section.quick-link.revoke', $secRp),
                    );
                } else {
                    $secMeta = $this->metaForSection(
                        $section,
                        route('admin.course.section.share-link.generate', $secRp),
                        route('admin.course.section.share-link.revoke', $secRp),
                    );
                }
                if ($secMeta === null) {
                    continue;
                }
                $items[] = [
                    'kind' => $secMeta['kind'],
                    'title' => (string) $section->title,
                    'active' => $secMeta['active'],
                    'url' => $secMeta['url'],
                    'target_type' => $section->type === CourseSection::TYPE_SURVEY ? 'survey' : CourseShareLink::TARGET_SECTION,
                    'target_id' => (int) $section->id,
                    'module_id' => (int) $module->id,
                    'module_title' => (string) $module->title,
                    'generate_url' => $secMeta['generate_url'],
                    'revoke_url' => $secMeta['revoke_url'],
                    'edit_anchor' => '#ap-mod-'.$module->id,
                ];
            }
        }

        return $items;
    }

    /**
     * Активные ссылки курса (для бейджей / счётчика).
     */
    public function activeCountForCourse(int $courseId): int
    {
        $n = 0;
        if ($this->tableReady()) {
            $n += (int) CourseShareLink::query()
                ->where('course_id', $courseId)
                ->where('is_active', true)
                ->count();
        }
        if ($this->surveyLinks->tableReady() && Schema::hasTable('course_sections')) {
            $sectionIds = CourseSection::query()
                ->where('course_id', $courseId)
                ->where('type', CourseSection::TYPE_SURVEY)
                ->pluck('id');
            if ($sectionIds->isNotEmpty()) {
                $n += (int) CourseSurveyLink::query()
                    ->whereIn('course_section_id', $sectionIds)
                    ->where('is_active', true)
                    ->count();
            }
        }

        return $n;
    }

    private function generate(string $targetType, int $targetId, int $courseId): CourseShareLink
    {
        if (! $this->tableReady()) {
            throw new \RuntimeException('Таблица быстрых ссылок не создана. Выполните миграции.');
        }

        CourseShareLink::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return CourseShareLink::query()->create([
            'course_id' => $courseId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'token' => $this->newToken(),
            'is_active' => true,
            'expires_at' => null,
        ]);
    }

    private function revoke(string $targetType, int $targetId): void
    {
        if (! $this->tableReady()) {
            return;
        }

        CourseShareLink::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    private function activeLink(string $targetType, int $targetId): ?CourseShareLink
    {
        if (! $this->tableReady() || $targetId < 1) {
            return null;
        }

        $link = CourseShareLink::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        return $link !== null && $link->isUsable() ? $link : null;
    }

    /**
     * @return array{
     *   active:bool,
     *   url:?string,
     *   expires_at:?string,
     *   generate_url:string,
     *   revoke_url:string,
     *   kind:string,
     *   title:string
     * }
     */
    private function metaPayload(?CourseShareLink $link, string $generateUrl, string $revokeUrl, string $kind, string $title): array
    {
        return [
            'active' => $link !== null,
            'url' => $link ? $this->learnerUrl($link) : null,
            'expires_at' => $link?->expires_at?->toIso8601String(),
            'generate_url' => $generateUrl,
            'revoke_url' => $revokeUrl,
            'kind' => $kind,
            'title' => $title,
        ];
    }

    private function newToken(): string
    {
        do {
            $token = Str::lower(Str::random(32));
        } while (
            CourseShareLink::query()->where('token', $token)->exists()
            || ($this->surveyLinks->tableReady() && CourseSurveyLink::query()->where('token', $token)->exists())
        );

        return $token;
    }
}
