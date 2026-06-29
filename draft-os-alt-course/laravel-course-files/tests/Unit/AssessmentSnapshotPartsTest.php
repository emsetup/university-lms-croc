<?php

namespace Tests\Unit;

use App\Services\CourseScoringService;
use App\Services\CourseSectionService;
use PHPUnit\Framework\TestCase;

final class AssessmentSnapshotPartsTest extends TestCase
{
    public function test_legacy_type_color_key_mapping(): void
    {
        $this->assertSame('tq', CourseSectionService::legacyTypeColorKey('theory_quiz'));
        $this->assertSame('pr', CourseSectionService::legacyTypeColorKey('practice'));
        $this->assertSame('ex', CourseSectionService::legacyTypeColorKey('module_exam'));
    }

    public function test_build_summary_parts_averages_only_present_stages(): void
    {
        $rows = [
            [
                'parts' => [
                    ['color_key' => 'ex', 'label' => 'Экзамен', 'pct' => 80],
                ],
            ],
            [
                'parts' => [
                    ['color_key' => 'ex', 'label' => 'Экзамен', 'pct' => 60],
                ],
            ],
        ];

        $summary = CourseScoringService::buildSummaryParts($rows);

        $this->assertCount(1, $summary);
        $this->assertSame('ex', $summary[0]['color_key']);
        $this->assertSame('Экзамен', $summary[0]['label']);
        $this->assertSame(70, $summary[0]['pct']);
    }

    public function test_exam_only_module_has_no_theory_quiz_part(): void
    {
        $parts = [
            [
                'key' => 'module_exam',
                'color_key' => 'ex',
                'label' => 'Экзамен',
                'pct' => 0,
                'attempts' => 0,
                'weight_pct' => 100,
                'legacy_key' => 'module_exam',
            ],
        ];

        $this->assertNull(CourseScoringService::partPctByLegacyKey($parts, 'theory_quiz'));
        $this->assertSame(0, CourseScoringService::partPctByLegacyKey($parts, 'module_exam'));
        $this->assertCount(1, $parts);
    }

    public function test_assess_parts_risk_flags_below_threshold(): void
    {
        $risk = CourseScoringService::assessPartsRisk([
            ['color_key' => 'ex', 'pct' => 55],
        ]);

        $this->assertTrue($risk['any_below_pass']);
        $this->assertSame('ex', $risk['weak_key']);
    }
}
