<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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

            $headers = self::get_headers($sheet);
            $columns = [
                'categoria' => self::find_header_index($headers, ['categoria']),
                'largo' => self::find_header_index($headers, ['largo (cm)', 'largo(cm)', 'largo', 'longitud (cm)', 'longitud(cm)', 'longitud']),
                'ancho' => self::find_header_index($headers, ['ancho (cm)', 'ancho(cm)', 'ancho']),
                'profundidad' => self::find_header_index($headers, ['profundidad (cm)', 'profundidad(cm)', 'profundidad', 'alto (cm)', 'alto(cm)', 'alto']),
                'peso' => self::find_header_index($headers, ['peso (kg)', 'peso(kg)', 'peso']),
                'idcat' => self::find_header_index($headers, ['id categoria', 'id categoria woocommerce', 'idcat', 'id']),
                'tamano' => self::find_header_index($headers, ['tamano', 'tamaño', 'size', 'talle']),
            ];

            $actualizar_tam = !empty($settings['actualizar_tam']);
            $actualizar_cat = !empty($settings['actualizar_cat']);
            $faltan_dimensiones = (
                $columns['largo'] === false &&
                $columns['ancho'] === false &&
                $columns['profundidad'] === false &&
                $columns['peso'] === false
            );

            $mode = [
                'solo_tamano_desde_excel' => $actualizar_tam && $faltan_dimensiones && $columns['tamano'] !== false,
                'actualizar_tam_dimensiones' => $actualizar_tam && !$faltan_dimensiones,
            ];

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

            $validation = self::validate_headers($columns, $headers, $actualizar_tam, $actualizar_cat, $mode['actualizar_tam_dimensiones'], $faltan_dimensiones);
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
            $term_ids = self::resolve_category_ids_for_parse($entry['categoria'], (int) $entry['idcat'], $entry['row'], $job['results']['detalles'], $name_to_ids_cache, $id_to_id_cache);
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

            self::process_product_update($product_id, $category_id, $entry, $job['settings'], $job['mode'], $job['results']);
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

    private static function process_product_update($product_id, $category_id, $entry, $settings, $mode, &$results) {
        $actualizar_si = !empty($settings['actualizar_si']);
        $actualizar_cat = !empty($settings['actualizar_cat']);

        $product = wc_get_product($product_id);
        if (!$product) {
            $results['errores']++;
            self::append_limited($results['detalles'], 'No se pudo cargar el producto con ID ' . $product_id . '.');
            return;
        }

        $dim_res = [
            'modificado' => false,
            'actualizacion_total' => false,
            'log_producto' => '',
            'detalles_errores' => '',
        ];
        $clase_modificada = false;

        if (!empty($mode['actualizar_tam_dimensiones']) && ((float) $entry['peso'] > 0 || (float) $entry['largo'] > 0 || (float) $entry['ancho'] > 0 || (float) $entry['profundidad'] > 0)) {
            $dim_res = self::update_dimensions($product, [
                'peso' => (float) $entry['peso'],
                'largo' => (float) $entry['largo'],
                'ancho' => (float) $entry['ancho'],
                'profundidad' => (float) $entry['profundidad'],
            ], $actualizar_si, (string) ($entry['categoria'] ?? ''), $product_id);
        }

        $tamano = (string) ($entry['tamano'] ?? '');
        if (($actualizar_cat || !empty($mode['solo_tamano_desde_excel'])) && $tamano !== '') {
            $class_res = self::update_shipping_class($product, $tamano);
            if (!empty($class_res['detalles_errores'])) {
                self::append_limited($results['detalles'], $class_res['detalles_errores']);
            }
            if (!empty($class_res['log_producto'])) {
                self::append_limited($results['detalles'], $class_res['log_producto']);
            }
            $clase_modificada = !empty($class_res['modificado']);
        }

        if (!empty($dim_res['modificado'])) {
            if (!empty($dim_res['actualizacion_total'])) {
                $results['totales']++;
            } else {
                $results['parciales']++;
            }
            if (!empty($dim_res['log_producto'])) {
                self::append_limited($results['productos_modificados'], $dim_res['log_producto']);
            }
            if (!empty($dim_res['detalles_errores'])) {
                self::append_limited($results['detalles'], $dim_res['detalles_errores']);
            }
        }

        if (!empty($dim_res['modificado']) || $clase_modificada) {
            $results['totales']++;
        }
    }

    private static function resolve_category_ids_for_parse($categoria, $idcat, $row, &$detalles_errores, &$name_to_ids_cache, &$id_to_id_cache) {
        $categoria = trim((string) $categoria);
        $idcat = (int) $idcat;
        $matched_ids = [];

        if ($categoria !== '') {
            $key = self::normalize_category_name($categoria);
            if (isset($name_to_ids_cache[$key])) {
                $matched_ids = $name_to_ids_cache[$key];
            } else {
                $matched_ids = self::find_category_ids_by_name($categoria);
                $name_to_ids_cache[$key] = array_values(array_unique(array_filter($matched_ids)));
            }
        }

        if ($idcat > 0) {
            if (isset($id_to_id_cache[$idcat])) {
                $matched_ids[] = (int) $id_to_id_cache[$idcat];
            } else {
                $term = get_term($idcat, 'product_cat');
                if ($term && !is_wp_error($term)) {
                    $id_to_id_cache[$idcat] = (int) $term->term_id;
                    $matched_ids[] = (int) $term->term_id;
                }
            }
        }

        $matched_ids = array_values(array_unique(array_filter(array_map('intval', $matched_ids))));
        if (!empty($matched_ids)) {
            return $matched_ids;
        }

        self::append_limited($detalles_errores, "Fila {$row}: No se encontró la categoría '{$categoria}'.");
        return [];
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

    private static function get_headers($sheet) {
        $encabezados = [];
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($col = 1; $col <= $highestColumnIndex; ++$col) {
            $cell = $sheet->getCell([$col, 1]);
            $encabezados[$col] = self::normalize_header($cell->getFormattedValue());
        }

        return $encabezados;
    }

    private static function normalize_header($valor) {
        $texto = trim((string) $valor);
        $texto = str_replace("\xEF\xBB\xBF", '', $texto);
        $texto = str_replace("\xc2\xa0", ' ', $texto);
        $texto = str_replace(['_', '-'], ' ', $texto);
        $texto = strtolower(remove_accents($texto));
        $texto = preg_replace('/\s+/', ' ', $texto);
        return trim((string) $texto);
    }

    private static function normalize_category_name($value) {
        return self::normalize_header($value);
    }

    private static function find_category_ids_by_name($category_name) {
        $normalized = self::normalize_category_name($category_name);
        if ($normalized === '') {
            return [];
        }

        $lookup = self::get_product_category_lookup();
        if (isset($lookup['exact'][$normalized])) {
            return $lookup['exact'][$normalized];
        }

        $matched_ids = [];
        foreach ($lookup['entries'] as $entry) {
            $term_name = (string) ($entry['normalized_name'] ?? '');
            if ($term_name === '') {
                continue;
            }

            if (strpos($term_name, $normalized) !== false || strpos($normalized, $term_name) !== false) {
                $matched_ids[] = (int) ($entry['term_id'] ?? 0);
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $matched_ids))));
    }

    private static function get_product_category_lookup() {
        static $lookup = null;

        if (is_array($lookup)) {
            return $lookup;
        }

        $lookup = [
            'exact' => [],
            'entries' => [],
        ];

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return $lookup;
        }

        foreach ($terms as $term) {
            if (!isset($term->term_id) || !isset($term->name)) {
                continue;
            }

            $term_id = (int) $term->term_id;
            $normalized_name = self::normalize_category_name($term->name);
            if ($term_id <= 0 || $normalized_name === '') {
                continue;
            }

            if (!isset($lookup['exact'][$normalized_name])) {
                $lookup['exact'][$normalized_name] = [];
            }

            $lookup['exact'][$normalized_name][] = $term_id;
            $lookup['entries'][] = [
                'term_id' => $term_id,
                'normalized_name' => $normalized_name,
            ];
        }

        foreach ($lookup['exact'] as $name => $ids) {
            $lookup['exact'][$name] = array_values(array_unique(array_map('intval', $ids)));
        }

        return $lookup;
    }

    private static function find_header_index($headers, $candidates) {
        $normalized_candidates = array_map([self::class, 'normalize_header'], (array) $candidates);

        foreach ($normalized_candidates as $candidate) {
            $found = array_search($candidate, $headers, true);
            if ($found !== false) {
                return $found;
            }
        }

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            foreach ($normalized_candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                if (strpos($header, $candidate) !== false) {
                    return $index;
                }
            }
        }

        return false;
    }

    private static function validate_headers($columns, $headers, $actualizar_tam, $actualizar_cat, $actualizar_tam_dimensiones, $faltan_dimensiones) {
        $required = ['Categoría'];
        $warnings = [];

        if ($actualizar_tam_dimensiones) {
            $required[] = 'Largo (cm) o Ancho (cm) o Profundidad (cm) o Peso (kg)';
        } elseif ($actualizar_tam && $columns['tamano'] === false) {
            $required[] = 'Tamaño (para importación Categoría + tamaño)';
        }

        if ($actualizar_cat && $columns['tamano'] === false) {
            $warnings[] = 'Configuración pide actualizar tamaño, pero no existe la columna "Tamaño". Se omite actualización de clase de envío en esta importación.';
        }

        if (
            $columns['categoria'] === false ||
            ($actualizar_tam && $faltan_dimensiones && $columns['tamano'] === false)
        ) {
            $detected = [];
            foreach ($headers as $h) {
                if ($h !== '') {
                    $detected[] = $h;
                }
            }
            return [
                'error_general' => 'No se encontraron los encabezados esperados en el archivo Excel.',
                'detalles' => [
                    'Incluí al menos: ' . implode(', ', $required) . '.',
                    'Encabezados detectados: ' . (!empty($detected) ? implode(', ', $detected) : '(ninguno)') . '.',
                ],
            ];
        }

        return [
            'warnings' => $warnings,
        ];
    }

    private static function update_dimensions($product, $incoming, $actualizar_si, $categoria, $product_id) {
        $modificado = false;
        $actualizacion_total = false;
        $log_producto = '';

        $peso_actual = $product->get_weight();
        $largo_actual = $product->get_length();
        $ancho_actual = $product->get_width();
        $profundidad_actual = $product->get_height();

        if ($actualizar_si || !$peso_actual || !$largo_actual || !$ancho_actual || !$profundidad_actual) {
            $log_producto .= "Actualizando el producto {$product->get_name()} ({$product_id}) de la categoría {$categoria}; ";

            if (($actualizar_si || !$peso_actual) && $incoming['peso'] > 0) {
                $product->set_weight($incoming['peso']);
                $log_producto .= "Peso {$peso_actual} -> {$incoming['peso']}; ";
                $modificado = true;
            }
            if (($actualizar_si || !$largo_actual) && $incoming['largo'] > 0) {
                $product->set_length($incoming['largo']);
                $log_producto .= "Largo {$largo_actual} -> {$incoming['largo']}; ";
                $modificado = true;
            }
            if (($actualizar_si || !$ancho_actual) && $incoming['ancho'] > 0) {
                $product->set_width($incoming['ancho']);
                $log_producto .= "Ancho {$ancho_actual} -> {$incoming['ancho']}; ";
                $modificado = true;
            }
            if (($actualizar_si || !$profundidad_actual) && $incoming['profundidad'] > 0) {
                $product->set_height($incoming['profundidad']);
                $log_producto .= "Profundidad {$profundidad_actual} -> {$incoming['profundidad']}; ";
                $modificado = true;
            }

            if ($actualizar_si || (!$peso_actual && !$largo_actual && !$ancho_actual && !$profundidad_actual)) {
                $actualizacion_total = true;
            }

            if ($modificado) {
                $product->save();
            }
        }

        return [
            'modificado' => $modificado,
            'actualizacion_total' => $actualizacion_total,
            'log_producto' => $log_producto,
            'detalles_errores' => '',
        ];
    }

    private static function update_shipping_class($product, $tamano) {
        $shipping_class = get_term_by('name', $tamano, 'product_shipping_class') ?: get_term_by('slug', $tamano, 'product_shipping_class');
        if (!$shipping_class) {
            return [
                'modificado' => false,
                'detalles_errores' => "Clase de envío '{$tamano}' no encontrada.",
                'log_producto' => '',
            ];
        }

        if ((int) $product->get_shipping_class_id() === (int) $shipping_class->term_id) {
            return [
                'modificado' => false,
                'detalles_errores' => '',
                'log_producto' => '',
            ];
        }

        $clase_actual = $product->get_shipping_class();
        $product->set_shipping_class_id($shipping_class->term_id);
        $product->save();

        return [
            'modificado' => true,
            'detalles_errores' => '',
            'log_producto' => 'Clase de envío modificada para ' . $product->get_name() . ' (ID: ' . $product->get_id() . ') - Clase original: ' . $clase_actual . '; Nueva clase: ' . $shipping_class->name . '.',
        ];
    }
}
