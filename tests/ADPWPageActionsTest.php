<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ADPWPageActionsTest extends TestCase {
    private array $originalPost = [];
    private array $originalFiles = [];
    private array $originalServer = [];

    protected function setUp(): void {
        $this->originalPost = $_POST;
        $this->originalFiles = $_FILES;
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void {
        $_POST = $this->originalPost;
        $_FILES = $this->originalFiles;
        $_SERVER = $this->originalServer;
        adpw_test_reset_wp_stubs();
    }

    public function testExcelImportPageActionsReturnNonceErrorForInvalidStartRequest(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'adpw_start_import_form_nonce' => 'bad',
        ];
        $GLOBALS['adpw_test_verify_nonce'] = false;

        $result = ADPW_Excel_Import_Page_Actions::handle_requests(
            [],
            'adpw_start_import_form',
            'adpw_start_import_form_nonce',
            'adpw_manual_batch',
            'adpw_manual_batch_nonce'
        );

        self::assertSame('POST detectado pero nonce inválido en inicio de importación.', $result['start_error']);
    }

    public function testExcelImportPageActionsReturnManualBatchMessage(): void {
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
        ]);

        $_POST = [
            'adpw_run_manual_batch' => '1',
            'adpw_manual_batch_nonce' => 'nonce',
        ];

        $result = ADPW_Excel_Import_Page_Actions::handle_requests(
            [],
            'adpw_start_import_form',
            'adpw_start_import_form_nonce',
            'adpw_manual_batch',
            'adpw_manual_batch_nonce'
        );

        self::assertSame('Se ejecutó manualmente un lote de importación.', $result['manual_message']);
        @unlink($categoriesFile);
    }

    public function testExcelImportPageActionsIncludeDebugLinesWhenStartFails(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'adpw_start_import_form_nonce' => 'nonce',
        ];
        $_FILES = [
            'archivo_excel' => [],
        ];

        $result = ADPW_Excel_Import_Page_Actions::handle_requests(
            [],
            'adpw_start_import_form',
            'adpw_start_import_form_nonce',
            'adpw_manual_batch',
            'adpw_manual_batch_nonce'
        );

        self::assertSame('No se seleccionó ningún archivo válido.', $result['start_error']);
        self::assertSame([], $result['start_error_details']);
    }

    public function testExcelImportPageActionsIncludeDetailsAndDebugLinesFromFailedStart(): void {
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

        $result = ADPW_Excel_Import_Page_Actions::handle_requests(
            ['categorias_por_lote' => 5, 'actualizar_tam' => 1, 'actualizar_cat' => 0],
            'adpw_start_import_form',
            'adpw_start_import_form_nonce',
            'adpw_manual_batch',
            'adpw_manual_batch_nonce'
        );

        self::assertSame('No se encontraron los encabezados esperados en el archivo Excel.', $result['start_error']);
        self::assertNotEmpty($result['start_error_details']);
        self::assertStringContainsString('Incluí al menos:', $result['start_error_details'][0]);
        self::assertStringContainsString('debug:', implode("\n", $result['start_error_details']));

        $validator->setValue(null, null);
        $mover->setValue(null, null);
        $uploadDirProvider->setValue(null, null);
        $mkdirProvider->setValue(null, null);
        @unlink($uploadedFile);
    }

    public function testExcelImportPageActionsStartSuccessfulImport(): void {
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

        $result = ADPW_Excel_Import_Page_Actions::handle_requests(
            ['categorias_por_lote' => 5],
            'adpw_start_import_form',
            'adpw_start_import_form_nonce',
            'adpw_manual_batch',
            'adpw_manual_batch_nonce'
        );

        self::assertStringContainsString('Importación iniciada en segundo plano. Job ID:', $result['start_message']);
        self::assertSame('', $result['start_error']);

        $validator->setValue(null, null);
        $mover->setValue(null, null);
        $uploadDirProvider->setValue(null, null);
        $mkdirProvider->setValue(null, null);
        @unlink($uploadedFile);
    }

    public function testCategoryMetadataPageActionsReturnManualBatchErrorWhenJobCannotRun(): void {
        $_POST = [
            'adpw_run_category_tree_batch' => '1',
            'adpw_category_tree_manual_batch_nonce' => 'nonce',
        ];

        $result = ADPW_Category_Metadata_Page_Actions::handle_manual_batch(
            'adpw_category_tree_manual_batch',
            'adpw_category_tree_manual_batch_nonce'
        );

        self::assertSame('No hay job del árbol en ejecución para procesar.', $result['manual_error']);
    }

    public function testCategoryMetadataPageActionsDelegateSaveRequest(): void {
        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 90;
        $shippingTerm->slug = 'premium';
        $shippingTerm->name = 'Premium';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];

        $_POST = [
            'guardar_metadata_por_categoria_nonce' => 'nonce',
            'metadata_categoria' => [
                12 => [
                    'clase_envio' => 'premium',
                ],
            ],
        ];

        $result = ADPW_Category_Metadata_Page_Actions::handle_save_request(
            'guardar_metadata_por_categoria_action',
            'guardar_metadata_por_categoria_nonce',
            'metadata_categoria'
        );

        self::assertSame('Se actualizaron 1 categorías.', $result['mensaje']);
    }

    public function testCategoryMetadataPageActionsReturnNoChangesWhenMetadataPayloadIsMissing(): void {
        $_POST = [
            'guardar_metadata_por_categoria_nonce' => 'nonce',
        ];

        $result = ADPW_Category_Metadata_Page_Actions::handle_save_request(
            'guardar_metadata_por_categoria_action',
            'guardar_metadata_por_categoria_nonce',
            'metadata_categoria'
        );

        self::assertSame('No hubo cambios para guardar.', $result['mensaje']);
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
