<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWSettingsAndCategoryStartServicesTest extends TestCase {
    private array $originalPost = [];
    private array $originalRequest = [];

    protected function setUp(): void {
        $this->originalPost = $_POST;
        $this->originalRequest = $_REQUEST;
    }

    protected function tearDown(): void {
        $_POST = $this->originalPost;
        $_REQUEST = $this->originalRequest;
        adpw_test_reset_wp_stubs();
    }

    public function testSettingsSaveServiceStoresImportFlags(): void {
        $_POST = [
            'adpw_settings_nonce' => 'nonce',
            'actualizar_si' => '1',
            'actualizar_tam' => '1',
        ];

        $result = ADPW_Settings_Save_Service::handle_save_request('import', 'adpw_import_settings', 'adpw_settings_nonce', 'adpw_save_settings', [
            'actualizar_si' => 0,
            'actualizar_tam' => 0,
            'actualizar_cat' => 0,
            'actualizar_productos_desde_categorias' => 0,
            'categorias_por_lote' => 20,
        ]);

        $settings = get_option('adpw_import_settings');

        self::assertSame('Configuración guardada correctamente.', $result['mensaje']);
        self::assertSame(1, $settings['actualizar_si']);
        self::assertSame(1, $settings['actualizar_tam']);
        self::assertSame(0, $settings['actualizar_cat']);
    }

    public function testSettingsSaveServiceRejectsInvalidNonce(): void {
        $GLOBALS['adpw_test_verify_nonce'] = false;
        $_POST = [
            'adpw_settings_nonce' => 'bad',
        ];

        $result = ADPW_Settings_Save_Service::handle_save_request('common', 'adpw_import_settings', 'adpw_settings_nonce', 'adpw_save_settings', [
            'actualizar_si' => 0,
            'actualizar_tam' => 0,
            'actualizar_cat' => 0,
            'actualizar_productos_desde_categorias' => 0,
            'categorias_por_lote' => 20,
        ]);

        self::assertSame('No se pudo validar la solicitud.', $result['error']);
    }

    public function testSettingsPageRendererOutputsTreeTabForm(): void {
        ob_start();
        ADPW_Settings_Page_Renderer::render('tree', [
            'actualizar_productos_desde_categorias' => 1,
            'categorias_por_lote' => 20,
            'actualizar_si' => 0,
            'actualizar_tam' => 0,
            'actualizar_cat' => 0,
        ], 'adpw_save_settings', 'adpw_settings_nonce');
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Actualizar productos al guardar metadata', $html);
        self::assertStringContainsString('adpw_settings_tab', $html);
        self::assertStringContainsString('Guardar configuración', $html);
    }

    public function testCategoryUpdateStartServiceRejectsConcurrentRunningJob(): void {
        $result = ADPW_Category_Update_Start_Service::start([7], 10, [
            'status' => 'running',
        ]);

        self::assertStringContainsString('Ya hay una actualización del árbol en ejecución', $result['error_general']);
    }

    public function testCategoryUpdateStartServiceRejectsEmptyCategoryList(): void {
        $result = ADPW_Category_Update_Start_Service::start([], 10, null);

        self::assertSame('No hay categorías para actualizar en segundo plano.', $result['error_general']);
    }

    public function testCategoryUpdateStartServiceBuildsJobAndResponse(): void {
        $GLOBALS['adpw_test_posts'] = [501];
        $GLOBALS['adpw_test_post_terms'][501] = [7];

        $result = ADPW_Category_Update_Start_Service::start([7], 0, null);

        self::assertSame(1, $result['job']['batch_size']);
        self::assertSame(1, $result['response']['total_entries']);
        self::assertSame('running', $result['response']['status']);
    }

    public function testExcelUpdateProductsBatchServiceCompletesImmediatelyForEmptyQueue(): void {
        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');
        $uploadedFile = tempnam(sys_get_temp_dir(), 'adpw-xlsx-');

        $job = [
            'batch_size' => 5,
            'product_cursor' => 0,
            'product_queue' => [],
            'categories_data_file' => $categoriesFile,
            'uploaded_file_path' => $uploadedFile,
        ];

        ADPW_Excel_Update_Products_Batch_Service::process($job);

        self::assertSame('completed', $job['status']);
        self::assertFileDoesNotExist($categoriesFile);
        self::assertFileDoesNotExist($uploadedFile);
    }
}
