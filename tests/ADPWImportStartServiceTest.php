<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class ADPWImportStartServiceTest extends TestCase {
    protected function tearDown(): void {
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_validator', null);
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'uploaded_file_mover', null);
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'upload_dir_provider', null);
        $this->setPrivateStaticProperty(ADPW_Excel_Import_Service::class, 'mkdir_p_callback', null);
        adpw_test_reset_wp_stubs();
    }

    public function testImportStartServiceReturnsInitializationErrors(): void {
        $result = ADPW_Import_Start_Service::start([], [
            'categorias_por_lote' => 10,
        ]);

        self::assertSame('No se seleccionó ningún archivo válido.', $result['error_general']);
        self::assertSame([], $result['detalles']);
        self::assertSame([], $result['debug_lines']);
    }

    public function testImportStartServiceBuildsJobAndResponseForValidSpreadsheet(): void {
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

        $result = ADPW_Import_Start_Service::start([
            'tmp_name' => $sourceFile,
            'name' => 'import.xlsx',
        ], [
            'categorias_por_lote' => 7,
            'actualizar_tam' => 1,
            'actualizar_cat' => 0,
        ]);

        self::assertSame('parse_sheet', $result['response']['stage']);
        self::assertSame(7, $result['response']['batch_size']);
        self::assertSame('running', $result['job']['status']);
        self::assertNotEmpty($result['job']['debug_log']);

        @unlink($result['job']['uploaded_file_path']);
        @unlink($result['job']['categories_data_file']);
        @unlink($sourceFile);
        @rmdir($targetRoot . '/adpw-imports');
        @rmdir($targetRoot);
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

    private function setPrivateStaticProperty(string $class, string $property, $value): void {
        $reflection = new ReflectionProperty($class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue(null, $value);
    }
}
