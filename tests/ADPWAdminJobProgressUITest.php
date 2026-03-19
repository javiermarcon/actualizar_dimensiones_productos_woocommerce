<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWAdminJobProgressUITest extends TestCase {
    public function testRenderProgressMarkupOutputsExpectedContainers(): void {
        ob_start();
        ADPW_Admin_Job_Progress_UI::render_progress_markup('adpw-import');
        $html = (string) ob_get_clean();

        self::assertStringContainsString('adpw-import-progress-wrapper', $html);
        self::assertStringContainsString('adpw-import-progress-bar', $html);
        self::assertStringContainsString('adpw-import-debug-log', $html);
    }

    public function testRenderBoxOutputsFormattedPreBlock(): void {
        ob_start();
        ADPW_Admin_Job_Progress_UI::render_box('Titulo', "Linea 1\nLinea 2", '#111111', '#eeeeee', 'box-id');
        $html = (string) ob_get_clean();

        self::assertStringContainsString('<strong>Titulo</strong>', $html);
        self::assertStringContainsString('id="box-id"', $html);
        self::assertStringContainsString('Linea 1', $html);
        self::assertStringContainsString('background:#eeeeee', $html);
    }

    public function testRenderStatusSnapshotOutputsIdleMessageWhenSnapshotIsMissing(): void {
        ob_start();
        ADPW_Admin_Job_Progress_UI::render_status_snapshot('Estado', null, 'adpw-import');
        $html = (string) ob_get_clean();

        self::assertStringContainsString('No hay job activo.', $html);
        self::assertStringContainsString('adpw-import-status-snapshot', $html);
    }

    public function testRenderStatusSnapshotOutputsSnapshotLinesWhenDataExists(): void {
        ob_start();
        ADPW_Admin_Job_Progress_UI::render_status_snapshot('Estado', [
            'status' => 'running',
            'stage' => 'parse_sheet',
            'stage_label' => 'Leyendo planilla',
            'progress' => 10,
            'processed_entries' => 1,
            'total_entries' => 10,
            'results' => [
                'totales' => 2,
                'parciales' => 1,
                'errores' => 0,
            ],
            'updated_at' => 100,
            'debug_log' => ['uno'],
        ], 'adpw-import');
        $html = (string) ob_get_clean();

        self::assertStringContainsString('status: running', $html);
        self::assertStringContainsString('stage_label: Leyendo planilla', $html);
        self::assertStringContainsString('results.parciales: 1', $html);
        self::assertStringContainsString('--- debug_log ---', $html);
    }

    public function testRenderPollingJsEmbedsConfiguredActionsAndMessages(): void {
        ob_start();
        ADPW_Admin_Job_Progress_UI::render_polling_js([
            'prefix' => 'adpw-import',
            'nonce' => 'nonce',
            'statusAction' => 'adpw_import_status',
            'runBatchAction' => 'adpw_import_run_batch',
            'startFormId' => 'form-id',
            'startButtonId' => 'button-id',
            'validateInputId' => 'file-id',
            'startText' => 'Iniciando',
            'completedText' => 'Completado',
            'errorText' => 'Error',
            'emptyInputMessage' => 'Seleccioná archivo',
        ]);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('admin-ajax.php', $html);
        self::assertStringContainsString('adpw_import_status', $html);
        self::assertStringContainsString('adpw_import_run_batch', $html);
        self::assertStringContainsString('Seleccion\\u00e1 archivo', $html);
        self::assertStringContainsString('beginPolling()', $html);
    }

    public function testBuildSnapshotLinesIncludesOptionalFields(): void {
        $lines = $this->invokePrivateStaticMethod(ADPW_Admin_Job_Progress_UI::class, 'build_snapshot_lines', [[
            'status' => 'failed',
            'stage' => 'parse_sheet',
            'stage_label' => 'Leyendo planilla',
            'progress' => 50,
            'processed_entries' => 5,
            'total_entries' => 10,
            'error_general' => 'fallo',
            'results' => [
                'totales' => 1,
                'parciales' => 2,
                'errores' => 3,
            ],
            'updated_at' => 100,
            'debug_log' => ['uno', 'dos'],
        ]]);

        self::assertContains('status: failed', $lines);
        self::assertContains('error_general: fallo', $lines);
        self::assertContains('results.totales: 1', $lines);
        self::assertContains('results.parciales: 2', $lines);
        self::assertContains('results.errores: 3', $lines);
        self::assertContains('--- debug_log ---', $lines);
    }

    private function invokePrivateStaticMethod(string $className, string $methodName, array $args = []) {
        $reflection = new ReflectionMethod($className, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }
}
