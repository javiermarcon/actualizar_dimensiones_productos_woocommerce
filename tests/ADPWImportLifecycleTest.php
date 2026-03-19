<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWImportLifecycleTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

    public function testGetJobSnapshotBuildsProgressForParseStage(): void {
        update_option('adpw_import_job', [
            'id' => 'job-1',
            'status' => 'running',
            'stage' => 'parse_sheet',
            'processed_rows' => 5,
            'total_rows' => 10,
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'updated_at' => 100,
        ]);

        $snapshot = ADPW_Import_Queue_Manager::get_job_snapshot();

        self::assertSame('running', $snapshot['status']);
        self::assertSame('parse_sheet', $snapshot['stage']);
        self::assertSame('Leyendo planilla y generando temporal', $snapshot['stage_label']);
        self::assertSame(16, $snapshot['progress']);
    }

    public function testProcessJobBatchFailsWhenParseSheetFileDoesNotExist(): void {
        $job = [
            'stage' => 'parse_sheet',
            'uploaded_file_path' => sys_get_temp_dir() . '/adpw-no-existe.xlsx',
            'results' => [
                'detalles' => [],
            ],
        ];

        ADPW_Excel_Import_Service::process_job_batch($job);

        self::assertSame('failed', $job['status']);
        self::assertSame('No existe el archivo temporal del Excel.', $job['error_general']);
    }

    public function testProcessJobBatchFailsWhenShippingClassesCannotBeValidated(): void {
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
        $GLOBALS['adpw_test_terms_errors']['product_shipping_class'] = new WP_Error();

        $job = [
            'stage' => 'save_category_meta',
            'batch_size' => 5,
            'category_cursor' => 0,
            'category_ids' => [7],
            'categories_data_file' => $categoriesFile,
            'results' => [
                'detalles' => [],
            ],
        ];

        ADPW_Excel_Import_Service::process_job_batch($job);

        self::assertSame('failed', $job['status']);
        self::assertSame('No se pudieron validar clases de envío.', $job['error_general']);
        @unlink($categoriesFile);
    }

    public function testProcessJobBatchTransitionsFromSaveCategoryMetaToUpdateProducts(): void {
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

        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 81;
        $shippingTerm->slug = 'premium';
        $shippingTerm->name = 'Premium';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];

        $job = [
            'stage' => 'save_category_meta',
            'batch_size' => 5,
            'category_cursor' => 0,
            'category_ids' => [7],
            'categories_data_file' => $categoriesFile,
            'results' => [
                'detalles' => [],
            ],
        ];

        ADPW_Excel_Import_Service::process_job_batch($job);

        self::assertSame('update_products', $job['stage']);
        self::assertSame(0, $job['category_cursor']);
        self::assertSame([], $job['product_queue']);
        self::assertSame('premium', $GLOBALS['adpw_test_term_meta'][7][ADPW_Category_Metadata_Manager::META_CLASS]);
        @unlink($categoriesFile);
    }

    public function testProcessJobBatchCompletesUpdateProductsAndCleansTempFiles(): void {
        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');
        $uploadedFile = tempnam(sys_get_temp_dir(), 'adpw-xlsx-');
        file_put_contents($categoriesFile, json_encode([
            '7' => [
                'categoria' => 'Viaje',
                'tamano' => '',
                'peso' => 1.1,
                'ancho' => 2.2,
                'largo' => 3.3,
                'profundidad' => 4.4,
            ],
        ]));

        $product = new WC_Product(900, 'Mochila');
        $GLOBALS['adpw_test_products'][900] = $product;

        $job = [
            'stage' => 'update_products',
            'batch_size' => 5,
            'product_cursor' => 0,
            'product_queue' => [[
                'product_id' => 900,
                'category_id' => 7,
            ]],
            'categories_data_file' => $categoriesFile,
            'uploaded_file_path' => $uploadedFile,
            'settings' => [
                'actualizar_si' => 1,
                'actualizar_cat' => 0,
            ],
            'mode' => [
                'actualizar_tam_dimensiones' => true,
                'solo_tamano_desde_excel' => false,
            ],
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
        ];

        ADPW_Excel_Import_Service::process_job_batch($job);

        self::assertSame('completed', $job['status']);
        self::assertFileDoesNotExist($categoriesFile);
        self::assertFileDoesNotExist($uploadedFile);
        self::assertSame('1.1', $product->get_weight());
        self::assertSame(2, $job['results']['totales']);
    }
}
