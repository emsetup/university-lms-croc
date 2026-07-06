<?php

namespace Tests\Unit;

use App\Support\CourseStaffPreview;
use PHPUnit\Framework\TestCase;

final class CourseStaffPreviewTest extends TestCase
{
    public function test_resolve_preview_rejects_invalid_tokens(): void
    {
        $this->assertNull(CourseStaffPreview::resolvePreview(null));
        $this->assertNull(CourseStaffPreview::resolvePreview(''));
        $this->assertNull(CourseStaffPreview::resolvePreview('not-a-valid-token'));
        $this->assertNull(CourseStaffPreview::resolvePreview('abcdef0123456789abcdef012345678')); // 30 chars
    }

    public function test_route_query_params_empty_without_token(): void
    {
        $this->assertSame([], CourseStaffPreview::routeQueryParams());
    }

    public function test_query_param_constant(): void
    {
        $this->assertSame('course_preview', CourseStaffPreview::QUERY_PARAM);
    }
}
