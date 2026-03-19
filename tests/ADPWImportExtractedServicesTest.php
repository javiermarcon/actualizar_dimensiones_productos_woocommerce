<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWImportExtractedServicesTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

    public function testImportJobFactoryBuildsRunningJobAndAppendsWarnings(): void {
        $job = ADPW_Import_Job_Factory::create_job([
            'batch_size' => 5,
            'mode' => [
                'actualizar_tam_dimensiones' => true,
                'solo_tamano_desde_excel' => false,
            ],
            'uploaded_file_path' => '/tmp/import.xlsx',
            'categories_data_file' => '/tmp/categories.json',
            'columns' => ['categoria' => 1],
            'cursor_row' => 2,
            'highest_row' => 10,
            'empty_row_count' => 0,
            'processed_rows' => 0,
            'total_rows' => 9,
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
            'debug_lines' => ['headers=["categoria"]'],
            'warnings' => ['warning de prueba'],
        ], [
            'actualizar_si' => 1,
            'actualizar_tam' => 1,
            'actualizar_cat' => 0,
        ]);

        self::assertSame('running', $job['status']);
        self::assertSame('parse_sheet', $job['stage']);
        self::assertSame(5, $job['batch_size']);
        self::assertTrue($job['settings']['actualizar_si']);
        self::assertNotEmpty($job['debug_log']);
        self::assertContains('warning de prueba', $job['results']['detalles']);
    }

    public function testImportJobSummaryBuildsSaveCategoryStageProgress(): void {
        $summary = ADPW_Import_Job_Summary::build_summary([
            'status' => 'running',
            'stage' => 'save_category_meta',
            'category_ids' => [1, 2, 3],
            'category_cursor' => 1,
            'updated_at' => 10,
        ]);

        self::assertSame('Guardando metadata de categorías', $summary['stage_label']);
        self::assertSame(44, $summary['progress']);
    }
}
