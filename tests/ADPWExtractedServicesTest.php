<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWExtractedServicesTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

    public function testImportJobSummaryBuildsParseStageProgress(): void {
        $summary = ADPW_Import_Job_Summary::build_summary([
            'status' => 'running',
            'stage' => 'parse_sheet',
            'processed_rows' => 5,
            'total_rows' => 10,
            'updated_at' => 10,
        ]);

        self::assertSame('Leyendo planilla y generando temporal', $summary['stage_label']);
        self::assertSame(16, $summary['progress']);
    }

    public function testQueueBuilderPrivatePickDeepestCategoryUsesAncestorDepth(): void {
        $depthCache = [];
        $GLOBALS['adpw_test_ancestors'][7] = [1];
        $GLOBALS['adpw_test_ancestors'][9] = [1, 2, 3];

        $selected = $this->invokePrivateStaticMethod(ADPW_Category_Product_Queue_Builder::class, 'pick_deepest_category', [
            [7, 9],
            &$depthCache,
        ]);

        self::assertSame(9, $selected);
        self::assertSame(3, $depthCache[9]);
    }

    public function testQueueBuilderBuildsQueueUsingDeepestMatchingCategory(): void {
        $GLOBALS['adpw_test_posts'] = [101];
        $GLOBALS['adpw_test_post_terms'][101] = [7, 9, 99];
        $GLOBALS['adpw_test_ancestors'][7] = [1];
        $GLOBALS['adpw_test_ancestors'][9] = [1, 2, 3];

        $queue = ADPW_Category_Product_Queue_Builder::build_product_queue_for_categories([7, 9]);

        self::assertSame([[
            'product_id' => 101,
            'category_id' => 9,
        ]], $queue);
    }

    public function testQueueBuilderReturnsEmptyQueueWhenProductsHaveNoMatchingCategories(): void {
        $GLOBALS['adpw_test_posts'] = [101];
        $GLOBALS['adpw_test_post_terms'][101] = [50];

        $queue = ADPW_Category_Product_Queue_Builder::build_product_queue_for_categories([7, 9]);

        self::assertSame([], $queue);
    }

    public function testQueueBuilderSkipsProductsWhenPostTermsReturnErrorOrOnlyInvalidCandidateIds(): void {
        $GLOBALS['adpw_test_posts'] = [101, 102];
        $GLOBALS['adpw_test_post_terms_errors'][101] = new WP_Error();
        $GLOBALS['adpw_test_post_terms'][102] = [0];

        $queue = ADPW_Category_Product_Queue_Builder::build_product_queue_for_categories([0, 7]);

        self::assertSame([], $queue);
    }

    public function testProductMetadataApplierReturnsZeroWhenQueueDoesNotChangeProducts(): void {
        $GLOBALS['adpw_test_posts'] = [101];
        $GLOBALS['adpw_test_post_terms'][101] = [7];
        $GLOBALS['adpw_test_term_meta'][7] = [];

        $product = new WC_Product(101, 'Baul');
        $GLOBALS['adpw_test_products'][101] = $product;

        $updated = ADPW_Category_Product_Metadata_Applier::update_products_using_most_specific_category([7]);

        self::assertSame(0, $updated);
        self::assertSame(0, $product->get_save_count());
    }

    public function testProductMetadataApplierProcessQueueBatchCompletesWhenCursorAlreadyFinished(): void {
        $job = [
            'status' => 'running',
            'batch_size' => 2,
            'product_cursor' => 1,
            'product_queue' => [['product_id' => 101, 'category_id' => 7]],
        ];

        ADPW_Category_Product_Metadata_Applier::process_product_queue_batch($job);

        self::assertSame('completed', $job['status']);
    }

    public function testProductMetadataApplierProcessQueueBatchInitializesRuntimeAndSkipsInvalidItems(): void {
        $product = new WC_Product(501, 'Baul');
        $GLOBALS['adpw_test_products'][501] = $product;
        $GLOBALS['adpw_test_term_meta'][7] = [
            ADPW_Category_Metadata_Manager::META_WEIGHT => '2.5',
        ];

        $job = [
            'status' => 'running',
            'batch_size' => 2,
            'product_cursor' => 0,
            'product_queue' => [
                ['product_id' => 0, 'category_id' => 7],
                ['product_id' => 501, 'category_id' => 7],
            ],
            'results' => [
                'totales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
        ];

        ADPW_Category_Product_Metadata_Applier::process_product_queue_batch($job);

        self::assertSame('completed', $job['status']);
        self::assertSame(2, $job['product_cursor']);
        self::assertSame('2.5', $product->get_weight());
        self::assertSame(1, $job['results']['totales']);
        self::assertArrayHasKey('shipping_class_cache', $job['runtime']);
    }

    public function testProductMetadataApplierPrivateHelpersCoverNoChangeAndMissingShippingClass(): void {
        $product = new WC_Product(501, 'Baul');
        $product->set_weight(1.5);
        $shippingCache = [];
        $messages = ['uno'];

        $sameWeight = $this->invokePrivateStaticMethod(ADPW_Category_Product_Metadata_Applier::class, 'set_product_numeric_if_needed', [
            $product,
            'weight',
            '1.5',
        ]);
        $emptyShippingClass = $this->invokePrivateStaticMethod(ADPW_Category_Product_Metadata_Applier::class, 'resolve_shipping_class_id', [
            '',
            &$shippingCache,
        ]);
        $missingShippingClass = $this->invokePrivateStaticMethod(ADPW_Category_Product_Metadata_Applier::class, 'resolve_shipping_class_id', [
            'premium',
            &$shippingCache,
        ]);
        $this->invokePrivateStaticMethod(ADPW_Category_Product_Metadata_Applier::class, 'append_limited', [
            &$messages,
            'dos',
            1,
        ]);

        self::assertFalse($sameWeight);
        self::assertSame(0, $emptyShippingClass);
        self::assertSame(0, $missingShippingClass);
        self::assertSame(['uno'], $messages);
    }

    public function testProductMetadataApplierAppendLimitedInitializesMissingTargetArray(): void {
        $messages = null;

        $this->invokePrivateStaticMethod(ADPW_Category_Product_Metadata_Applier::class, 'append_limited', [
            &$messages,
            'uno',
            2,
        ]);

        self::assertSame(['uno'], $messages);
    }

    private function invokePrivateStaticMethod(string $className, string $methodName, array $args = []) {
        $reflection = new ReflectionMethod($className, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }
}
