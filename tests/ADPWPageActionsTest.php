<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

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
}
