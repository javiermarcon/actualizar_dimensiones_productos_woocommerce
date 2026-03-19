<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWBackgroundJobUtilsTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

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

    public function testGetJobAndSaveJobRoundTripThroughOptions(): void {
        ADPW_Background_Job_Utils::save_job('adpw_test_job', [
            'id' => 'job-1',
            'status' => 'running',
        ]);

        $job = ADPW_Background_Job_Utils::get_job('adpw_test_job');

        self::assertSame('job-1', $job['id']);
        self::assertSame('running', $job['status']);
    }

    public function testAppendDebugTrimsLogAndScheduleNextBatchAvoidsDuplicates(): void {
        $job = [
            'debug_log' => array_fill(0, 300, 'old'),
        ];

        ADPW_Background_Job_Utils::append_debug($job, 'nuevo');
        ADPW_Background_Job_Utils::schedule_next_batch('adpw_test_hook', 'job-1');
        ADPW_Background_Job_Utils::schedule_next_batch('adpw_test_hook', 'job-1');

        self::assertCount(300, $job['debug_log']);
        self::assertStringContainsString('nuevo', $job['debug_log'][299]);
        self::assertCount(1, $GLOBALS['adpw_test_scheduled_events']);
    }
}
