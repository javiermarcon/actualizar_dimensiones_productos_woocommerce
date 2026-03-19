<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ADPWExcelImportServiceTest extends TestCase {
    protected function tearDown(): void {
        adpw_test_reset_wp_stubs();
    }

    public function testNormalizeHeaderRemovesAccentsAndNormalizesSpacing(): void {
        $normalized = $this->invokePrivateMethod(
            ADPW_Excel_Import_Service::class,
            'normalize_header',
            ["  Tamaño_-  Grande \t"]
        );

        self::assertSame('tamano grande', $normalized);
    }

    public function testFindHeaderIndexAcceptsKnownVariants(): void {
        $headers = [
            1 => 'categoria',
            2 => 'id categoria',
            3 => 'tamano',
        ];

        $index = $this->invokePrivateMethod(
            ADPW_Excel_Import_Service::class,
            'find_header_index',
            [$headers, ['tamaño', 'size']]
        );

        self::assertSame(3, $index);
    }

    public function testFindCategoryIdsByNameFallsBackToFlexibleMatch(): void {
        $term = new WP_Term();
        $term->term_id = 99;
        $term->name = 'Redes de Pulpo para motos';
        $term->slug = 'redes-de-pulpo-para-motos';
        $term->taxonomy = 'product_cat';

        $GLOBALS['adpw_test_terms'] = [$term];

        $ids = $this->invokePrivateMethod(
            ADPW_Excel_Import_Service::class,
            'find_category_ids_by_name',
            ['Redes de Pulpo']
        );

        self::assertSame([99], $ids);
    }

    public function testValidateHeadersReturnsWarningWhenSizeColumnIsMissing(): void {
        $result = $this->invokePrivateMethod(
            ADPW_Excel_Import_Service::class,
            'validate_headers',
            [[
                'categoria' => 1,
                'largo' => 2,
                'ancho' => false,
                'profundidad' => false,
                'peso' => false,
                'idcat' => false,
                'tamano' => false,
            ], [1 => 'categoria', 2 => 'largo (cm)'], false, true, false, false]
        );

        self::assertArrayHasKey('warnings', $result);
        self::assertCount(1, $result['warnings']);
    }

    public function testUpdateShippingClassReturnsErrorWhenClassDoesNotExist(): void {
        $product = new WC_Product(55, 'Casco');

        $result = $this->invokePrivateMethod(
            ADPW_Excel_Import_Service::class,
            'update_shipping_class',
            [$product, 'fantasma']
        );

        self::assertFalse($result['modificado']);
        self::assertStringContainsString('no encontrada', $result['detalles_errores']);
    }

    public function testUpdateDimensionsUpdatesOnlyMissingFieldsWhenActualizarSiempreIsFalse(): void {
        $product = new WC_Product(77, 'Parrilla');
        $product->set_weight(1.2);

        $result = $this->invokePrivateMethod(
            ADPW_Excel_Import_Service::class,
            'update_dimensions',
            [$product, [
                'peso' => 9.5,
                'largo' => 10.0,
                'ancho' => 20.0,
                'profundidad' => 30.0,
            ], false, 'Accesorios', 77]
        );

        self::assertTrue($result['modificado']);
        self::assertSame('1.2', $product->get_weight());
        self::assertSame('10', $product->get_length());
        self::assertSame('20', $product->get_width());
        self::assertSame('30', $product->get_height());
    }

    private function invokePrivateMethod(string $className, string $methodName, array $arguments) {
        $reflection = new ReflectionMethod($className, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $arguments);
    }
}
