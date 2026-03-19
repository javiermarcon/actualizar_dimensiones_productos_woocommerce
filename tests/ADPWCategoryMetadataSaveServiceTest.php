<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWCategoryMetadataSaveServiceTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

    public function testSaveFromRequestReturnsMessageWhenNothingChanged(): void {
        $result = ADPW_Category_Metadata_Save_Service::save_from_request([], [
            'actualizar_productos_desde_categorias' => 1,
            'categorias_por_lote' => 20,
        ]);

        self::assertSame('No hubo cambios para guardar.', $result['mensaje']);
    }

    public function testSaveFromRequestStartsBackgroundJobWhenConfigured(): void {
        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 90;
        $shippingTerm->slug = 'premium';
        $shippingTerm->name = 'Premium';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];
        $GLOBALS['adpw_test_posts'] = [888];
        $GLOBALS['adpw_test_post_terms'][888] = [12];

        $result = ADPW_Category_Metadata_Save_Service::save_from_request([
            12 => [
                'clase_envio' => 'premium',
                'peso' => '1',
            ],
        ], [
            'actualizar_productos_desde_categorias' => 1,
            'categorias_por_lote' => 5,
        ]);

        self::assertSame('Se actualizaron 1 categorías.', $result['mensaje']);
        self::assertStringContainsString('Job ID', $result['detalle_productos']);
        self::assertArrayHasKey('adpw_category_update_job', $GLOBALS['adpw_test_options']);
    }
}
