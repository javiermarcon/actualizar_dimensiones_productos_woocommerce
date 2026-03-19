<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Import_Support {
    private static ?array $product_category_lookup = null;

    public static function build_columns($headers) {
        return [
            'categoria' => self::find_header_index($headers, ['categoria']),
            'largo' => self::find_header_index($headers, ['largo (cm)', 'largo(cm)', 'largo', 'longitud (cm)', 'longitud(cm)', 'longitud']),
            'ancho' => self::find_header_index($headers, ['ancho (cm)', 'ancho(cm)', 'ancho']),
            'profundidad' => self::find_header_index($headers, ['profundidad (cm)', 'profundidad(cm)', 'profundidad', 'alto (cm)', 'alto(cm)', 'alto']),
            'peso' => self::find_header_index($headers, ['peso (kg)', 'peso(kg)', 'peso']),
            'idcat' => self::find_header_index($headers, ['id categoria', 'id categoria woocommerce', 'idcat', 'id']),
            'tamano' => self::find_header_index($headers, ['tamano', 'tamaño', 'size', 'talle']),
        ];
    }

    public static function build_mode($settings, $columns) {
        $actualizar_tam = !empty($settings['actualizar_tam']);
        $faltan_dimensiones = (
            $columns['largo'] === false &&
            $columns['ancho'] === false &&
            $columns['profundidad'] === false &&
            $columns['peso'] === false
        );

        return [
            'mode' => [
                'solo_tamano_desde_excel' => $actualizar_tam && $faltan_dimensiones && $columns['tamano'] !== false,
                'actualizar_tam_dimensiones' => $actualizar_tam && !$faltan_dimensiones,
            ],
            'faltan_dimensiones' => $faltan_dimensiones,
        ];
    }

    public static function get_headers($sheet) {
        $encabezados = [];
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($col = 1; $col <= $highestColumnIndex; ++$col) {
            $cell = $sheet->getCell([$col, 1]);
            $encabezados[$col] = self::normalize_header($cell->getFormattedValue());
        }

        return $encabezados;
    }

    public static function normalize_header($valor) {
        $texto = trim((string) $valor);
        $texto = str_replace("\xEF\xBB\xBF", '', $texto);
        $texto = str_replace("\xc2\xa0", ' ', $texto);
        $texto = str_replace(['_', '-'], ' ', $texto);
        $texto = strtolower(remove_accents($texto));
        $texto = preg_replace('/\s+/', ' ', $texto);
        return trim((string) $texto);
    }

    public static function find_header_index($headers, $candidates) {
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

    public static function validate_headers($columns, $headers, $actualizar_tam, $actualizar_cat, $actualizar_tam_dimensiones, $faltan_dimensiones) {
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

    public static function resolve_category_ids_for_parse($categoria, $idcat, $row, &$detalles_errores, &$name_to_ids_cache, &$id_to_id_cache) {
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

    public static function find_category_ids_by_name($category_name) {
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

    private static function normalize_category_name($value) {
        return self::normalize_header($value);
    }

    private static function get_product_category_lookup() {
        if (is_array(self::$product_category_lookup)) {
            return self::$product_category_lookup;
        }

        self::$product_category_lookup = [
            'exact' => [],
            'entries' => [],
        ];

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return self::$product_category_lookup;
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

            if (!isset(self::$product_category_lookup['exact'][$normalized_name])) {
                self::$product_category_lookup['exact'][$normalized_name] = [];
            }

            self::$product_category_lookup['exact'][$normalized_name][] = $term_id;
            self::$product_category_lookup['entries'][] = [
                'term_id' => $term_id,
                'normalized_name' => $normalized_name,
            ];
        }

        foreach (self::$product_category_lookup['exact'] as $name => $ids) {
            self::$product_category_lookup['exact'][$name] = array_values(array_unique(array_map('intval', $ids)));
        }

        return self::$product_category_lookup;
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
}
