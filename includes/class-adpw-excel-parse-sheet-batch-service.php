<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Parse_Sheet_Batch_Service {
    public static function process(&$job) {
        if (empty($job['uploaded_file_path']) || !file_exists($job['uploaded_file_path'])) {
            $job['status'] = 'failed';
            $job['error_general'] = 'No existe el archivo temporal del Excel.';
            return;
        }

        $spreadsheet = ADPW_Excel_Import_Temp_Store::load_spreadsheet($job['uploaded_file_path']);
        $sheet = $spreadsheet->getActiveSheet();

        $batch_size = max(1, (int) ($job['batch_size'] ?? 1));
        $row = (int) ($job['cursor_row'] ?? 2);
        $highest_row = (int) ($job['highest_row'] ?? 1);
        $processed_in_batch = 0;

        $category_map = ADPW_Excel_Import_Temp_Store::load_json_file((string) $job['categories_data_file']);
        $name_to_ids_cache = [];
        $id_to_id_cache = [];

        while ($row <= $highest_row && $processed_in_batch < $batch_size) {
            $entry = self::read_entry_from_sheet($sheet, $job['columns'], $row);
            $row++;
            $processed_in_batch++;
            $job['processed_rows'] = (int) ($job['processed_rows'] ?? 0) + 1;

            if (self::is_empty_entry($entry)) {
                $job['empty_row_count'] = (int) ($job['empty_row_count'] ?? 0) + 1;
                if ((int) $job['empty_row_count'] >= 3) {
                    break;
                }
                continue;
            }

            $job['empty_row_count'] = 0;
            $term_ids = ADPW_Excel_Import_Support::resolve_category_ids_for_parse(
                $entry['categoria'],
                (int) $entry['idcat'],
                $entry['row'],
                $job['results']['detalles'],
                $name_to_ids_cache,
                $id_to_id_cache
            );
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

        ADPW_Excel_Import_Temp_Store::save_json_file((string) $job['categories_data_file'], $category_map);

        $job['cursor_row'] = $row;

        if ($row > $highest_row || (int) ($job['empty_row_count'] ?? 0) >= 3) {
            $category_ids = array_map('intval', array_keys($category_map));
            $job['category_ids'] = array_values($category_ids);
            $job['category_cursor'] = 0;
            $job['stage'] = 'save_category_meta';
        }
    }

    public static function read_entry_from_sheet($sheet, $columns, $row) {
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

    public static function is_empty_entry($entry) {
        return empty($entry['categoria']) && empty($entry['idcat']) && empty($entry['largo']) && empty($entry['ancho']) && empty($entry['profundidad']) && empty($entry['peso']) && empty($entry['tamano']);
    }

    public static function append_limited(&$target, $message, $limit = 250) {
        if (!is_array($target)) {
            $target = [];
        }
        if (count($target) >= $limit) {
            return;
        }
        $target[] = $message;
    }
}
