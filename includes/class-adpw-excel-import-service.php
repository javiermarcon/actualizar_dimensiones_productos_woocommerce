<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Import_Service {
    public static function initialize_job($file, $settings, $batch_size) {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['error_general' => 'No se seleccionó ningún archivo válido.'];
        }

        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            return ['error_general' => 'No se pudo acceder al directorio de uploads.'];
        }

        $target_dir = trailingslashit($upload['basedir']) . 'adpw-imports';
        if (!wp_mkdir_p($target_dir)) {
            return ['error_general' => 'No se pudo crear el directorio temporal de importación.'];
        }

        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'xlsx';
        }

        $uploaded_file_path = trailingslashit($target_dir) . 'adpw-upload-' . wp_generate_uuid4() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploaded_file_path)) {
            return ['error_general' => 'No se pudo mover el archivo subido.'];
        }

        try {
            $spreadsheet = self::load_spreadsheet($uploaded_file_path);
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ADPW_Excel_Import_Support::get_headers($sheet);
            $columns = ADPW_Excel_Import_Support::build_columns($headers);

            $actualizar_tam = !empty($settings['actualizar_tam']);
            $actualizar_cat = !empty($settings['actualizar_cat']);
            $mode_config = ADPW_Excel_Import_Support::build_mode($settings, $columns);
            $mode = $mode_config['mode'];
            $faltan_dimensiones = $mode_config['faltan_dimensiones'];

            $debug_lines = [];
            $debug_lines[] = 'headers=' . wp_json_encode(array_values(array_filter($headers, static function ($value) {
                return $value !== '';
            })));
            $debug_lines[] = 'columns=' . wp_json_encode($columns);
            $debug_lines[] = 'settings=' . wp_json_encode([
                'actualizar_tam' => $actualizar_tam,
                'actualizar_cat' => $actualizar_cat,
            ]);
            $debug_lines[] = 'mode=' . wp_json_encode($mode);

            $validation = ADPW_Excel_Import_Support::validate_headers($columns, $headers, $actualizar_tam, $actualizar_cat, $mode['actualizar_tam_dimensiones'], $faltan_dimensiones);
            if (!empty($validation['error_general'])) {
                @unlink($uploaded_file_path);
                $validation['debug_lines'] = $debug_lines;
                return $validation;
            }

            $categories_data_file = trailingslashit($target_dir) . 'adpw-categories-' . wp_generate_uuid4() . '.json';
            self::save_json_file($categories_data_file, []);

            $highest_row = (int) $sheet->getHighestRow();

            return [
                'uploaded_file_path' => $uploaded_file_path,
                'categories_data_file' => $categories_data_file,
                'columns' => $columns,
                'mode' => $mode,
                'batch_size' => max(1, (int) $batch_size),
                'cursor_row' => 2,
                'highest_row' => $highest_row,
                'empty_row_count' => 0,
                'processed_rows' => 0,
                'total_rows' => max(0, $highest_row - 1),
                'category_ids' => [],
                'category_cursor' => 0,
                'product_queue' => [],
                'product_cursor' => 0,
                'results' => [
                    'totales' => 0,
                    'parciales' => 0,
                    'errores' => 0,
                    'detalles' => [],
                    'productos_modificados' => [],
                ],
                'warnings' => $validation['warnings'] ?? [],
                'debug_lines' => $debug_lines,
            ];
        } catch (\Exception $e) {
            @unlink($uploaded_file_path);
            return ['error_general' => 'Error preparando el Excel: ' . $e->getMessage()];
        }
    }

    public static function process_job_batch(&$job) {
        $stage = (string) ($job['stage'] ?? '');

        if ($stage === 'parse_sheet') {
            self::process_parse_sheet_batch($job);
            return;
        }

        if ($stage === 'save_category_meta') {
            self::process_save_category_meta_batch($job);
            return;
        }

        if ($stage === 'update_products') {
            self::process_update_products_batch($job);
            return;
        }

        $job['status'] = 'failed';
        $job['error_general'] = 'Etapa de importación desconocida: ' . $stage;
    }

    private static function process_parse_sheet_batch(&$job) {
        if (empty($job['uploaded_file_path']) || !file_exists($job['uploaded_file_path'])) {
            $job['status'] = 'failed';
            $job['error_general'] = 'No existe el archivo temporal del Excel.';
            return;
        }

        $spreadsheet = self::load_spreadsheet($job['uploaded_file_path']);
        $sheet = $spreadsheet->getActiveSheet();

        $batch_size = max(1, (int) ($job['batch_size'] ?? 1));
        $row = (int) ($job['cursor_row'] ?? 2);
        $highest_row = (int) ($job['highest_row'] ?? 1);
        $processed_in_batch = 0;

        $category_map = self::load_json_file((string) $job['categories_data_file']);
        $name_to_ids_cache = [];
        $id_to_id_cache = [];

        while ($row <= $highest_row && $processed_in_batch < $batch_size) {
            $entry = self::read_entry_from_sheet($sheet, $job['columns'], $row);
            $row++;
            $processed_in_batch++;
            $job['processed_rows'] = (int) $job['processed_rows'] + 1;

            if (self::is_empty_entry($entry)) {
                $job['empty_row_count'] = (int) $job['empty_row_count'] + 1;
                if ((int) $job['empty_row_count'] >= 3) {
                    break;
                }
                continue;
            }

            $job['empty_row_count'] = 0;
            $term_ids = ADPW_Excel_Import_Support::resolve_category_ids_for_parse($entry['categoria'], (int) $entry['idcat'], $entry['row'], $job['results']['detalles'], $name_to_ids_cache, $id_to_id_cache);
            if (empty($term_ids)) {
                $job['results']['errores']++;
                continue;
            }

            if (count($term_ids) > 1 && (string) $entry['categoria'] !== '') {
                self::append_limited($job['results']['detalles'], "Fila {$entry['row']}: la categoría '{$entry['categoria']}' coincide con " . count($term_ids) . ' categorías. Se actualizarán todas.');
            }

            foreach ($term_ids as $term_id) {
                $key = (string) $term_id;
                if (!isset($category_map[$key])) {
                    $category_map[$key] = [
                        'categoria_id' => $term_id,
                        'categoria' => (string) $entry['categoria'],
                        'tamano' => '',
                        'peso' => 0,
                        'largo' => 0,
                        'ancho' => 0,
                        'profundidad' => 0,
                    ];
                }

                if ((string) $entry['tamano'] !== '') {
                    $category_map[$key]['tamano'] = (string) $entry['tamano'];
                }
                if ((float) $entry['peso'] > 0) {
                    $category_map[$key]['peso'] = (float) $entry['peso'];
                }
                if ((float) $entry['largo'] > 0) {
                    $category_map[$key]['largo'] = (float) $entry['largo'];
                }
                if ((float) $entry['ancho'] > 0) {
                    $category_map[$key]['ancho'] = (float) $entry['ancho'];
                }
                if ((float) $entry['profundidad'] > 0) {
                    $category_map[$key]['profundidad'] = (float) $entry['profundidad'];
                }
            }
        }

        self::save_json_file((string) $job['categories_data_file'], $category_map);

        $job['cursor_row'] = $row;

        if ($row > $highest_row || (int) $job['empty_row_count'] >= 3) {
            $category_ids = array_map('intval', array_keys($category_map));
            $job['category_ids'] = array_values($category_ids);
            $job['category_cursor'] = 0;
            $job['stage'] = 'save_category_meta';
        }
    }

    private static function process_save_category_meta_batch(&$job) {
        $batch_size = max(1, (int) ($job['batch_size'] ?? 1));
        $cursor = (int) ($job['category_cursor'] ?? 0);
        $category_ids = isset($job['category_ids']) && is_array($job['category_ids']) ? $job['category_ids'] : [];

        if ($cursor >= count($category_ids)) {
            $job['category_cursor'] = 0;
            self::prepare_product_queue($job);
            $job['stage'] = 'update_products';
            return;
        }

        $category_map = self::load_json_file((string) $job['categories_data_file']);
        $valid_shipping_slugs = ADPW_Category_Metadata_Manager::get_valid_shipping_slugs();
        if (!is_array($valid_shipping_slugs)) {
            $job['status'] = 'failed';
            $job['error_general'] = 'No se pudieron validar clases de envío.';
            return;
        }

        $processed = 0;
        while ($cursor < count($category_ids) && $processed < $batch_size) {
            $category_id = (int) $category_ids[$cursor];
            $cursor++;
            $processed++;

            $entry = $category_map[(string) $category_id] ?? null;
            if (!is_array($entry)) {
                continue;
            }

            $metadata = [
                'clase_envio' => (string) ($entry['tamano'] ?? ''),
                'peso' => (string) ($entry['peso'] ?? ''),
                'alto' => (string) ($entry['profundidad'] ?? ''),
                'ancho' => (string) ($entry['ancho'] ?? ''),
                'profundidad' => (string) ($entry['largo'] ?? ''),
            ];

            ADPW_Category_Metadata_Manager::save_category_metadata($category_id, $metadata, $valid_shipping_slugs);
        }

        $job['category_cursor'] = $cursor;

        if ($cursor >= count($category_ids)) {
            $job['category_cursor'] = 0;
            self::prepare_product_queue($job);
            $job['stage'] = 'update_products';
        }
    }

    private static function process_update_products_batch(&$job) {
        $batch_size = max(1, (int) ($job['batch_size'] ?? 1));
        $cursor = (int) ($job['product_cursor'] ?? 0);
        $product_queue = isset($job['product_queue']) && is_array($job['product_queue']) ? $job['product_queue'] : [];

        if ($cursor >= count($product_queue)) {
            $job['status'] = 'completed';
            self::cleanup_job_files($job);
            return;
        }

        $category_map = self::load_json_file((string) $job['categories_data_file']);
        $processed = 0;

        while ($cursor < count($product_queue) && $processed < $batch_size) {
            $queue_item = $product_queue[$cursor] ?? [];
            $cursor++;
            $processed++;

            $product_id = isset($queue_item['product_id']) ? (int) $queue_item['product_id'] : 0;
            $category_id = isset($queue_item['category_id']) ? (int) $queue_item['category_id'] : 0;
            if ($product_id <= 0 || $category_id <= 0) {
                continue;
            }

            $entry = $category_map[(string) $category_id] ?? null;
            if (!is_array($entry)) {
                continue;
            }

            ADPW_Excel_Product_Update_Service::process_product_update($product_id, $entry, $job['settings'], $job['mode'], $job['results']);
        }

        $job['product_cursor'] = $cursor;

        if ($cursor >= count($product_queue)) {
            $job['status'] = 'completed';
            self::cleanup_job_files($job);
        }
    }

    private static function prepare_product_queue(&$job) {
        $category_ids = isset($job['category_ids']) && is_array($job['category_ids']) ? $job['category_ids'] : [];
        $job['product_queue'] = ADPW_Category_Metadata_Manager::build_product_queue_for_categories($category_ids);
        $job['product_cursor'] = 0;
    }

    private static function cleanup_job_files($job) {
        if (!empty($job['uploaded_file_path']) && file_exists($job['uploaded_file_path'])) {
            @unlink($job['uploaded_file_path']);
        }
        if (!empty($job['categories_data_file']) && file_exists($job['categories_data_file'])) {
            @unlink($job['categories_data_file']);
        }
    }

    private static function load_json_file($path) {
        if (!is_string($path) || $path === '' || !file_exists($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function save_json_file($path, $data) {
        file_put_contents($path, wp_json_encode($data));
    }

    private static function read_entry_from_sheet($sheet, $columns, $row) {
        return [
            'row' => (int) $row,
            'categoria' => ($columns['categoria'] !== false) ? trim((string) $sheet->getCell([$columns['categoria'], $row])->getFormattedValue()) : '',
            'idcat' => ($columns['idcat'] !== false) ? intval($sheet->getCell([$columns['idcat'], $row])->getFormattedValue()) : 0,
            'largo' => ($columns['largo'] !== false) ? floatval($sheet->getCell([$columns['largo'], $row])->getFormattedValue()) : 0,
            'ancho' => ($columns['ancho'] !== false) ? floatval($sheet->getCell([$columns['ancho'], $row])->getFormattedValue()) : 0,
            'profundidad' => ($columns['profundidad'] !== false) ? floatval($sheet->getCell([$columns['profundidad'], $row])->getFormattedValue()) : 0,
            'peso' => ($columns['peso'] !== false) ? floatval($sheet->getCell([$columns['peso'], $row])->getFormattedValue()) : 0,
            'tamano' => ($columns['tamano'] !== false) ? trim((string) $sheet->getCell([$columns['tamano'], $row])->getFormattedValue()) : '',
        ];
    }

    private static function is_empty_entry($entry) {
        return empty($entry['categoria']) && empty($entry['idcat']) && empty($entry['largo']) && empty($entry['ancho']) && empty($entry['profundidad']) && empty($entry['peso']) && empty($entry['tamano']);
    }

    private static function append_limited(&$target, $message, $limit = 250) {
        if (!is_array($target)) {
            $target = [];
        }
        if (count($target) >= $limit) {
            return;
        }
        $target[] = $message;
    }

    private static function load_spreadsheet($path) {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        return $reader->load($path);
    }

}
