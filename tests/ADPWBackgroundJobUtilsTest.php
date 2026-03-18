<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWBackgroundJobUtilsTest extends TestCase {
    public function testBuildSummaryForRunningJobKeepsMinimumProgress(): void {
        $summary = ADPW_Background_Job_Utils::build_summary(
            [
                'status' => 'running',
                'stage' => 'parse_sheet',
                'updated_at' => 123,
            ],
            'Leyendo planilla',
            0,
            10
        );

        self::assertSame('running', $summary['status']);
        self::assertSame(1, $summary['progress']);
        self::assertSame(0, $summary['processed_entries']);
        self::assertSame(10, $summary['total_entries']);
    }

    public function testBuildSummaryForCompletedJobForcesHundredPercent(): void {
        $summary = ADPW_Background_Job_Utils::build_summary(
            [
                'status' => 'completed',
                'stage' => 'update_products',
                'updated_at' => 456,
            ],
            'Actualizando productos',
            2,
            10
        );

        self::assertSame('completed', $summary['status']);
        self::assertSame(100, $summary['progress']);
    }
}
