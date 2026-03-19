<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWAjaxAndRenderTest extends TestCase {
    private array $originalPost = [];
    private array $originalFiles = [];
    private array $originalRequest = [];
    private array $originalServer = [];

    protected function setUp(): void {
        $this->originalPost = $_POST;
        $this->originalFiles = $_FILES;
        $this->originalRequest = $_REQUEST;
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void {
        $_POST = $this->originalPost;
        $_FILES = $this->originalFiles;
        $_REQUEST = $this->originalRequest;
        $_SERVER = $this->originalServer;
        adpw_test_reset_wp_stubs();
    }

    public function testImportAjaxStatusReturnsIdleWhenNoJobExists(): void {
        try {
            ADPW_Import_Queue_Manager::ajax_import_status();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertTrue($e->success);
            self::assertSame('idle', $e->payload['status']);
        }
    }

    public function testImportAjaxRunBatchReturnsErrorWhenNoJobIsRunning(): void {
        try {
            ADPW_Import_Queue_Manager::ajax_run_batch();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('No hay job en ejecución para procesar.', $e->payload['error_general']);
        }
    }

    public function testImportAjaxStartImportRejectsUserWithoutPermissions(): void {
        $GLOBALS['adpw_test_current_user_can'] = false;

        try {
            ADPW_Import_Queue_Manager::ajax_start_import();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('No tenés permisos para iniciar la importación.', $e->payload['message']);
        }
    }

    public function testCategoryUpdateAjaxStatusReturnsIdleWhenNoJobExists(): void {
        try {
            ADPW_Category_Update_Queue_Manager::ajax_status();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertTrue($e->success);
            self::assertSame('idle', $e->payload['status']);
        }
    }

    public function testCategoryUpdateAjaxRunBatchReturnsErrorWhenNoJobExists(): void {
        try {
            ADPW_Category_Update_Queue_Manager::ajax_run_batch();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('No hay job del árbol en ejecución para procesar.', $e->payload['error_general']);
        }
    }

    public function testSettingsRenderPageOutputsCurrentTabMarkup(): void {
        $_REQUEST = [
            'tab' => 'import',
        ];

        ob_start();
        ADPW_Settings::render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Configuración', $html);
        self::assertStringContainsString('Importación Excel', $html);
        self::assertStringContainsString('Actualizar siempre', $html);
    }

    public function testExcelImportPageRenderOutputsImportForm(): void {
        ob_start();
        ADPW_Excel_Import_Page::render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Importar Excel', $html);
        self::assertStringContainsString('adpw-import-form', $html);
        self::assertStringContainsString('Procesar siguiente lote ahora', $html);
    }

    public function testCategoryMetadataPageRenderShowsEmptyMessageWhenThereAreNoCategories(): void {
        ob_start();
        ADPW_Category_Metadata_Page::render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Árbol de categorías', $html);
        self::assertStringContainsString('No hay categorías de producto para mostrar.', $html);
    }

    public function testImportAjaxStatusReturnsRunningSummaryAndTriggersCron(): void {
        update_option('adpw_import_job', [
            'id' => 'job-import',
            'status' => 'running',
            'stage' => 'parse_sheet',
            'processed_rows' => 1,
            'total_rows' => 10,
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'updated_at' => 10,
        ]);

        try {
            ADPW_Import_Queue_Manager::ajax_import_status();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertTrue($e->success);
            self::assertSame('running', $e->payload['status']);
            self::assertSame('parse_sheet', $e->payload['stage']);
            self::assertNotEmpty($GLOBALS['adpw_test_spawn_cron_calls']);
        }
    }

    public function testImportAjaxRunBatchReturnsSuccessfulSummary(): void {
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

        try {
            ADPW_Import_Queue_Manager::ajax_run_batch();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertTrue($e->success);
            self::assertSame('running', $e->payload['status']);
            self::assertSame('update_products', $e->payload['stage']);
        }

        @unlink($categoriesFile);
    }

    public function testCategoryUpdateAjaxStatusReturnsRunningSummary(): void {
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

        try {
            ADPW_Category_Update_Queue_Manager::ajax_status();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertTrue($e->success);
            self::assertSame('running', $e->payload['status']);
            self::assertSame('update_products', $e->payload['stage']);
        }
    }

    public function testCategoryUpdateAjaxRunBatchReturnsSuccessfulSummary(): void {
        $product = new WC_Product(901, 'Mochila');
        $GLOBALS['adpw_test_products'][901] = $product;
        $GLOBALS['adpw_test_term_meta'][7] = [
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

        try {
            ADPW_Category_Update_Queue_Manager::ajax_run_batch();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertTrue($e->success);
            self::assertSame('completed', $e->payload['status']);
            self::assertSame(100, $e->payload['progress']);
        }
    }
}
