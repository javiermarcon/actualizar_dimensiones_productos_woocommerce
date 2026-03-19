<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWCategoryMetadataManagerTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

    public function testNormalizeNumberHandlesCommaAndNegativeValues(): void {
        self::assertSame('12.5', ADPW_Category_Metadata_Manager::normalize_number('12,5'));
        self::assertSame('0', ADPW_Category_Metadata_Manager::normalize_number('-3'));
        self::assertSame('', ADPW_Category_Metadata_Manager::normalize_number('abc'));
    }

    public function testSaveCategoryMetadataPersistsOnlyValidShippingClassAndNumericValues(): void {
        ADPW_Category_Metadata_Manager::save_category_metadata(10, [
            'clase_envio' => 'Clase Premium',
            'peso' => '1,5',
            'alto' => '',
            'ancho' => '2',
            'profundidad' => '3',
        ], ['clase-premium']);

        self::assertSame('clase-premium', $GLOBALS['adpw_test_term_meta'][10][ADPW_Category_Metadata_Manager::META_CLASS]);
        self::assertSame('1.5', $GLOBALS['adpw_test_term_meta'][10][ADPW_Category_Metadata_Manager::META_WEIGHT]);
        self::assertArrayNotHasKey(ADPW_Category_Metadata_Manager::META_HEIGHT, $GLOBALS['adpw_test_term_meta'][10]);
        self::assertSame('2', $GLOBALS['adpw_test_term_meta'][10][ADPW_Category_Metadata_Manager::META_WIDTH]);
        self::assertSame('3', $GLOBALS['adpw_test_term_meta'][10][ADPW_Category_Metadata_Manager::META_DEPTH]);
    }

    public function testBuildProductQueueForCategoriesChoosesDeepestCategory(): void {
        $GLOBALS['adpw_test_posts'] = [101];
        $GLOBALS['adpw_test_post_terms'][101] = [5, 7];
        $GLOBALS['adpw_test_ancestors'][5] = [1];
        $GLOBALS['adpw_test_ancestors'][7] = [1, 2, 3];

        $queue = ADPW_Category_Metadata_Manager::build_product_queue_for_categories([5, 7]);

        self::assertSame([[
            'product_id' => 101,
            'category_id' => 7,
        ]], $queue);
    }

    public function testProcessProductQueueBatchUpdatesProductAndCompletesJob(): void {
        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 44;
        $shippingTerm->slug = 'clase-x';
        $shippingTerm->name = 'Clase X';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];

        $GLOBALS['adpw_test_term_meta'][7] = [
            ADPW_Category_Metadata_Manager::META_CLASS => 'clase-x',
            ADPW_Category_Metadata_Manager::META_WEIGHT => '1.5',
            ADPW_Category_Metadata_Manager::META_HEIGHT => '2',
            ADPW_Category_Metadata_Manager::META_WIDTH => '3',
            ADPW_Category_Metadata_Manager::META_DEPTH => '4',
        ];

        $product = new WC_Product(501, 'Baul');
        $GLOBALS['adpw_test_products'][501] = $product;

        $job = [
            'batch_size' => 10,
            'product_cursor' => 0,
            'product_queue' => [[
                'product_id' => 501,
                'category_id' => 7,
            ]],
            'results' => [
                'totales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
        ];

        ADPW_Category_Metadata_Manager::process_product_queue_batch($job);

        self::assertSame('completed', $job['status']);
        self::assertSame(1, $job['results']['totales']);
        self::assertSame('1.5', $product->get_weight());
        self::assertSame('2', $product->get_height());
        self::assertSame('3', $product->get_width());
        self::assertSame('4', $product->get_length());
        self::assertSame(44, $product->get_shipping_class_id());
        self::assertSame(1, $product->get_save_count());
    }
}
