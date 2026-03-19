<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWSettingsAndPagesTest extends TestCase {
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

    public function testSettingsGetMergesDefaultsWithSavedValues(): void {
        update_option('adpw_import_settings', [
            'actualizar_si' => 1,
        ]);

        $settings = ADPW_Settings::get();

        self::assertSame(1, $settings['actualizar_si']);
        self::assertSame(0, $settings['actualizar_tam']);
        self::assertSame(20, $settings['categorias_por_lote']);
    }

    public function testSettingsGetFallsBackToDefaultsWhenSavedOptionIsInvalid(): void {
        update_option('adpw_import_settings', 'corrupto');

        $settings = ADPW_Settings::get();

        self::assertSame(0, $settings['actualizar_si']);
        self::assertSame(20, $settings['categorias_por_lote']);
    }

    public function testSettingsHandleSaveRequestRejectsUsersWithoutPermissions(): void {
        $GLOBALS['adpw_test_current_user_can'] = false;

        $result = ADPW_Settings::handle_save_request('common');

        self::assertSame('No tenés permisos para guardar configuración.', $result['error']);
    }

    public function testSettingsHandleSaveRequestRejectsInvalidNonce(): void {
        $GLOBALS['adpw_test_verify_nonce'] = false;
        $_POST = [
            'adpw_settings_nonce' => 'bad',
        ];

        $result = ADPW_Settings::handle_save_request('common');

        self::assertSame('No se pudo validar la solicitud.', $result['error']);
    }

    public function testSettingsHandleSaveRequestUpdatesCommonBatchSize(): void {
        $_POST = [
            'adpw_settings_nonce' => 'nonce',
            'categorias_por_lote' => '0',
        ];

        $result = ADPW_Settings::handle_save_request('common');
        $settings = get_option('adpw_import_settings');

        self::assertSame('Configuración guardada correctamente.', $result['mensaje']);
        self::assertSame(1, $settings['categorias_por_lote']);
    }

    public function testSettingsHandleSaveRequestUpdatesImportFlags(): void {
        $_POST = [
            'adpw_settings_nonce' => 'nonce',
            'actualizar_si' => '1',
            'actualizar_cat' => '1',
        ];

        $result = ADPW_Settings::handle_save_request('import');
        $settings = get_option('adpw_import_settings');

        self::assertSame('Configuración guardada correctamente.', $result['mensaje']);
        self::assertSame(1, $settings['actualizar_si']);
        self::assertSame(0, $settings['actualizar_tam']);
        self::assertSame(1, $settings['actualizar_cat']);
    }

    public function testSettingsHandleSaveRequestUpdatesTreeFlag(): void {
        $_POST = [
            'adpw_settings_nonce' => 'nonce',
            'actualizar_productos_desde_categorias' => '1',
        ];

        $result = ADPW_Settings::handle_save_request('tree');
        $settings = get_option('adpw_import_settings');

        self::assertSame('Configuración guardada correctamente.', $result['mensaje']);
        self::assertSame(1, $settings['actualizar_productos_desde_categorias']);
    }

    public function testSettingsGetActiveTabFallsBackToCommonWhenTabIsInvalid(): void {
        $_REQUEST = [
            'tab' => 'invalido',
        ];

        $tab = $this->invokePrivateStaticMethod(ADPW_Settings::class, 'get_active_tab');

        self::assertSame('common', $tab);
    }

    public function testSettingsGetActiveTabFallsBackToPostedTabWhenRequestIsEmpty(): void {
        $_POST = [
            'adpw_settings_tab' => 'tree',
        ];

        $tab = $this->invokePrivateStaticMethod(ADPW_Settings::class, 'get_active_tab');

        self::assertSame('tree', $tab);
    }

    public function testSettingsRenderTabsOutputsAllNavigationLinks(): void {
        ob_start();
        ADPW_Settings_Page_Renderer::render_tabs('tree');
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Común', $html);
        self::assertStringContainsString('Importación Excel', $html);
        self::assertStringContainsString('Árbol de categorías', $html);
        self::assertStringContainsString('nav-tab-active', $html);
    }

    public function testExcelImportPageBuildRequestDebugIncludesFileMetadata(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'adpw_start_import_form_nonce' => 'nonce',
        ];
        $_FILES = [
            'archivo_excel' => [
                'name' => 'archivo.xlsx',
                'size' => 123,
                'error' => 0,
                'tmp_name' => '/tmp/php123',
            ],
        ];

        $lines = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Page::class, 'build_request_debug');

        self::assertContains('method=POST', $lines);
        self::assertContains('start_nonce_present=yes', $lines);
        self::assertContains('file.name=archivo.xlsx', $lines);
    }

    public function testCategoryMetadataPageHandleSaveRequestRejectsInvalidNonce(): void {
        $GLOBALS['adpw_test_verify_nonce'] = false;
        $_POST = [
            'guardar_metadata_por_categoria_nonce' => 'bad',
        ];

        $result = $this->invokePrivateStaticMethod(ADPW_Category_Metadata_Page::class, 'handle_save_request');

        self::assertSame('No se pudo validar la solicitud.', $result['error']);
    }

    public function testCategoryMetadataPageHandleSaveRequestReturnsNoChangesMessage(): void {
        $_POST = [
            'guardar_metadata_por_categoria_nonce' => 'nonce',
            'adpw_no_changes' => '1',
        ];

        $result = $this->invokePrivateStaticMethod(ADPW_Category_Metadata_Page::class, 'handle_save_request');

        self::assertSame('No hubo cambios para guardar.', $result['mensaje']);
    }

    public function testSettingsRenderPageShowsErrorNoticeWhenSaveFails(): void {
        $GLOBALS['adpw_test_current_user_can'] = false;
        $_REQUEST = [
            'tab' => 'common',
        ];
        $_POST = [
            'adpw_save_settings' => '1',
        ];

        ob_start();
        ADPW_Settings::render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('No tenés permisos para guardar configuración.', $html);
    }

    public function testExcelImportPageRenderShowsManualBatchErrorWhenNoJobExists(): void {
        $_POST = [
            'adpw_run_manual_batch' => '1',
            'adpw_manual_batch_nonce' => 'nonce',
        ];

        ob_start();
        ADPW_Excel_Import_Page::render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('No hay job en ejecución para procesar.', $html);
    }

    public function testCategoryMetadataPageRenderShowsErrorWhenTermsCannotBeLoaded(): void {
        $GLOBALS['adpw_test_terms_errors']['product_cat'] = new WP_Error();

        ob_start();
        ADPW_Category_Metadata_Page::render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('No se pudieron cargar categorías o clases de envío.', $html);
    }

    public function testCategoryMetadataPageRenderShowsSuccessNoticeForManualBatch(): void {
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

        $_POST = [
            'adpw_run_category_tree_batch' => '1',
            'adpw_category_tree_manual_batch_nonce' => 'nonce',
        ];

        ob_start();
        ADPW_Category_Metadata_Page::render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Se ejecutó manualmente un lote de actualización del árbol.', $html);
    }

    public function testCategoryMetadataPageHandleSaveRequestRejectsUsersWithoutPermissions(): void {
        $GLOBALS['adpw_test_current_user_can'] = false;
        $_POST = [
            'guardar_metadata_por_categoria_nonce' => 'nonce',
        ];

        $result = $this->invokePrivateStaticMethod(ADPW_Category_Metadata_Page::class, 'handle_save_request');

        self::assertSame('No tenés permisos para guardar metadata.', $result['error']);
    }

    public function testCategoryMetadataPageHandleSaveRequestDelegatesToSaveService(): void {
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
                    'peso' => '1',
                ],
            ],
        ];

        $result = $this->invokePrivateStaticMethod(ADPW_Category_Metadata_Page::class, 'handle_save_request');

        self::assertSame('Se actualizaron 1 categorías.', $result['mensaje']);
        self::assertSame('premium', $GLOBALS['adpw_test_term_meta'][12][ADPW_Category_Metadata_Manager::META_CLASS]);
    }

    public function testCategoryMetadataPageRenderCategoryRowsOutputsIndentedTreeAndFields(): void {
        $category = new WP_Term();
        $category->term_id = 12;
        $category->name = 'Cascos';
        $category->taxonomy = 'product_cat';

        $shippingClass = new WP_Term();
        $shippingClass->term_id = 90;
        $shippingClass->slug = 'premium';
        $shippingClass->name = 'Premium';
        $shippingClass->taxonomy = 'product_shipping_class';

        $GLOBALS['adpw_test_term_meta'][12] = [
            ADPW_Category_Metadata_Manager::META_CLASS => 'premium',
            ADPW_Category_Metadata_Manager::META_WEIGHT => '1',
            ADPW_Category_Metadata_Manager::META_HEIGHT => '2',
            ADPW_Category_Metadata_Manager::META_WIDTH => '3',
            ADPW_Category_Metadata_Manager::META_DEPTH => '4',
        ];

        ob_start();
        $this->invokePrivateStaticMethod(ADPW_Category_Metadata_Page::class, 'render_category_rows', [
            0,
            [0 => [$category]],
            [$shippingClass],
            1,
        ]);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Cascos', $html);
        self::assertStringContainsString('padding-left:20px', $html);
        self::assertStringContainsString('metadata_categoria[12][clase_envio]', $html);
        self::assertStringContainsString('option value="premium" selected="selected"', $html);
        self::assertStringContainsString('metadata_categoria[12][peso]', $html);
    }

    public function testCategoryMetadataPageRenderNumberCellOutputsConfiguredInput(): void {
        ob_start();
        $this->invokePrivateStaticMethod(ADPW_Category_Metadata_Page::class, 'render_number_cell', [
            12,
            'peso',
            '1.5',
        ]);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('type="number"', $html);
        self::assertStringContainsString('metadata_categoria[12][peso]', $html);
        self::assertStringContainsString('data-field="peso"', $html);
        self::assertStringContainsString('value="1.5"', $html);
    }

    public function testCategoryMetadataPageRenderPayloadScriptOutputsClientSideDeltaBuilder(): void {
        ob_start();
        $this->invokePrivateStaticMethod(ADPW_Category_Metadata_Page::class, 'render_payload_script');
        $html = (string) ob_get_clean();

        self::assertStringContainsString('adpw-category-metadata-form', $html);
        self::assertStringContainsString('adpw-metadata-delta-container', $html);
        self::assertStringContainsString('adpw_no_changes', $html);
    }

    private function invokePrivateStaticMethod(string $className, string $methodName, array $args = []) {
        $reflection = new ReflectionMethod($className, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }
}
