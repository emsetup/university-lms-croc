<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CourseSection;
use App\Models\CourseSectionContent;
use PHPUnit\Framework\TestCase;

/**
 * Контракт: markdown теории/практики привязан к course_section_id, не к модулю.
 */
final class CourseSectionContentIsolationTest extends TestCase
{
    public function test_section_content_fillable_is_section_scoped(): void
    {
        $model = new CourseSectionContent;

        $this->assertSame('course_section_contents', $model->getTable());
        $this->assertContains('course_section_id', $model->getFillable());
        $this->assertContains('body_markdown', $model->getFillable());
        $this->assertNotContains('course_module_id', $model->getFillable());
        $this->assertNotContains('theory_markdown', $model->getFillable());
    }

    public function test_text_and_practice_types_are_distinct_section_kinds(): void
    {
        $this->assertSame('text', CourseSection::TYPE_TEXT);
        $this->assertSame('practice', CourseSection::TYPE_PRACTICE);
        $this->assertNotSame(CourseSection::TYPE_TEXT, CourseSection::TYPE_PRACTICE);
    }
}
