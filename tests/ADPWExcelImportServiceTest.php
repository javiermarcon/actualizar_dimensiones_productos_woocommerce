<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class ADPWExcelImportServiceTest extends TestCase {
    protected function tearDown(): void {
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Support::class, 'product_category_lookup', null);
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_validator', null);
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_mover', null);
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', null);
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'mkdir_p_callback', null);
        adpw_test_reset_wp_stubs();
    }

    public function testNormalizeHeaderRemovesAccentsAndNormalizesSpacing(): void {
        $normalized = ADPW_Excel_Import_Support::normalize_header("  Tamaño_-  Grande \t");

        self::assertSame('tamano grande', $normalized);
    }

    public function testFindHeaderIndexAcceptsKnownVariants(): void {
        $headers = [
            1 => 'categoria',
            2 => 'id categoria',
            3 => 'tamano',
        ];

        $index = ADPW_Excel_Import_Support::find_header_index($headers, ['tamaño', 'size']);

        self::assertSame(3, $index);
    }

    public function testBuildColumnsDetectsSupportedAliases(): void {
        $columns = ADPW_Excel_Import_Support::build_columns([
            1 => 'categoria',
            2 => 'longitud (cm)',
            3 => 'ancho(cm)',
            4 => 'alto',
            5 => 'peso',
            6 => 'id categoria woocommerce',
            7 => 'talle',
        ]);

        self::assertSame(1, $columns['categoria']);
        self::assertSame(2, $columns['largo']);
        self::assertSame(3, $columns['ancho']);
        self::assertSame(4, $columns['profundidad']);
        self::assertSame(5, $columns['peso']);
        self::assertSame(6, $columns['idcat']);
        self::assertSame(7, $columns['tamano']);
    }

    public function testFindHeaderIndexReturnsFalseWhenNoCandidateMatches(): void {
        $index = ADPW_Excel_Import_Support::find_header_index([
            1 => 'sku',
            2 => 'descripcion',
        ], ['categoria', 'tamaño']);

        self::assertFalse($index);
    }

    public function testFindHeaderIndexSkipsEmptyHeaders(): void {
        $index = ADPW_Excel_Import_Support::find_header_index([
            1 => '',
            2 => 'descripcion larga',
        ], ['categoria']);

        self::assertFalse($index);
    }

    public function testFindHeaderIndexSkipsEmptyNormalizedCandidates(): void {
        $index = ADPW_Excel_Import_Support::find_header_index([
            1 => 'sku',
            2 => 'descripcion larga',
        ], ['___']);

        self::assertFalse($index);
    }

    public function testGetHeadersNormalizesSpreadsheetHeaderRow(): void {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', ' Categoría ');
        $sheet->setCellValue('B1', 'Tamaño');
        $sheet->setCellValue('C1', 'Peso (kg)');

        $headers = ADPW_Excel_Import_Support::get_headers($sheet);

        self::assertSame('categoria', $headers[1]);
        self::assertSame('tamano', $headers[2]);
        self::assertSame('peso (kg)', $headers[3]);
    }

    public function testFindCategoryIdsByNameFallsBackToFlexibleMatch(): void {
        $term = new WP_Term();
        $term->term_id = 99;
        $term->name = 'Redes de Pulpo para motos';
        $term->slug = 'redes-de-pulpo-para-motos';
        $term->taxonomy = 'product_cat';

        $GLOBALS['adpw_test_terms'] = [$term];

        $ids = ADPW_Excel_Import_Support::find_category_ids_by_name('Redes de Pulpo');

        self::assertSame([99], $ids);
    }

    public function testFindCategoryIdsByNameReturnsUniqueIdsForExactMatch(): void {
        $termOne = new WP_Term();
        $termOne->term_id = 20;
        $termOne->name = 'Baules';
        $termOne->slug = 'baules';
        $termOne->taxonomy = 'product_cat';

        $termTwo = new WP_Term();
        $termTwo->term_id = 20;
        $termTwo->name = 'Baules';
        $termTwo->slug = 'baules-duplicado';
        $termTwo->taxonomy = 'product_cat';

        $GLOBALS['adpw_test_terms'] = [$termOne, $termTwo];

        $ids = ADPW_Excel_Import_Support::find_category_ids_by_name('Baules');

        self::assertSame([20], $ids);
    }

    public function testFindCategoryIdsByNameReturnsEmptyArrayWhenCategoriesCannotBeLoaded(): void {
        $GLOBALS['adpw_test_terms_errors']['product_cat'] = new WP_Error();

        $ids = ADPW_Excel_Import_Support::find_category_ids_by_name('Baules');

        self::assertSame([], $ids);
    }

    public function testFindCategoryIdsByNameReturnsEmptyArrayForBlankName(): void {
        $ids = ADPW_Excel_Import_Support::find_category_ids_by_name('   ');

        self::assertSame([], $ids);
    }

    public function testSupportPrivateHelpersNormalizeAndBuildLookup(): void {
        $term = new WP_Term();
        $term->term_id = 20;
        $term->name = 'Baúles Grandes';
        $term->slug = 'baules-grandes';
        $term->taxonomy = 'product_cat';
        $GLOBALS['adpw_test_terms'] = [$term];

        $normalized = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Support::class, 'normalize_category_name', [' Baúles Grandes ']);
        $lookup = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Support::class, 'get_product_category_lookup');

        self::assertSame('baules grandes', $normalized);
        self::assertSame([20], $lookup['exact']['baules grandes']);
        self::assertSame(20, $lookup['entries'][0]['term_id']);
    }

    public function testSupportPrivateLookupReturnsCachedValueWithoutReloadingTerms(): void {
        $cachedLookup = [
            'exact' => ['baules' => [20]],
            'entries' => [
                [
                    'term_id' => 20,
                    'normalized_name' => 'baules',
                ],
            ],
        ];
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Support::class, 'product_category_lookup', $cachedLookup);
        $GLOBALS['adpw_test_terms_errors']['product_cat'] = new WP_Error();

        $lookup = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Support::class, 'get_product_category_lookup');

        self::assertSame($cachedLookup, $lookup);
    }

    public function testSupportPrivateLookupSkipsInvalidTermsAndKeepsOnlyValidEntries(): void {
        $invalidMissingName = new stdClass();
        $invalidMissingName->term_id = 15;

        $invalidEmptyName = new WP_Term();
        $invalidEmptyName->term_id = 0;
        $invalidEmptyName->name = '';
        $invalidEmptyName->taxonomy = 'product_cat';

        $valid = new WP_Term();
        $valid->term_id = 25;
        $valid->name = 'Baules';
        $valid->slug = 'baules';
        $valid->taxonomy = 'product_cat';

        $GLOBALS['adpw_test_terms'] = [$invalidMissingName, $invalidEmptyName, $valid];

        $lookup = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Support::class, 'get_product_category_lookup');

        self::assertSame([25], $lookup['exact']['baules']);
        self::assertCount(1, $lookup['entries']);
    }

    public function testValidateHeadersReturnsWarningWhenSizeColumnIsMissing(): void {
        $result = ADPW_Excel_Import_Support::validate_headers([
            'categoria' => 1,
            'largo' => 2,
            'ancho' => false,
            'profundidad' => false,
            'peso' => false,
            'idcat' => false,
            'tamano' => false,
        ], [1 => 'categoria', 2 => 'largo (cm)'], false, true, false, false);

        self::assertArrayHasKey('warnings', $result);
        self::assertCount(1, $result['warnings']);
    }

    public function testValidateHeadersReturnsErrorWhenRequiredHeadersAreMissing(): void {
        $result = ADPW_Excel_Import_Support::validate_headers([
            'categoria' => false,
            'largo' => false,
            'ancho' => false,
            'profundidad' => false,
            'peso' => false,
            'idcat' => false,
            'tamano' => false,
        ], [1 => 'sku', 2 => 'descripcion'], true, false, false, true);

        self::assertSame('No se encontraron los encabezados esperados en el archivo Excel.', $result['error_general']);
        self::assertStringContainsString('Categoría', $result['detalles'][0]);
        self::assertStringContainsString('sku, descripcion', $result['detalles'][1]);
    }

    public function testBuildModeDetectsOnlySizeImport(): void {
        $result = ADPW_Excel_Import_Support::build_mode([
            'actualizar_tam' => 1,
        ], [
            'largo' => false,
            'ancho' => false,
            'profundidad' => false,
            'peso' => false,
            'tamano' => 4,
        ]);

        self::assertTrue($result['faltan_dimensiones']);
        self::assertTrue($result['mode']['solo_tamano_desde_excel']);
        self::assertFalse($result['mode']['actualizar_tam_dimensiones']);
    }

    public function testResolveCategoryIdsForParseUsesIdFallback(): void {
        $term = new WP_Term();
        $term->term_id = 50;
        $term->name = 'Otra categoria';
        $term->slug = 'otra-categoria';
        $term->taxonomy = 'product_cat';
        $GLOBALS['adpw_test_terms'] = [$term];
        $errors = [];
        $nameCache = [];
        $idCache = [];

        $ids = ADPW_Excel_Import_Support::resolve_category_ids_for_parse('', 50, 2, $errors, $nameCache, $idCache);

        self::assertSame([50], $ids);
        self::assertSame([], $errors);
    }

    public function testResolveCategoryIdsForParseRecordsErrorWhenCategoryDoesNotExist(): void {
        $errors = [];
        $nameCache = [];
        $idCache = [];

        $ids = ADPW_Excel_Import_Support::resolve_category_ids_for_parse('Sin Match', 0, 8, $errors, $nameCache, $idCache);

        self::assertSame([], $ids);
        self::assertCount(1, $errors);
        self::assertStringContainsString("Fila 8: No se encontró la categoría 'Sin Match'.", $errors[0]);
    }

    public function testResolveCategoryIdsForParseUsesCachedNameAndIdLookups(): void {
        $errors = [];
        $nameCache = ['baules' => [17]];
        $idCache = [17 => 17];

        $ids = ADPW_Excel_Import_Support::resolve_category_ids_for_parse('Baules', 17, 3, $errors, $nameCache, $idCache);

        self::assertSame([17], $ids);
        self::assertSame([], $errors);
    }

    public function testUpdateShippingClassReturnsErrorWhenClassDoesNotExist(): void {
        $product = new WC_Product(55, 'Casco');

        $result = ADPW_Excel_Product_Update_Service::update_shipping_class($product, 'fantasma');

        self::assertFalse($result['modificado']);
        self::assertStringContainsString('no encontrada', $result['detalles_errores']);
    }

    public function testUpdateShippingClassReturnsNoChangeWhenClassIsAlreadyAssigned(): void {
        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 61;
        $shippingTerm->name = 'Premium';
        $shippingTerm->slug = 'premium';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];

        $product = new WC_Product(56, 'Casco');
        $product->set_shipping_class_id(61);

        $result = ADPW_Excel_Product_Update_Service::update_shipping_class($product, 'premium');

        self::assertFalse($result['modificado']);
        self::assertSame('', $result['detalles_errores']);
        self::assertSame('', $result['log_producto']);
    }

    public function testUpdateShippingClassFindsTermByName(): void {
        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 62;
        $shippingTerm->name = 'Clase Premium';
        $shippingTerm->slug = 'clase-premium';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];

        $product = new WC_Product(57, 'Casco');
        $product->set_shipping_class('base');

        $result = ADPW_Excel_Product_Update_Service::update_shipping_class($product, 'Clase Premium');

        self::assertTrue($result['modificado']);
        self::assertSame(62, $product->get_shipping_class_id());
        self::assertStringContainsString('Nueva clase: Clase Premium', $result['log_producto']);
    }

    public function testUpdateDimensionsUpdatesOnlyMissingFieldsWhenActualizarSiempreIsFalse(): void {
        $product = new WC_Product(77, 'Parrilla');
        $product->set_weight(1.2);

        $result = ADPW_Excel_Product_Update_Service::update_dimensions($product, [
            'peso' => 9.5,
            'largo' => 10.0,
            'ancho' => 20.0,
            'profundidad' => 30.0,
        ], false, 'Accesorios', 77);

        self::assertTrue($result['modificado']);
        self::assertSame('1.2', $product->get_weight());
        self::assertSame('10', $product->get_length());
        self::assertSame('20', $product->get_width());
        self::assertSame('30', $product->get_height());
    }

    public function testUpdateDimensionsReturnsNoChangesWhenIncomingValuesAreEmpty(): void {
        $product = new WC_Product(78, 'Parrilla');
        $product->set_weight(1.2);
        $product->set_length(2);
        $product->set_width(3);
        $product->set_height(4);

        $result = ADPW_Excel_Product_Update_Service::update_dimensions($product, [
            'peso' => 0,
            'largo' => 0,
            'ancho' => 0,
            'profundidad' => 0,
        ], true, 'Accesorios', 78);

        self::assertFalse($result['modificado']);
        self::assertTrue($result['actualizacion_total']);
        self::assertStringContainsString('Actualizando el producto', $result['log_producto']);
        self::assertSame(0, $product->get_save_count());
    }

    public function testProcessProductUpdateRecordsErrorWhenProductCannotBeLoaded(): void {
        $results = [
            'totales' => 0,
            'parciales' => 0,
            'errores' => 0,
            'detalles' => [],
            'productos_modificados' => [],
        ];

        ADPW_Excel_Product_Update_Service::process_product_update(999, [
            'categoria' => 'Viaje',
            'tamano' => '',
            'peso' => 0,
            'largo' => 0,
            'ancho' => 0,
            'profundidad' => 0,
        ], [
            'actualizar_si' => 1,
            'actualizar_cat' => 0,
        ], [
            'actualizar_tam_dimensiones' => true,
            'solo_tamano_desde_excel' => false,
        ], $results);

        self::assertSame(1, $results['errores']);
        self::assertStringContainsString('No se pudo cargar el producto con ID 999', $results['detalles'][0]);
    }

    public function testProcessProductUpdateRecordsDimensionAndShippingChanges(): void {
        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 61;
        $shippingTerm->name = 'Premium';
        $shippingTerm->slug = 'premium';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];

        $product = new WC_Product(200, 'Valija');
        $product->set_shipping_class('base');
        $GLOBALS['adpw_test_products'][200] = $product;
        $results = [
            'totales' => 0,
            'parciales' => 0,
            'errores' => 0,
            'detalles' => [],
            'productos_modificados' => [],
        ];

        ADPW_Excel_Product_Update_Service::process_product_update(200, [
            'categoria' => 'Viaje',
            'tamano' => 'premium',
            'peso' => 1.1,
            'largo' => 2.2,
            'ancho' => 3.3,
            'profundidad' => 4.4,
        ], [
            'actualizar_si' => 1,
            'actualizar_cat' => 1,
        ], [
            'actualizar_tam_dimensiones' => true,
            'solo_tamano_desde_excel' => false,
        ], $results);

        self::assertGreaterThan(0, $results['totales']);
        self::assertSame(61, $product->get_shipping_class_id());
        self::assertNotEmpty($results['detalles']);
        self::assertNotEmpty($results['productos_modificados']);
    }

    public function testProcessProductUpdateRecordsPartialDimensionUpdateAndShippingError(): void {
        $product = new WC_Product(201, 'Valija');
        $product->set_weight(1.5);
        $product->set_length(2.5);
        $product->set_width(3.5);
        $GLOBALS['adpw_test_products'][201] = $product;

        $results = [
            'totales' => 0,
            'parciales' => 0,
            'errores' => 0,
            'detalles' => [],
            'productos_modificados' => [],
        ];

        ADPW_Excel_Product_Update_Service::process_product_update(201, [
            'categoria' => 'Viaje',
            'tamano' => 'fantasma',
            'peso' => 0,
            'largo' => 0,
            'ancho' => 0,
            'profundidad' => 4.4,
        ], [
            'actualizar_si' => 0,
            'actualizar_cat' => 1,
        ], [
            'actualizar_tam_dimensiones' => true,
            'solo_tamano_desde_excel' => false,
        ], $results);

        self::assertSame(1, $results['parciales']);
        self::assertSame(1, $results['totales']);
        self::assertStringContainsString('Clase de envío', $results['detalles'][0]);
        self::assertSame('4.4', $product->get_height());
    }

    public function testReadEntryFromSheetExtractsTypedValues(): void {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A2', 'Baules');
        $sheet->setCellValue('B2', '17');
        $sheet->setCellValue('C2', '10.5');
        $sheet->setCellValue('D2', '20');
        $sheet->setCellValue('E2', '30');
        $sheet->setCellValue('F2', '40');
        $sheet->setCellValue('G2', 'premium');

        $entry = ADPW_Excel_Parse_Sheet_Batch_Service::read_entry_from_sheet($sheet, [
            'categoria' => 1,
            'idcat' => 2,
            'largo' => 3,
            'ancho' => 4,
            'profundidad' => 5,
            'peso' => 6,
            'tamano' => 7,
        ], 2);

        self::assertSame(2, $entry['row']);
        self::assertSame('Baules', $entry['categoria']);
        self::assertSame(17, $entry['idcat']);
        self::assertSame(10.5, $entry['largo']);
        self::assertSame(20.0, $entry['ancho']);
        self::assertSame(30.0, $entry['profundidad']);
        self::assertSame(40.0, $entry['peso']);
        self::assertSame('premium', $entry['tamano']);
    }

    public function testInitializeJobBuildsJobDataForValidSpreadsheet(): void {
        $sourceFile = $this->createSpreadsheetFile([
            ['Categoria', 'Peso (kg)', 'Tamaño'],
            ['Baules', 10, 'premium'],
        ]);
        $targetRoot = sys_get_temp_dir() . '/adpw-tests-' . uniqid('', true);
        mkdir($targetRoot);

        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_validator', static function ($path): bool {
            return is_string($path) && $path !== '';
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_mover', static function ($source, $destination): bool {
            return copy((string) $source, (string) $destination);
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', static function () use ($targetRoot): array {
            return [
                'basedir' => $targetRoot,
                'error' => '',
            ];
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'mkdir_p_callback', static function ($target): bool {
            return is_dir((string) $target) || mkdir((string) $target, 0777, true);
        });

        $job = ADPW_Excel_Import_Service::initialize_job([
            'tmp_name' => $sourceFile,
            'name' => 'import.xlsx',
        ], [
            'actualizar_tam' => 1,
            'actualizar_cat' => 0,
        ], 7);

        self::assertSame(7, $job['batch_size']);
        self::assertSame(2, $job['highest_row']);
        self::assertSame(1, $job['total_rows']);
        self::assertSame(1, $job['columns']['categoria']);
        self::assertTrue($job['mode']['actualizar_tam_dimensiones']);
        self::assertFileExists($job['uploaded_file_path']);
        self::assertFileExists($job['categories_data_file']);

        @unlink($job['uploaded_file_path']);
        @unlink($job['categories_data_file']);
        @unlink($sourceFile);
        @rmdir($targetRoot . '/adpw-imports');
        @rmdir($targetRoot);
    }

    public function testInitializeJobUsesDefaultXlsxExtensionWhenFileNameHasNoExtension(): void {
        $sourceFile = $this->createSpreadsheetFile([
            ['Categoria', 'Peso (kg)'],
            ['Baules', 10],
        ]);
        $targetRoot = sys_get_temp_dir() . '/adpw-tests-' . uniqid('', true);
        mkdir($targetRoot);

        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_validator', static function (): bool {
            return true;
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_mover', static function ($source, $destination): bool {
            return copy((string) $source, (string) $destination);
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', static function () use ($targetRoot): array {
            return [
                'basedir' => $targetRoot,
                'error' => '',
            ];
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'mkdir_p_callback', static function ($target): bool {
            return is_dir((string) $target) || mkdir((string) $target, 0777, true);
        });

        $job = ADPW_Excel_Import_Service::initialize_job([
            'tmp_name' => $sourceFile,
            'name' => 'importacion',
        ], [
            'actualizar_tam' => 1,
            'actualizar_cat' => 0,
        ], 5);

        self::assertStringEndsWith('.xlsx', $job['uploaded_file_path']);

        @unlink($job['uploaded_file_path']);
        @unlink($job['categories_data_file']);
        @unlink($sourceFile);
        @rmdir($targetRoot . '/adpw-imports');
        @rmdir($targetRoot);
    }

    public function testInitializeJobReturnsUploadDirectoryError(): void {
        $sourceFile = $this->createSpreadsheetFile([
            ['Categoria'],
            ['Baules'],
        ]);

        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_validator', static function (): bool {
            return true;
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', static function (): array {
            return [
                'basedir' => sys_get_temp_dir(),
                'error' => 'fallo',
            ];
        });

        $result = ADPW_Excel_Import_Service::initialize_job([
            'tmp_name' => $sourceFile,
            'name' => 'import.xlsx',
        ], [], 5);

        self::assertSame('No se pudo acceder al directorio de uploads.', $result['error_general']);
        @unlink($sourceFile);
    }

    public function testInitializeJobReturnsDirectoryCreationError(): void {
        $sourceFile = $this->createSpreadsheetFile([
            ['Categoria'],
            ['Baules'],
        ]);

        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_validator', static function (): bool {
            return true;
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', static function (): array {
            return [
                'basedir' => sys_get_temp_dir(),
                'error' => '',
            ];
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'mkdir_p_callback', static function (): bool {
            return false;
        });

        $result = ADPW_Excel_Import_Service::initialize_job([
            'tmp_name' => $sourceFile,
            'name' => 'import.xlsx',
        ], [], 5);

        self::assertSame('No se pudo crear el directorio temporal de importación.', $result['error_general']);
        @unlink($sourceFile);
    }

    public function testInitializeJobReturnsMoveErrorWhenFileCannotBeMoved(): void {
        $sourceFile = $this->createSpreadsheetFile([
            ['Categoria'],
            ['Baules'],
        ]);

        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_validator', static function (): bool {
            return true;
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_mover', static function (): bool {
            return false;
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', static function (): array {
            return [
                'basedir' => sys_get_temp_dir(),
                'error' => '',
            ];
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'mkdir_p_callback', static function (): bool {
            return true;
        });

        $result = ADPW_Excel_Import_Service::initialize_job([
            'tmp_name' => $sourceFile,
            'name' => 'import.xlsx',
        ], [], 5);

        self::assertSame('No se pudo mover el archivo subido.', $result['error_general']);
        @unlink($sourceFile);
    }

    public function testInitializeJobReturnsValidationErrorAndDebugLines(): void {
        $sourceFile = $this->createSpreadsheetFile([
            ['SKU'],
            ['ABC123'],
        ]);
        $targetRoot = sys_get_temp_dir() . '/adpw-tests-' . uniqid('', true);
        mkdir($targetRoot);

        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_validator', static function (): bool {
            return true;
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_mover', static function ($source, $destination): bool {
            return copy((string) $source, (string) $destination);
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', static function () use ($targetRoot): array {
            return [
                'basedir' => $targetRoot,
                'error' => '',
            ];
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'mkdir_p_callback', static function ($target): bool {
            return is_dir((string) $target) || mkdir((string) $target, 0777, true);
        });

        $result = ADPW_Excel_Import_Service::initialize_job([
            'tmp_name' => $sourceFile,
            'name' => 'import.xlsx',
        ], [
            'actualizar_tam' => 1,
            'actualizar_cat' => 0,
        ], 5);

        self::assertSame('No se encontraron los encabezados esperados en el archivo Excel.', $result['error_general']);
        self::assertNotEmpty($result['debug_lines']);

        @unlink($sourceFile);
        @rmdir($targetRoot . '/adpw-imports');
        @rmdir($targetRoot);
    }

    public function testInitializeJobReturnsPreparationErrorWhenSpreadsheetCannotBeLoaded(): void {
        $sourceFile = tempnam(sys_get_temp_dir(), 'adpw-invalid-');
        file_put_contents($sourceFile, 'no es un excel valido');
        $targetRoot = sys_get_temp_dir() . '/adpw-tests-' . uniqid('', true);
        mkdir($targetRoot);

        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_validator', static function (): bool {
            return true;
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_mover', static function ($source, $destination): bool {
            return true;
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', static function () use ($targetRoot): array {
            return [
                'basedir' => $targetRoot,
                'error' => '',
            ];
        });
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'mkdir_p_callback', static function ($target): bool {
            return is_dir((string) $target) || mkdir((string) $target, 0777, true);
        });

        $result = ADPW_Excel_Import_Service::initialize_job([
            'tmp_name' => $sourceFile,
            'name' => 'invalido.xlsx',
        ], [], 5);

        self::assertStringContainsString('Error preparando el Excel:', $result['error_general']);

        @unlink($sourceFile);
        @rmdir($targetRoot . '/adpw-imports');
        @rmdir($targetRoot);
    }

    public function testImportServicePrivateUploadedFileValidatorUsesNativeFallback(): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'adpw-upload-');

        $result = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Service::class, 'is_valid_uploaded_file', [$tempFile]);

        self::assertFalse($result);
        @unlink($tempFile);
    }

    public function testImportServicePrivateUploadedFileValidatorUsesInjectedCallback(): void {
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_validator', static function (string $path): bool {
            return $path === '/tmp/custom-upload';
        });

        $result = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Service::class, 'is_valid_uploaded_file', ['/tmp/custom-upload']);

        self::assertTrue($result);
    }

    public function testImportServicePrivateFileMoverUsesNativeFallback(): void {
        $sourceFile = tempnam(sys_get_temp_dir(), 'adpw-source-');
        $destinationFile = sys_get_temp_dir() . '/adpw-destination-' . uniqid('', true) . '.xlsx';

        $result = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Service::class, 'move_uploaded_file_to_target', [$sourceFile, $destinationFile]);

        self::assertFalse($result);
        @unlink($sourceFile);
        @unlink($destinationFile);
    }

    public function testImportServicePrivateFileMoverUsesInjectedCallback(): void {
        $sourceFile = tempnam(sys_get_temp_dir(), 'adpw-source-');
        $destinationFile = sys_get_temp_dir() . '/adpw-destination-' . uniqid('', true) . '.xlsx';

        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_mover', static function (string $source, string $destination): bool {
            return copy($source, $destination);
        });

        $result = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Service::class, 'move_uploaded_file_to_target', [$sourceFile, $destinationFile]);

        self::assertTrue($result);
        self::assertFileExists($destinationFile);
        @unlink($sourceFile);
        @unlink($destinationFile);
    }

    public function testImportServicePrivateUploadDirUsesProviderCallback(): void {
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', static function (): array {
            return [
                'basedir' => '/tmp/custom-basedir',
                'error' => '',
            ];
        });

        $uploadDir = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Service::class, 'get_upload_dir');

        self::assertSame('/tmp/custom-basedir', $uploadDir['basedir']);
    }

    public function testImportServicePrivateUploadDirReturnsEmptyArrayForInvalidProviderResponse(): void {
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', static function () {
            return 'invalid';
        });

        $uploadDir = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Service::class, 'get_upload_dir');

        self::assertSame([], $uploadDir);
    }

    public function testImportServicePrivateUploadDirUsesWordPressFallbackWhenNoProviderExists(): void {
        $uploadDir = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Service::class, 'get_upload_dir');

        self::assertSame(sys_get_temp_dir(), $uploadDir['basedir']);
        self::assertSame('', $uploadDir['error']);
    }

    public function testImportServicePrivateCreateDirectoryUsesNativeFallback(): void {
        $targetDir = sys_get_temp_dir() . '/adpw-mkdir-' . uniqid('', true);

        $result = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Service::class, 'create_directory', [$targetDir]);

        self::assertTrue($result);
        @rmdir($targetDir);
    }

    public function testImportServicePrivateCreateDirectoryUsesCallbackResult(): void {
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'mkdir_p_callback', static function (): bool {
            return false;
        });

        $result = $this->invokePrivateStaticMethod(ADPW_Excel_Import_Service::class, 'create_directory', ['/tmp/ignored']);

        self::assertFalse($result);
    }

    public function testIsEmptyEntryRecognizesEmptyAndNonEmptyRows(): void {
        $empty = ADPW_Excel_Parse_Sheet_Batch_Service::is_empty_entry([
            'categoria' => '',
            'idcat' => 0,
            'largo' => 0,
            'ancho' => 0,
            'profundidad' => 0,
            'peso' => 0,
            'tamano' => '',
        ]);
        $nonEmpty = ADPW_Excel_Parse_Sheet_Batch_Service::is_empty_entry([
            'categoria' => '',
            'idcat' => 0,
            'largo' => 0,
            'ancho' => 0,
            'profundidad' => 0,
            'peso' => 0,
            'tamano' => 'premium',
        ]);

        self::assertTrue($empty);
        self::assertFalse($nonEmpty);
    }

    public function testProcessParseSheetBatchBuildsCategoryMapAndTransitionsStage(): void {
        $term = new WP_Term();
        $term->term_id = 17;
        $term->name = 'Baules';
        $term->slug = 'baules';
        $term->taxonomy = 'product_cat';
        $GLOBALS['adpw_test_terms'] = [$term];

        $uploadedFile = $this->createSpreadsheetFile([
            ['Categoria', 'Tamaño', 'Peso (kg)', 'Largo (cm)', 'Ancho (cm)', 'Profundidad (cm)'],
            ['Baules', 'grande', 8.5, 10, 20, 30],
        ]);
        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');

        $job = [
            'uploaded_file_path' => $uploadedFile,
            'categories_data_file' => $categoriesFile,
            'columns' => [
                'categoria' => 1,
                'tamano' => 2,
                'peso' => 3,
                'largo' => 4,
                'ancho' => 5,
                'profundidad' => 6,
                'idcat' => false,
            ],
            'batch_size' => 10,
            'cursor_row' => 2,
            'highest_row' => 5,
            'empty_row_count' => 0,
            'processed_rows' => 0,
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
        ];

        ADPW_Excel_Parse_Sheet_Batch_Service::process($job);

        $categoryMap = json_decode((string) file_get_contents($categoriesFile), true);

        self::assertSame('save_category_meta', $job['stage']);
        self::assertSame([17], $job['category_ids']);
        self::assertSame(6, $job['cursor_row']);
        self::assertSame('grande', $categoryMap['17']['tamano']);
        self::assertSame(8.5, $categoryMap['17']['peso']);
        self::assertSame(10, $categoryMap['17']['largo']);
        self::assertSame(20, $categoryMap['17']['ancho']);
        self::assertSame(30, $categoryMap['17']['profundidad']);

        @unlink($uploadedFile);
        @unlink($categoriesFile);
    }

    public function testProcessParseSheetBatchWarnsWhenCategoryMatchesMultipleTerms(): void {
        $termOne = new WP_Term();
        $termOne->term_id = 10;
        $termOne->name = 'Redes de Pulpo chica';
        $termOne->slug = 'redes-de-pulpo-chica';
        $termOne->taxonomy = 'product_cat';

        $termTwo = new WP_Term();
        $termTwo->term_id = 11;
        $termTwo->name = 'Redes de Pulpo para motos';
        $termTwo->slug = 'redes-de-pulpo-para-motos';
        $termTwo->taxonomy = 'product_cat';

        $GLOBALS['adpw_test_terms'] = [$termOne, $termTwo];

        $uploadedFile = $this->createSpreadsheetFile([
            ['Categoria'],
            ['Redes de Pulpo'],
            [''],
            [''],
            [''],
        ]);
        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');

        $job = [
            'uploaded_file_path' => $uploadedFile,
            'categories_data_file' => $categoriesFile,
            'columns' => [
                'categoria' => 1,
                'tamano' => false,
                'peso' => false,
                'largo' => false,
                'ancho' => false,
                'profundidad' => false,
                'idcat' => false,
            ],
            'batch_size' => 10,
            'cursor_row' => 2,
            'highest_row' => 5,
            'empty_row_count' => 0,
            'processed_rows' => 0,
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
        ];

        ADPW_Excel_Parse_Sheet_Batch_Service::process($job);

        self::assertSame('save_category_meta', $job['stage']);
        self::assertSame([10, 11], $job['category_ids']);
        self::assertNotEmpty($job['results']['detalles']);
        self::assertStringContainsString('coincide con 2 categorías', implode("\n", $job['results']['detalles']));

        @unlink($uploadedFile);
        @unlink($categoriesFile);
    }

    public function testProcessParseSheetBatchTracksMissingCategoriesAsErrors(): void {
        $uploadedFile = $this->createSpreadsheetFile([
            ['Categoria'],
            ['Sin Match'],
            [''],
            [''],
            [''],
        ]);
        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');

        $job = [
            'uploaded_file_path' => $uploadedFile,
            'categories_data_file' => $categoriesFile,
            'columns' => [
                'categoria' => 1,
                'tamano' => false,
                'peso' => false,
                'largo' => false,
                'ancho' => false,
                'profundidad' => false,
                'idcat' => false,
            ],
            'batch_size' => 10,
            'cursor_row' => 2,
            'highest_row' => 5,
            'empty_row_count' => 0,
            'processed_rows' => 0,
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
        ];

        ADPW_Excel_Parse_Sheet_Batch_Service::process($job);

        self::assertSame('save_category_meta', $job['stage']);
        self::assertSame(1, $job['results']['errores']);
        self::assertStringContainsString('No se encontró la categoría', implode("\n", $job['results']['detalles']));

        @unlink($uploadedFile);
        @unlink($categoriesFile);
    }

    public function testProcessSaveCategoryMetaBatchTransitionsImmediatelyWhenThereAreNoCategoriesLeft(): void {
        $job = [
            'batch_size' => 5,
            'category_cursor' => 0,
            'category_ids' => [],
            'product_cursor' => 9,
        ];

        ADPW_Excel_Save_Category_Meta_Batch_Service::process($job);

        self::assertSame('update_products', $job['stage']);
        self::assertSame(0, $job['category_cursor']);
        self::assertSame([], $job['product_queue']);
        self::assertSame(0, $job['product_cursor']);
    }

    public function testProcessSaveCategoryMetaBatchOnlyPersistsFieldsProvidedByExcel(): void {
        $shippingTerm = new WP_Term();
        $shippingTerm->term_id = 81;
        $shippingTerm->slug = 'premium';
        $shippingTerm->name = 'Premium';
        $shippingTerm->taxonomy = 'product_shipping_class';
        $GLOBALS['adpw_test_terms'] = [$shippingTerm];

        $GLOBALS['adpw_test_term_meta'][7] = [
            ADPW_Category_Metadata_Manager::META_WEIGHT => '1.1',
            ADPW_Category_Metadata_Manager::META_HEIGHT => '2.2',
            ADPW_Category_Metadata_Manager::META_WIDTH => '3.3',
            ADPW_Category_Metadata_Manager::META_DEPTH => '4.4',
        ];

        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');
        file_put_contents($categoriesFile, json_encode([
            '7' => [
                'categoria' => 'Viaje',
                'tamano' => 'premium',
                'peso' => 0,
                'ancho' => 0,
                'largo' => 0,
                'profundidad' => 0,
            ],
        ]));

        $job = [
            'batch_size' => 5,
            'category_cursor' => 0,
            'category_ids' => [7],
            'categories_data_file' => $categoriesFile,
            'columns' => [
                'categoria' => 1,
                'tamano' => 2,
                'peso' => false,
                'largo' => false,
                'ancho' => false,
                'profundidad' => false,
                'idcat' => false,
            ],
        ];

        ADPW_Excel_Save_Category_Meta_Batch_Service::process($job);

        self::assertSame('update_products', $job['stage']);
        self::assertSame('premium', $GLOBALS['adpw_test_term_meta'][7][ADPW_Category_Metadata_Manager::META_CLASS]);
        self::assertSame('1.1', $GLOBALS['adpw_test_term_meta'][7][ADPW_Category_Metadata_Manager::META_WEIGHT]);
        self::assertSame('2.2', $GLOBALS['adpw_test_term_meta'][7][ADPW_Category_Metadata_Manager::META_HEIGHT]);
        self::assertSame('3.3', $GLOBALS['adpw_test_term_meta'][7][ADPW_Category_Metadata_Manager::META_WIDTH]);
        self::assertSame('4.4', $GLOBALS['adpw_test_term_meta'][7][ADPW_Category_Metadata_Manager::META_DEPTH]);

        @unlink($categoriesFile);
    }

    public function testProcessSaveCategoryMetaBatchSkipsBlankValuesEvenWhenColumnsExist(): void {
        $GLOBALS['adpw_test_term_meta'][7] = [
            ADPW_Category_Metadata_Manager::META_WEIGHT => '1.1',
            ADPW_Category_Metadata_Manager::META_HEIGHT => '2.2',
            ADPW_Category_Metadata_Manager::META_WIDTH => '3.3',
            ADPW_Category_Metadata_Manager::META_DEPTH => '4.4',
        ];

        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');
        file_put_contents($categoriesFile, json_encode([
            '7' => [
                'categoria' => 'Viaje',
                'tamano' => '',
                'peso' => 0,
                'ancho' => 0,
                'largo' => 0,
                'profundidad' => 0,
            ],
        ]));

        $job = [
            'batch_size' => 5,
            'category_cursor' => 0,
            'category_ids' => [7],
            'categories_data_file' => $categoriesFile,
            'columns' => [
                'categoria' => 1,
                'tamano' => 2,
                'peso' => 3,
                'largo' => 4,
                'ancho' => 5,
                'profundidad' => 6,
                'idcat' => false,
            ],
        ];

        ADPW_Excel_Save_Category_Meta_Batch_Service::process($job);

        self::assertArrayNotHasKey(ADPW_Category_Metadata_Manager::META_CLASS, $GLOBALS['adpw_test_term_meta'][7]);
        self::assertSame('1.1', $GLOBALS['adpw_test_term_meta'][7][ADPW_Category_Metadata_Manager::META_WEIGHT]);
        self::assertSame('2.2', $GLOBALS['adpw_test_term_meta'][7][ADPW_Category_Metadata_Manager::META_HEIGHT]);
        self::assertSame('3.3', $GLOBALS['adpw_test_term_meta'][7][ADPW_Category_Metadata_Manager::META_WIDTH]);
        self::assertSame('4.4', $GLOBALS['adpw_test_term_meta'][7][ADPW_Category_Metadata_Manager::META_DEPTH]);

        @unlink($categoriesFile);
    }

    public function testPrepareProductQueueBuildsQueueFromMostSpecificCategory(): void {
        $GLOBALS['adpw_test_posts'] = [501];
        $GLOBALS['adpw_test_post_terms'][501] = [7, 8];
        $GLOBALS['adpw_test_ancestors'][7] = [];
        $GLOBALS['adpw_test_ancestors'][8] = [7];

        $job = [
            'category_ids' => [7, 8],
            'product_cursor' => 5,
        ];

        ADPW_Excel_Save_Category_Meta_Batch_Service::prepare_product_queue($job);

        self::assertSame([[
            'product_id' => 501,
            'category_id' => 8,
        ]], $job['product_queue']);
        self::assertSame(0, $job['product_cursor']);
    }

    public function testProcessUpdateProductsBatchCompletesAndSkipsInvalidQueueItems(): void {
        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');
        $uploadedFile = tempnam(sys_get_temp_dir(), 'adpw-xlsx-');
        file_put_contents($categoriesFile, json_encode([
            '7' => [
                'categoria' => 'Viaje',
                'tamano' => '',
                'peso' => 1.1,
                'ancho' => 2.2,
                'largo' => 3.3,
                'profundidad' => 4.4,
            ],
        ]));

        $job = [
            'batch_size' => 10,
            'product_cursor' => 0,
            'product_queue' => [
                ['product_id' => 0, 'category_id' => 7],
                ['product_id' => 10, 'category_id' => 0],
                ['product_id' => 10, 'category_id' => 99],
            ],
            'categories_data_file' => $categoriesFile,
            'uploaded_file_path' => $uploadedFile,
            'settings' => [
                'actualizar_si' => 1,
                'actualizar_cat' => 0,
            ],
            'mode' => [
                'actualizar_tam_dimensiones' => true,
                'solo_tamano_desde_excel' => false,
            ],
            'results' => [
                'totales' => 0,
                'parciales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
        ];

        ADPW_Excel_Update_Products_Batch_Service::process($job);

        self::assertSame('completed', $job['status']);
        self::assertSame(3, $job['product_cursor']);
        self::assertFileDoesNotExist($categoriesFile);
        self::assertFileDoesNotExist($uploadedFile);
        self::assertSame(0, $job['results']['totales']);
    }

    public function testCleanupJobFilesRemovesExistingTemporaryFiles(): void {
        $categoriesFile = tempnam(sys_get_temp_dir(), 'adpw-cat-');
        $uploadedFile = tempnam(sys_get_temp_dir(), 'adpw-xlsx-');

        ADPW_Excel_Import_Temp_Store::cleanup_job_files([
            'uploaded_file_path' => $uploadedFile,
            'categories_data_file' => $categoriesFile,
        ]);

        self::assertFileDoesNotExist($uploadedFile);
        self::assertFileDoesNotExist($categoriesFile);
    }

    public function testLoadSpreadsheetReturnsSpreadsheetInstanceForValidFile(): void {
        $uploadedFile = $this->createSpreadsheetFile([
            ['Categoria', 'Peso'],
            ['Baules', 10],
        ]);

        $spreadsheet = ADPW_Excel_Import_Temp_Store::load_spreadsheet($uploadedFile);

        self::assertSame('Categoria', $spreadsheet->getActiveSheet()->getCell('A1')->getFormattedValue());
        self::assertSame('Baules', $spreadsheet->getActiveSheet()->getCell('A2')->getFormattedValue());

        @unlink($uploadedFile);
    }

    public function testLoadJsonFileReturnsEmptyArrayForInvalidJson(): void {
        $jsonFile = tempnam(sys_get_temp_dir(), 'adpw-json-');
        file_put_contents($jsonFile, '{invalid');

        $result = ADPW_Excel_Import_Temp_Store::load_json_file($jsonFile);

        self::assertSame([], $result);
        @unlink($jsonFile);
    }

    public function testLoadJsonFileReturnsEmptyArrayForMissingFile(): void {
        $result = ADPW_Excel_Import_Temp_Store::load_json_file(sys_get_temp_dir() . '/adpw-missing-file.json');

        self::assertSame([], $result);
    }

    public function testAppendLimitedStopsAtConfiguredLimit(): void {
        $messages = ['uno'];

        ADPW_Excel_Parse_Sheet_Batch_Service::append_limited($messages, 'dos', 1);

        self::assertSame(['uno'], $messages);
    }

    public function testProductUpdateAppendLimitedStopsAtConfiguredLimit(): void {
        $messages = ['uno'];

        $this->invokePrivateStaticMethod(ADPW_Excel_Product_Update_Service::class, 'append_limited', [&$messages, 'dos', 1]);

        self::assertSame(['uno'], $messages);
    }

    public function testProductUpdateAppendLimitedInitializesMissingTargetArray(): void {
        $messages = null;

        $this->invokePrivateStaticMethod(ADPW_Excel_Product_Update_Service::class, 'append_limited', [&$messages, 'uno', 2]);

        self::assertSame(['uno'], $messages);
    }

    public function testSupportAppendLimitedStopsAtConfiguredLimit(): void {
        $messages = ['uno'];

        $this->invokePrivateStaticMethod(ADPW_Excel_Import_Support::class, 'append_limited', [&$messages, 'dos', 1]);

        self::assertSame(['uno'], $messages);
    }

    public function testSupportAppendLimitedInitializesMissingTargetArray(): void {
        $messages = null;

        $this->invokePrivateStaticMethod(ADPW_Excel_Import_Support::class, 'append_limited', [&$messages, 'uno', 2]);

        self::assertSame(['uno'], $messages);
    }

    public function testSaveJsonFilePersistsEncodedData(): void {
        $jsonFile = tempnam(sys_get_temp_dir(), 'adpw-json-');

        ADPW_Excel_Import_Temp_Store::save_json_file($jsonFile, ['ok' => true, 'count' => 2]);

        self::assertSame(['ok' => true, 'count' => 2], json_decode((string) file_get_contents($jsonFile), true));
        @unlink($jsonFile);
    }

    private function createSpreadsheetFile(array $rows): string {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1) . (string) ($rowIndex + 1), $value);
            }
        }

        $file = tempnam(sys_get_temp_dir(), 'adpw-xlsx-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($file);

        return $file;
    }

    private function invokePrivateStaticMethod(string $class, string $method, array $args = []) {
        $reflection = new ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }

    private function setPrivateStaticProperty(string $class, string $property, $value): void {
        $reflection = new ReflectionProperty($class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue(null, $value);
    }
}
