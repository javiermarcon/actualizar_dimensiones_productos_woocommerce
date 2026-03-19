<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWCategoryUpdateExtractedServicesTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

    public function testCategoryUpdateJobStoreRoundTripsStateAndScheduling(): void {
        ADPW_Category_Update_Job_Store::save_job('adpw_category_update_job', [
            'id' => 'job-tree',
            'status' => 'running',
        ]);

        $job = ADPW_Category_Update_Job_Store::get_job('adpw_category_update_job');
        ADPW_Category_Update_Job_Store::append_debug($job, 'debug line');
        ADPW_Category_Update_Job_Store::schedule_next_batch('adpw_process_category_update_batch', 'job-tree');

        self::assertSame('job-tree', $job['id']);
        self::assertStringContainsString('debug line', $job['debug_log'][0]);
        self::assertCount(1, $GLOBALS['adpw_test_scheduled_events']);
    }

    public function testCategoryUpdateJobSummaryBuildsProductQueueProgress(): void {
        $summary = ADPW_Category_Update_Job_Summary::build_summary([
            'status' => 'running',
            'stage' => 'update_products',
            'product_queue' => [1, 2, 3, 4],
            'product_cursor' => 2,
            'updated_at' => 10,
        ]);

        self::assertSame('Actualizando productos desde árbol de categorías', $summary['stage_label']);
        self::assertSame(50, $summary['progress']);
        self::assertSame(2, $summary['processed_entries']);
        self::assertSame(4, $summary['total_entries']);
    }
}
