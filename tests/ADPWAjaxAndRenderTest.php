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
            self::assertFalse($e->success);
            self::assertSame('Excepción en import_status: JSON response captured', $e->payload['error_general']);
        }
    }

    public function testImportAjaxRunBatchReturnsErrorWhenNoJobIsRunning(): void {
        try {
            ADPW_Import_Queue_Manager::ajax_run_batch();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Excepción en run_batch: JSON response captured', $e->payload['error_general']);
        }
    }

    public function testImportAjaxStartImportRejectsUserWithoutPermissions(): void {
        $GLOBALS['adpw_test_current_user_can'] = false;

        try {
            ADPW_Import_Queue_Manager::ajax_start_import();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Excepción en start_import: JSON response captured', $e->payload['error_general']);
        }
    }

    public function testCategoryUpdateAjaxStatusReturnsIdleWhenNoJobExists(): void {
        try {
            ADPW_Category_Update_Queue_Manager::ajax_status();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Excepción en category_update_status: JSON response captured', $e->payload['error_general']);
        }
    }

    public function testCategoryUpdateAjaxRunBatchReturnsErrorWhenNoJobExists(): void {
        try {
            ADPW_Category_Update_Queue_Manager::ajax_run_batch();
            self::fail('Expected JSON response exception.');
        } catch (ADPW_Test_Json_Response_Exception $e) {
            self::assertFalse($e->success);
            self::assertSame('Excepción en category_update_run_batch: JSON response captured', $e->payload['error_general']);
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
}
