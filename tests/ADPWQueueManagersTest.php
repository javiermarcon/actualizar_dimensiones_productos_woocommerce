<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWQueueManagersTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

    public function testImportQueueRunBatchNowFailsWithoutRunningJob(): void {
        $result = ADPW_Import_Queue_Manager::run_batch_now();

        self::assertSame('No hay job en ejecución para procesar.', $result['error_general']);
    }

    public function testImportQueueProcessBatchFailsUnknownStageAndPersistsJob(): void {
        update_option('adpw_import_job', [
            'id' => 'job-import',
            'status' => 'running',
            'stage' => 'misterio',
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'debug_log' => [],
        ]);

        ADPW_Import_Queue_Manager::process_batch('job-import');
        $job = get_option('adpw_import_job');

        self::assertSame('failed', $job['status']);
        self::assertStringContainsString('Etapa de importación desconocida', $job['error_general']);
    }

    public function testCategoryUpdateStartJobRejectsConcurrentRun(): void {
        update_option('adpw_category_update_job', [
            'id' => 'job-tree',
            'status' => 'running',
        ]);

        $result = ADPW_Category_Update_Queue_Manager::start_job([1, 2], 10);

        self::assertStringContainsString('Ya hay una actualización del árbol en ejecución', $result['error_general']);
    }

    public function testCategoryUpdateStartJobCreatesCompletedJobWhenQueueIsEmpty(): void {
        $result = ADPW_Category_Update_Queue_Manager::start_job([3], 10);
        $job = get_option('adpw_category_update_job');

        self::assertSame('completed', $result['status']);
        self::assertSame('completed', $job['status']);
        self::assertSame([], $job['product_queue']);
    }
}
