<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CourseContentMarkdown;
use PHPUnit\Framework\TestCase;

final class CourseContentMarkdownTocTest extends TestCase
{
    public function test_headings_get_unique_ids(): void
    {
        $html = CourseContentMarkdown::toHtml("## Один\n\n## Один\n\n### Два\n");

        $this->assertStringContainsString('id="odin"', $html);
        $this->assertStringContainsString('id="odin-2"', $html);
        $this->assertStringContainsString('id="dva"', $html);
    }

    public function test_toc_marker_renders_links_to_headings(): void
    {
        $md = "[[toc]]\n\n## Раздел А\n\nТекст.\n\n### Подраздел\n\n## Раздел Б\n";
        $html = CourseContentMarkdown::toHtml($md);

        $this->assertStringContainsString('class="theory-toc"', $html);
        $this->assertStringContainsString('href="#razdel-a"', $html);
        $this->assertStringContainsString('href="#podrazdel"', $html);
        $this->assertStringContainsString('href="#razdel-b"', $html);
        $this->assertStringNotContainsString('[[toc]]', $html);
        $this->assertStringNotContainsString('COURSE_CONTENT_TOC', $html);
    }

    public function test_toc_case_insensitive_bracket_form(): void
    {
        $html = CourseContentMarkdown::toHtml("[TOC]\n\n## Заголовок\n");
        $this->assertStringContainsString('class="theory-toc"', $html);
        $this->assertStringContainsString('href="#zagolovok"', $html);
    }

    public function test_centered_heading_keeps_class_and_gets_id(): void
    {
        $html = CourseContentMarkdown::toHtml("## {center} Центр\n");
        $this->assertStringContainsString('theory-heading--center', $html);
        $this->assertMatchesRegularExpression('/<h2[^>]*\bid="/', $html);
    }

    public function test_typographic_bullets_become_list_items(): void
    {
        $md = "ДО разделяются на две категории:\n"
            ."• Расчетные: оплата.\n"
            ."• Нерасчетные: без оплат.\n";
        $html = CourseContentMarkdown::toHtml($md);

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Расчетные: оплата.</li>', $html);
        $this->assertStringContainsString('<li>Нерасчетные: без оплат.</li>', $html);
        $this->assertStringNotContainsString('• Расчетные', $html);
    }

    public function test_typographic_bullets_inside_code_fence_are_kept(): void
    {
        $md = "```\n• не список\n```\n";
        $html = CourseContentMarkdown::toHtml($md);

        $this->assertStringContainsString('• не список', $html);
        $this->assertStringNotContainsString('<ul>', $html);
    }
}
