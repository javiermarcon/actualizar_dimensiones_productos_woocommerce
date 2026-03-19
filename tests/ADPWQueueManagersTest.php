<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWQueueManagersTest extends TestCase {
    protected function tearDown(): void {
        $reflection = new ReflectionProperty(ADPW_Excel_Import_Support::class, 'product_category_lookup');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);
        adpw_test_reset_wp_stubs();
    }

    public function testImportQueueGetJobSnapshotReturnsNullWithoutStoredJob(): void {
        self::assertNull(ADPW_Import_Queue_Manager::get_job_snapshot());
    }

    public function testImportQueueRunBatchNowFailsWithoutRunningJob(): void {
        $result = ADPW_Import_Queue_Manager::run_batch_now();

        self::assertSame('No hay job en ejecución para procesar.', $result['error_general']);
    }

    public function testImportQueueStartImportJobPropagatesInitializationErrors(): void {
        $result = ADPW_Import_Queue_Manager::start_import_job([], [
            'categorias_por_lote' => 10,
            'actualizar_si' => 1,
            'actualizar_tam' => 1,
            'actualizar_cat' => 1,
        ]);

        self::assertSame('No se seleccionó ningún archivo válido.', $result['error_general']);
        self::assertSame([], $result['detalles']);
        self::assertSame([], $result['debug_lines']);
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

    public function testImportQueueProcessBatchIgnoresDifferentJobIdentifier(): void {
        update_option('adpw_import_job', [
            'id' => 'job-import',
            'status' => 'running',
            'stage' => 'parse_sheet',
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'debug_log' => [],
        ]);

        ADPW_Import_Queue_Manager::process_batch('other-job');
        $job = get_option('adpw_import_job');

        self::assertSame('running', $job['status']);
        self::assertSame('parse_sheet', $job['stage']);
        self::assertSame([], $job['debug_log']);
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

    public function testImportQueueGetJobSnapshotBuildsUpdateProductsStageSummary(): void {
        update_option('adpw_import_job', [
            'id' => 'job-import',
            'status' => 'running',
            'stage' => 'update_products',
            'product_queue' => [1, 2, 3, 4],
            'product_cursor' => 2,
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'updated_at' => 10,
        ]);

        $snapshot = ADPW_Import_Queue_Manager::get_job_snapshot();

        self::assertSame('Actualizando productos', $snapshot['stage_label']);
        self::assertSame(83, $snapshot['progress']);
    }

    public function testImportQueueGetJobSnapshotBuildsSaveCategoryMetaStageSummary(): void {
        update_option('adpw_import_job', [
            'id' => 'job-import',
            'status' => 'running',
            'stage' => 'save_category_meta',
            'category_ids' => [7, 8, 9],
            'category_cursor' => 1,
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'updated_at' => 10,
        ]);

        $snapshot = ADPW_Import_Queue_Manager::get_job_snapshot();

        self::assertSame('Guardando metadata de categorías', $snapshot['stage_label']);
        self::assertSame(44, $snapshot['progress']);
    }

    public function testImportQueueProcessBatchSchedulesNextRunWhileJobKeepsRunning(): void {
        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 11;
        $shippingTerm->slug = 'premium';
        $shippingTerm->name = 'Premium';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];

        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');
        file_put_contents($categoriesFile, json_encode([
            '7' => [
                'tamano' => 'premium',
                'peso' => '1',
                'ancho' => '2',
                'largo' => '3',
                'profundidad' => '4',
            ],
            '8' => [
                'tamano' => 'premium',
                'peso' => '1',
                'ancho' => '2',
                'largo' => '3',
                'profundidad' => '4',
            ],
        ]));

        update_option('adpw_import_job', [
            'id' => 'job-import',
            'status' => 'running',
            'stage' => 'save_category_meta',
            'batch_size' => 1,
            'category_cursor' => 0,
            'category_ids' => [7, 8],
            'categories_data_file' => $categoriesFile,
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

        self::assertSame('running', $job['status']);
        self::assertSame(1, $job['category_cursor']);
        self::assertNotEmpty($GLOBALS['adpw_test_scheduled_events']);
        @unlink($categoriesFile);
    }

    public function testImportQueueRunBatchNowReturnsUpdatedSummaryAfterProcessing(): void {
        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 11;
        $shippingTerm->slug = 'premium';
        $shippingTerm->name = 'Premium';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];

        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');
        file_put_contents($categoriesFile, json_encode([
            '7' => [
                'tamano' => 'premium',
                'peso' => '1',
                'ancho' => '2',
                'largo' => '3',
                'profundidad' => '4',
            ],
        ]));

        update_option('adpw_import_job', [
            'id' => 'job-import',
            'status' => 'running',
            'stage' => 'save_category_meta',
            'batch_size' => 10,
            'category_cursor' => 0,
            'category_ids' => [7],
            'categories_data_file' => $categoriesFile,
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'debug_log' => [],
        ]);

        $summary = ADPW_Import_Queue_Manager::run_batch_now();

        self::assertSame('running', $summary['status']);
        self::assertSame('update_products', $summary['stage']);
        self::assertSame('Actualizando productos', $summary['stage_label']);

        @unlink($categoriesFile);
    }

    public function testCategoryUpdateGetJobSnapshotTriggersSpawnCronForRunningJob(): void {
        update_option('adpw_category_update_job', [
            'id' => 'job-tree',
            'status' => 'running',
            'stage' => 'update_products',
            'product_queue' => [1, 2],
            'product_cursor' => 1,
            'results' => [
                'totales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'updated_at' => 10,
        ]);

        $snapshot = ADPW_Category_Update_Queue_Manager::get_job_snapshot();

        self::assertSame('running', $snapshot['status']);
        self::assertNotEmpty($GLOBALS['adpw_test_spawn_cron_calls']);
    }

    public function testCategoryUpdateRunBatchNowFailsWithoutRunningJob(): void {
        $result = ADPW_Category_Update_Queue_Manager::run_batch_now();

        self::assertSame('No hay job del árbol en ejecución para procesar.', $result['error_general']);
    }

    public function testCategoryUpdateRunBatchNowReturnsUpdatedSummaryAfterProcessing(): void {
        $product = new WC_Product(901, 'Mochila');
        $GLOBALS['adpw_test_products'][901] = $product;
        $GLOBALS['adpw_test_term_meta'][7] = [
            ADPW_Category_Metadata_Manager::META_WEIGHT => '1.2',
            ADPW_Category_Metadata_Manager::META_WIDTH => '2.3',
            ADPW_Category_Metadata_Manager::META_DEPTH => '3.4',
            ADPW_Category_Metadata_Manager::META_HEIGHT => '4.5',
        ];

        update_option('adpw_category_update_job', [
            'id' => 'job-tree',
            'status' => 'running',
            'stage' => 'update_products',
            'batch_size' => 10,
            'product_cursor' => 0,
            'product_queue' => [[
                'product_id' => 901,
                'category_id' => 7,
            ]],
            'runtime' => [],
            'results' => [
                'totales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'debug_log' => [],
        ]);

        $summary = ADPW_Category_Update_Queue_Manager::run_batch_now();

        self::assertSame('completed', $summary['status']);
        self::assertSame(100, $summary['progress']);
        self::assertSame('4.5', $product->get_height());
    }
}
