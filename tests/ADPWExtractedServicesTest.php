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

    private function invokePrivateStaticMethod(string $className, string $methodName, array $args = []) {
        $reflection = new ReflectionMethod($className, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }
}
