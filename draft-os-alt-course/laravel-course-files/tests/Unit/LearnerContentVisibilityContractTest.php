<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ContentViewAudienceRule;
use App\Models\Course;
use PHPUnit\Framework\TestCase;

/**
 * Логика видимости без БД: константы и контракт правил.
 */
final class LearnerContentVisibilityContractTest extends TestCase
{
    public function test_resource_types_include_course_module_section(): void
    {
        $this->assertContains('course', ContentViewAudienceRule::RESOURCE_TYPES);
        $this->assertContains('module', ContentViewAudienceRule::RESOURCE_TYPES);
        $this->assertContains('section', ContentViewAudienceRule::RESOURCE_TYPES);
    }

    public function test_subject_types_include_learner_and_groups(): void
    {
        $this->assertContains('learner', ContentViewAudienceRule::SUBJECT_TYPES);
        $this->assertContains('portal_group', ContentViewAudienceRule::SUBJECT_TYPES);
        $this->assertContains('course_group', ContentViewAudienceRule::SUBJECT_TYPES);
    }

    public function test_view_audience_constants(): void
    {
        $this->assertSame('all', Course::VIEW_AUDIENCE_ALL);
        $this->assertSame('restricted', Course::VIEW_AUDIENCE_RESTRICTED);
    }
}
