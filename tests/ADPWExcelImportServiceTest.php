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

        $GLOBALS['adpw_test_terms'] = [$term];

        $ids = $this->invokePrivateMethod(
            ADPW_Excel_Import_Service::class,
            'find_category_ids_by_name',
            ['Redes de Pulpo']
        );

        self::assertSame([99], $ids);
    }

    private function invokePrivateMethod(string $className, string $methodName, array $arguments) {
        $reflection = new ReflectionMethod($className, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $arguments);
    }
}
