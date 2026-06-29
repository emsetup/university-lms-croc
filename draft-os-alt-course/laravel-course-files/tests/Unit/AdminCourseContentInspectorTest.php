<?php

namespace Tests\Unit;

use App\Support\AdminCourseContentInspector;
use PHPUnit\Framework\TestCase;

final class AdminCourseContentInspectorTest extends TestCase
{
    public function test_legacy_content_columns_returns_fixed_set(): void
    {
        $cols = AdminCourseContentInspector::contentColumnsForCourse(6, true);

        $this->assertSame(
            ['text', 'quiz', 'practice', 'exam', 'docker'],
            array_column($cols, 'key')
        );
    }

    public function test_invalid_course_id_without_legacy_returns_legacy_columns(): void
    {
        $cols = AdminCourseContentInspector::contentColumnsForCourse(0, false);

        $this->assertSame(
            ['text', 'quiz', 'practice', 'exam', 'docker'],
            array_column($cols, 'key')
        );
    }
}
