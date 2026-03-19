<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWCategoryMetadataManagerTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

    public function testGetCategoryMetaValuesReturnsStoredMetadata(): void {
        $GLOBALS['adpw_test_term_meta'][10] = [
            ADPW_Category_Metadata_Manager::META_CLASS => 'premium',
            ADPW_Category_Metadata_Manager::META_WEIGHT => '1.5',
            ADPW_Category_Metadata_Manager::META_HEIGHT => '2',
            ADPW_Category_Metadata_Manager::META_WIDTH => '3',
            ADPW_Category_Metadata_Manager::META_DEPTH => '4',
        ];

        $values = ADPW_Category_Metadata_Manager::get_category_meta_values(10);

        self::assertSame([
            'clase_envio' => 'premium',
            'peso' => '1.5',
            'alto' => '2',
            'ancho' => '3',
            'profundidad' => '4',
        ], $values);
    }

    public function testGetValidShippingSlugsReturnsSlugsAndNullOnError(): void {
        $term = new WP_Term();
        $term->term_id = 44;
        $term->slug = 'clase-x';
        $term->name = 'Clase X';
        $term->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$term];

        self::assertSame(['clase-x'], ADPW_Category_Metadata_Manager::get_valid_shipping_slugs());

        $GLOBALS['adpw_test_terms_errors']['product_shipping_class'] = new WP_Error();

        self::assertNull(ADPW_Category_Metadata_Manager::get_valid_shipping_slugs());
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

    public function testSaveCategoryMetadataDeletesEmptyShippingClass(): void {
        $GLOBALS['adpw_test_term_meta'][10] = [
            ADPW_Category_Metadata_Manager::META_CLASS => 'premium',
        ];

        ADPW_Category_Metadata_Manager::save_category_metadata(10, [
            'clase_envio' => '',
        ], ['premium']);

        self::assertArrayNotHasKey(ADPW_Category_Metadata_Manager::META_CLASS, $GLOBALS['adpw_test_term_meta'][10]);
    }

    public function testUpdateProductsUsingMostSpecificCategoryCountsUpdatedProducts(): void {
        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 44;
        $shippingTerm->slug = 'clase-x';
        $shippingTerm->name = 'Clase X';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];

        $GLOBALS['adpw_test_posts'] = [101];
        $GLOBALS['adpw_test_post_terms'][101] = [7];
        $GLOBALS['adpw_test_term_meta'][7] = [
            ADPW_Category_Metadata_Manager::META_CLASS => 'clase-x',
            ADPW_Category_Metadata_Manager::META_WEIGHT => '1.5',
        ];

        $product = new WC_Product(101, 'Baul');
        $GLOBALS['adpw_test_products'][101] = $product;

        $updated = ADPW_Category_Metadata_Manager::update_products_using_most_specific_category([7]);

        self::assertSame(1, $updated);
        self::assertSame('1.5', $product->get_weight());
        self::assertSame(44, $product->get_shipping_class_id());
    }

    public function testBuildProductQueueForCategoriesReturnsEmptyForEmptyInput(): void {
        self::assertSame([], ADPW_Category_Metadata_Manager::build_product_queue_for_categories([]));
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

    public function testProcessProductQueueBatchRegistersErrorWhenProductCannotBeLoaded(): void {
        $job = [
            'batch_size' => 10,
            'product_cursor' => 0,
            'product_queue' => [[
                'product_id' => 999,
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
        self::assertSame(1, $job['results']['errores']);
        self::assertStringContainsString('No se pudo cargar el producto con ID 999', $job['results']['detalles'][0]);
    }

    public function testPrivateHelpersHandleNoChangeAndLimits(): void {
        $product = new WC_Product(500, 'Baul');
        $product->set_weight(1.5);
        $shippingCache = ['premium' => 44];
        $messages = ['uno'];

        $sameWeight = $this->invokePrivateStaticMethod(ADPW_Category_Metadata_Manager::class, 'set_product_numeric_if_needed', [
            $product,
            'weight',
            '1.5',
        ]);
        $resolved = $this->invokePrivateStaticMethod(ADPW_Category_Metadata_Manager::class, 'resolve_shipping_class_id', [
            'premium',
            &$shippingCache,
        ]);
        $this->invokePrivateStaticMethod(ADPW_Category_Metadata_Manager::class, 'append_limited', [
            &$messages,
            'dos',
            1,
        ]);

        self::assertFalse($sameWeight);
        self::assertSame(44, $resolved);
        self::assertSame(['uno'], $messages);
    }

    private function invokePrivateStaticMethod(string $className, string $methodName, array $args = []) {
        $reflection = new ReflectionMethod($className, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }
}
