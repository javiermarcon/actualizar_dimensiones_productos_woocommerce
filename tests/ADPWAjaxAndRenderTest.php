<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

    public function testImportAjaxStatusRejectsInvalidNonce(): void {
        $GLOBALS['adpw_test_check_ajax_referer'] = false;

        try {
            ADPW_Import_Queue_Manager::ajax_import_status();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Nonce inválido.', $e->payload['message']);
        }
    }

    public function testImportAjaxStatusRejectsUserWithoutPermissions(): void {
        $GLOBALS['adpw_test_current_user_can'] = false;

        try {
            ADPW_Import_Queue_Manager::ajax_import_status();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('No tenés permisos para consultar estado.', $e->payload['message']);
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

    public function testImportAjaxRunBatchRejectsUserWithoutPermissions(): void {
        $GLOBALS['adpw_test_current_user_can'] = false;

        try {
            ADPW_Import_Queue_Manager::ajax_run_batch();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('No tenés permisos para ejecutar lotes.', $e->payload['message']);
        }
    }

    public function testImportAjaxRunBatchRejectsInvalidNonce(): void {
        $GLOBALS['adpw_test_check_ajax_referer'] = false;

        try {
            ADPW_Import_Queue_Manager::ajax_run_batch();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Nonce inválido.', $e->payload['message']);
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

    public function testImportAjaxStartImportRejectsInvalidNonce(): void {
        $GLOBALS['adpw_test_check_ajax_referer'] = false;

        try {
            ADPW_Import_Queue_Manager::ajax_start_import();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Nonce inválido.', $e->payload['message']);
        }
    }

    public function testImportAjaxStartImportReturnsInitializationErrorPayload(): void {
        try {
            ADPW_Import_Queue_Manager::ajax_start_import();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('No se seleccionó ningún archivo válido.', $e->payload['error_general']);
            self::assertSame([], $e->payload['detalles']);
        }
    }

    public function testImportAjaxStartImportReturnsSuccessForValidSpreadsheet(): void {
        $uploadedFile = $this->createSpreadsheetFile([
            ['Categoria', 'Tamano', 'Peso', 'Ancho', 'Largo', 'Profundidad'],
            ['Cascos', 'premium', '1', '2', '3', '4'],
        ]);

        $serviceReflection = new ReflectionClass(ADPW_Excel_Import_Service::class);
        $validator = $serviceReflection->getProperty('uploaded_file_validator');
        $validator->setAccessible(true);
        $validator->setValue(null, static fn (): bool => true);

        $mover = $serviceReflection->getProperty('uploaded_file_mover');
        $mover->setAccessible(true);
        $mover->setValue(null, static function (string $from, string $to): bool {
            return copy($from, $to);
        });

        $uploadDirProvider = $serviceReflection->getProperty('upload_dir_provider');
        $uploadDirProvider->setAccessible(true);
        $uploadDirProvider->setValue(null, static function (): array {
            return [
                'basedir' => sys_get_temp_dir(),
                'baseurl' => 'http://example.test/uploads',
            ];
        });

        $mkdirProvider = $serviceReflection->getProperty('mkdir_p_callback');
        $mkdirProvider->setAccessible(true);
        $mkdirProvider->setValue(null, static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));

        $_FILES = [
            'archivo_excel' => [
                'name' => 'import.xlsx',
                'tmp_name' => $uploadedFile,
                'size' => filesize($uploadedFile),
                'error' => 0,
            ],
        ];

        try {
            ADPW_Import_Queue_Manager::ajax_start_import();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertTrue($e->success);
            self::assertSame('parse_sheet', $e->payload['stage']);
            self::assertSame(20, $e->payload['batch_size']);
        }

        $validator->setValue(null, null);
        $mover->setValue(null, null);
        $uploadDirProvider->setValue(null, null);
        $mkdirProvider->setValue(null, null);
        @unlink($uploadedFile);
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

    public function testCategoryUpdateAjaxRunBatchRejectsUserWithoutPermissions(): void {
        $GLOBALS['adpw_test_current_user_can'] = false;

        try {
            ADPW_Category_Update_Queue_Manager::ajax_run_batch();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('No tenés permisos para ejecutar lotes.', $e->payload['message']);
        }
    }

    public function testCategoryUpdateAjaxRunBatchRejectsInvalidNonce(): void {
        $GLOBALS['adpw_test_check_ajax_referer'] = false;

        try {
            ADPW_Category_Update_Queue_Manager::ajax_run_batch();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Nonce inválido.', $e->payload['message']);
        }
    }

    public function testCategoryUpdateAjaxStatusRejectsUserWithoutPermissions(): void {
        $GLOBALS['adpw_test_current_user_can'] = false;

        try {
            ADPW_Category_Update_Queue_Manager::ajax_status();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('No tenés permisos para consultar estado.', $e->payload['message']);
        }
    }

    public function testCategoryUpdateAjaxStatusRejectsInvalidNonce(): void {
        $GLOBALS['adpw_test_check_ajax_referer'] = false;

        try {
            ADPW_Category_Update_Queue_Manager::ajax_status();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Nonce inválido.', $e->payload['message']);
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

    public function testExcelImportPageRenderShowsStartErrorBox(): void {
        $uploadedFile = $this->createSpreadsheetFile([
            ['SKU'],
            ['ABC123'],
        ]);

        $serviceReflection = new ReflectionClass(ADPW_Excel_Import_Service::class);
        $validator = $serviceReflection->getProperty('uploaded_file_validator');
        $validator->setAccessible(true);
        $validator->setValue(null, static fn (): bool => true);

        $mover = $serviceReflection->getProperty('uploaded_file_mover');
        $mover->setAccessible(true);
        $mover->setValue(null, static function (string $from, string $to): bool {
            return copy($from, $to);
        });

        $uploadDirProvider = $serviceReflection->getProperty('upload_dir_provider');
        $uploadDirProvider->setAccessible(true);
        $uploadDirProvider->setValue(null, static function (): array {
            return [
                'basedir' => sys_get_temp_dir(),
                'baseurl' => 'http://example.test/uploads',
                'error' => '',
            ];
        });

        $mkdirProvider = $serviceReflection->getProperty('mkdir_p_callback');
        $mkdirProvider->setAccessible(true);
        $mkdirProvider->setValue(null, static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'adpw_start_import_form_nonce' => 'nonce',
        ];
        $_FILES = [
            'archivo_excel' => [
                'name' => 'invalid.xlsx',
                'tmp_name' => $uploadedFile,
                'size' => filesize($uploadedFile),
                'error' => 0,
            ],
        ];

        ob_start();
        ADPW_Excel_Import_Page::render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Error', $html);
        self::assertStringContainsString('No se encontraron los encabezados esperados en el archivo Excel.', $html);

        $validator->setValue(null, null);
        $mover->setValue(null, null);
        $uploadDirProvider->setValue(null, null);
        $mkdirProvider->setValue(null, null);
        @unlink($uploadedFile);
    }

    public function testExcelImportPageRenderShowsManualBatchMessage(): void {
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

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'adpw_run_manual_batch' => '1',
            'adpw_manual_batch_nonce' => 'nonce',
        ];

        ob_start();
        ADPW_Excel_Import_Page::render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Batch Manual', $html);
        self::assertStringContainsString('Se ejecutó manualmente un lote de importación.', $html);

        @unlink($categoriesFile);
    }

    public function testExcelImportPageRenderShowsStartMessage(): void {
        $uploadedFile = $this->createSpreadsheetFile([
            ['Categoria', 'Tamano', 'Peso', 'Ancho', 'Largo', 'Profundidad'],
            ['Cascos', 'premium', '1', '2', '3', '4'],
        ]);

        $serviceReflection = new ReflectionClass(ADPW_Excel_Import_Service::class);
        $validator = $serviceReflection->getProperty('uploaded_file_validator');
        $validator->setAccessible(true);
        $validator->setValue(null, static fn (): bool => true);

        $mover = $serviceReflection->getProperty('uploaded_file_mover');
        $mover->setAccessible(true);
        $mover->setValue(null, static function (string $from, string $to): bool {
            return copy($from, $to);
        });

        $uploadDirProvider = $serviceReflection->getProperty('upload_dir_provider');
        $uploadDirProvider->setAccessible(true);
        $uploadDirProvider->setValue(null, static function (): array {
            return [
                'basedir' => sys_get_temp_dir(),
                'baseurl' => 'http://example.test/uploads',
            ];
        });

        $mkdirProvider = $serviceReflection->getProperty('mkdir_p_callback');
        $mkdirProvider->setAccessible(true);
        $mkdirProvider->setValue(null, static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'adpw_start_import_form_nonce' => 'nonce',
        ];
        $_FILES = [
            'archivo_excel' => [
                'name' => 'import.xlsx',
                'tmp_name' => $uploadedFile,
                'size' => filesize($uploadedFile),
                'error' => 0,
            ],
        ];

        ob_start();
        ADPW_Excel_Import_Page::render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Inicio', $html);
        self::assertStringContainsString('Importación iniciada en segundo plano. Job ID:', $html);

        $validator->setValue(null, null);
        $mover->setValue(null, null);
        $uploadDirProvider->setValue(null, null);
        $mkdirProvider->setValue(null, null);
        @unlink($uploadedFile);
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

    private function createSpreadsheetFile(array $rows): string {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1) . (string) ($rowIndex + 1), $value);
            }
        }

        $file = tempnam(sys_get_temp_dir(), 'adpw-xlsx-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        return $file;
    }
}
