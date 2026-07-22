<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSection;
use App\Models\CourseSurveyLink;
use App\Models\Learner;
use App\Support\LearnerPreviewContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class SurveyQuickLinkService
{
    public function tableReady(): bool
    {
        return Schema::hasTable('course_survey_links');
    }

    /**
     * @return array{
     *   active:bool,
     *   url:?string,
     *   expires_at:?string,
     *   generate_url:string,
     *   revoke_url:string,
     *   invite_url:?string,
     *   kind:string
     * }|null
     */
    public function metaForSection(CourseSection $section, string $generateUrl, string $revokeUrl, ?string $inviteUrl = null): ?array
    {
        if ($section->type !== CourseSection::TYPE_SURVEY || ! $this->tableReady()) {
            return null;
        }

        $link = $this->activeLinkForSection((int) $section->id);

        return [
            'active' => $link !== null,
            'url' => $link ? $this->learnerUrl($link) : null,
            'expires_at' => $link?->expires_at?->toIso8601String(),
            'generate_url' => $generateUrl,
            'revoke_url' => $revokeUrl,
            'invite_url' => $inviteUrl,
            'kind' => 'survey',
        ];
    }

    public function learnerUrl(CourseSurveyLink $link): string
    {
        return route('survey.quick', ['token' => $link->token]);
    }

    public function activeUrlForSection(int $sectionId): ?string
    {
        $link = $this->activeLinkForSection($sectionId);

        return $link ? $this->learnerUrl($link) : null;
    }

    public function generate(CourseSection $section): CourseSurveyLink
    {
        if (! $this->tableReady()) {
            throw new \RuntimeException('Таблица быстрых ссылок не создана. Выполните миграции.');
        }

        CourseSurveyLink::query()
            ->where('course_section_id', (int) $section->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return CourseSurveyLink::query()->create([
            'course_section_id' => (int) $section->id,
            'token' => $this->newToken(),
            'is_active' => true,
            'expires_at' => null,
        ]);
    }

    public function revoke(CourseSection $section): void
    {
        if (! $this->tableReady()) {
            return;
        }

        CourseSurveyLink::query()
            ->where('course_section_id', (int) $section->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    public function resolve(string $token): ?CourseSurveyLink
    {
        if (! $this->tableReady() || $token === '') {
            return null;
        }

        $link = CourseSurveyLink::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if ($link === null || ! $link->isUsable()) {
            return null;
        }

        $section = CourseSection::query()
            ->whereKey((int) $link->course_section_id)
            ->where('type', CourseSection::TYPE_SURVEY)
            ->where('is_enabled', true)
            ->first();

        if ($section === null) {
            return null;
        }

        $link->setRelation('courseSection', $section);

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

    public function activeLinkForSection(int $sectionId): ?CourseSurveyLink
    {
        if (! $this->tableReady() || $sectionId < 1) {
            return null;
        }

        $link = CourseSurveyLink::query()
            ->where('course_section_id', $sectionId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        return $link !== null && $link->isUsable() ? $link : null;
    }

    private function newToken(): string
    {
        do {
            $token = Str::lower(Str::random(32));
        } while (CourseSurveyLink::query()->where('token', $token)->exists());

        return $token;
    }
}
