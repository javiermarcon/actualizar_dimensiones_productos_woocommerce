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

    public function testCategoryUpdateJobFactoryBuildsCompletedJobForEmptyQueue(): void {
        $job = ADPW_Category_Update_Job_Factory::create_job([3, 5], 0, []);

        self::assertSame('completed', $job['status']);
        self::assertSame(1, $job['batch_size']);
        self::assertSame([3, 5], $job['category_ids']);
        self::assertNotEmpty($job['debug_log']);
    }

    public function testCategoryUpdateBatchRunnerMarksJobFailedOnProcessingException(): void {
        $GLOBALS['adpw_test_products'][999] = new class (999, 'Explota') extends WC_Product {
            public function save(): void {
                throw new RuntimeException('save exploded');
            }
        };
        $GLOBALS['adpw_test_term_meta'][7] = [
            ADPW_Category_Metadata_Manager::META_CLASS => '',
            ADPW_Category_Metadata_Manager::META_WEIGHT => '1',
            ADPW_Category_Metadata_Manager::META_HEIGHT => '',
            ADPW_Category_Metadata_Manager::META_WIDTH => '',
            ADPW_Category_Metadata_Manager::META_DEPTH => '',
        ];

        $job = ADPW_Category_Update_Batch_Runner::process([
            'id' => 'job-tree',
            'status' => 'running',
            'stage' => 'update_products',
            'batch_size' => 1,
            'product_queue' => [[
                'product_id' => 999,
                'category_id' => 7,
            ]],
            'product_cursor' => 0,
            'results' => [
                'totales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'runtime' => [],
            'error_general' => '',
            'debug_log' => [],
        ]);

        self::assertSame('failed', $job['status']);
        self::assertStringContainsString('Excepción en batch del árbol:', $job['error_general']);
        self::assertNotEmpty($job['debug_log']);
    }
}
