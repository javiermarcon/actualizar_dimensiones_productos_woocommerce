<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class ADPWImportOrchestrationTest extends TestCase {
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

    public function testImportJobSummaryBuildsParseStageProgressForIdleJob(): void {
        $summary = ADPW_Import_Job_Summary::build_summary([
            'status' => 'idle',
            'stage' => 'parse_sheet',
            'total_rows' => 10,
            'processed_rows' => 5,
            'updated_at' => 10,
        ]);

        self::assertSame('Leyendo planilla y generando temporal', $summary['stage_label']);
        self::assertSame(16, $summary['progress']);
    }

    public function testImportJobSummaryBuildsCompletedProgressForUpdateStage(): void {
        $summary = ADPW_Import_Job_Summary::build_summary([
            'status' => 'completed',
            'stage' => 'update_products',
            'product_queue' => [1, 2, 3],
            'product_cursor' => 1,
            'updated_at' => 10,
        ]);

        self::assertSame('Actualizando productos', $summary['stage_label']);
        self::assertSame(100, $summary['progress']);
    }

    public function testImportJobFactoryKeepsEmptyWarningsAndDebugStable(): void {
        $job = ADPW_Import_Job_Factory::create_job([
            'batch_size' => 2,
            'mode' => [],
            'uploaded_file_path' => '/tmp/import.xlsx',
            'categories_data_file' => '/tmp/categories.json',
            'columns' => [],
            'cursor_row' => 2,
            'highest_row' => 2,
            'empty_row_count' => 0,
            'processed_rows' => 0,
            'total_rows' => 0,
            'category_ids' => [],
            'category_cursor' => 0,
            'product_queue' => [],
            'product_cursor' => 0,
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'debug_lines' => [],
            'warnings' => [],
        ], []);

        self::assertSame('parse_sheet', $job['stage']);
        self::assertCount(1, $job['debug_log']);
        self::assertSame([], $job['results']['detalles']);
    }

    public function testImportBatchRunnerMarksJobFailedOnException(): void {
        $job = ADPW_Import_Batch_Runner::process([
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

        self::assertSame('failed', $job['status']);
        self::assertStringContainsString('Etapa de importación desconocida', $job['error_general']);
        self::assertNotEmpty($job['debug_log']);
    }

    public function testExcelImportPageActionsBuildsStartMessageOnSuccessfulStart(): void {
        $uploadedFile = $this->createSpreadsheetFile([
            ['Categoria', 'Tamano', 'Peso', 'Ancho', 'Largo', 'Profundidad'],
            ['Cascos', 'premium', '1', '2', '3', '4'],
        ]);

        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');
        file_put_contents($categoriesFile, json_encode([]));

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
        $uploadDirProvider->setValue(null, static function () use ($categoriesFile): array {
            return [
                'basedir' => dirname($categoriesFile),
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
        @unlink($categoriesFile);
    }

    public function testExcelImportPageActionsExposeManualBatchError(): void {
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

        self::assertSame('No hay job en ejecución para procesar.', $result['start_error']);
    }

    public function testCategoryMetadataPageActionsIgnoreManualBatchWithInvalidNonce(): void {
        $GLOBALS['adpw_test_verify_nonce'] = false;
        $_POST = [
            'adpw_run_category_tree_batch' => '1',
            'adpw_category_tree_manual_batch_nonce' => 'bad',
        ];

        $result = ADPW_Category_Metadata_Page_Actions::handle_manual_batch(
            'adpw_category_tree_manual_batch',
            'adpw_category_tree_manual_batch_nonce'
        );

        self::assertSame('', $result['manual_message']);
        self::assertSame('', $result['manual_error']);
    }

    public function testCategoryMetadataPageActionsReturnNoChangesMessageWithoutPayload(): void {
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
