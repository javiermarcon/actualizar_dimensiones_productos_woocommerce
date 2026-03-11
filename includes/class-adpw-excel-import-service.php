<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Import_Service {
    public static function process_upload($file, $settings) {
        if (!current_user_can('manage_options')) {
            return ['error_general' => 'No tienes permisos para realizar esta acción.'];
        }

        if (empty($file['tmp_name'])) {
            return ['error_general' => 'No se seleccionó ningún archivo.'];
        }

        set_time_limit(300);

        $actualizar_si = !empty($settings['actualizar_si']);
        $actualizar_tam = !empty($settings['actualizar_tam']);
        $actualizar_cat = !empty($settings['actualizar_cat']);

        $modificados_totales = 0;
        $modificados_parciales = 0;
        $errores = 0;
        $detalles_errores = [];
        $productos_modificados = [];

        try {
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $hoja = $spreadsheet->getActiveSheet();

            $encabezados = self::get_headers($hoja);

            $columna_categoria = array_search('categoria', $encabezados, true);
            $columna_largo = array_search('largo (cm)', $encabezados, true);
            $columna_ancho = array_search('ancho (cm)', $encabezados, true);
            $columna_profundidad = array_search('profundidad (cm)', $encabezados, true);
            $columna_peso = array_search('peso (kg)', $encabezados, true);
            $columna_idcat = array_search('id categoria', $encabezados, true);
            $columna_tamano = array_search('tamano', $encabezados, true);

            $faltan_columnas_dimensiones = ($columna_largo === false && $columna_ancho === false && $columna_profundidad === false && $columna_peso === false);
            $solo_tamano_desde_excel = $actualizar_tam && $faltan_columnas_dimensiones && $columna_tamano !== false;
            $actualizar_tam_dimensiones = $actualizar_tam && !$faltan_columnas_dimensiones;

            if ($validation_error = self::validate_headers($columna_categoria, $columna_tamano, $actualizar_tam, $actualizar_tam_dimensiones, $actualizar_cat, $faltan_columnas_dimensiones)) {
                return $validation_error;
            }

            $highestRow = $hoja->getHighestRow();
            $empty_row_count = 0;

            for ($row = 2; $row <= $highestRow; ++$row) {
                $categoria = ($columna_categoria !== false) ? trim((string) $hoja->getCell([$columna_categoria, $row])->getFormattedValue()) : null;
                $idcat = ($columna_idcat !== false) ? intval($hoja->getCell([$columna_idcat, $row])->getFormattedValue()) : 0;
                $largo = ($columna_largo !== false) ? floatval($hoja->getCell([$columna_largo, $row])->getFormattedValue()) : 0;
                $ancho = ($columna_ancho !== false) ? floatval($hoja->getCell([$columna_ancho, $row])->getFormattedValue()) : 0;
                $profundidad = ($columna_profundidad !== false) ? floatval($hoja->getCell([$columna_profundidad, $row])->getFormattedValue()) : 0;
                $peso = ($columna_peso !== false) ? floatval($hoja->getCell([$columna_peso, $row])->getFormattedValue()) : 0;
                $tamano = ($columna_tamano !== false) ? trim((string) $hoja->getCell([$columna_tamano, $row])->getFormattedValue()) : '';

                $row_is_empty = empty($categoria) && empty($idcat) && empty($largo) && empty($ancho) && empty($profundidad) && empty($peso) && empty($tamano);
                if ($row_is_empty) {
                    $empty_row_count++;
                    if ($empty_row_count >= 3) {
                        $detalles_errores[] = 'Detenido el procesamiento después de encontrar 3 filas vacías consecutivas.';
                        break;
                    }
                    continue;
                }
                $empty_row_count = 0;

                if (empty($categoria) && !$idcat) {
                    $errores++;
                    $detalles_errores[] = "Fila {$row}: Categoría vacía y sin ID categoría.";
                    continue;
                }

                $categoria_id = self::resolve_category_id($categoria, $idcat, $row, $detalles_errores);
                if (!$categoria_id) {
                    $errores++;
                    continue;
                }

                $productos = get_posts([
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'tax_query' => [[
                        'taxonomy' => 'product_cat',
                        'field' => 'id',
                        'terms' => $categoria_id,
                    ]],
                ]);

                if (empty($productos)) {
                    $errores++;
                    $detalles_errores[] = "Fila {$row}: No se encontraron productos en la categoría '{$categoria}'.";
                    continue;
                }

                foreach ($productos as $product_id) {
                    $product = wc_get_product($product_id);
                    if (!$product) {
                        $errores++;
                        $detalles_errores[] = "Fila {$row}: No se pudo cargar el producto con ID {$product_id}.";
                        continue;
                    }

                    $dim_res = [
                        'modificado' => false,
                        'actualizacion_total' => false,
                        'log_producto' => '',
                        'detalles_errores' => '',
                    ];
                    $clase_modificada = false;

                    if ($actualizar_tam_dimensiones && ($peso || $largo || $ancho || $profundidad)) {
                        $dim_res = self::update_dimensions($product, [
                            'peso' => $peso,
                            'largo' => $largo,
                            'ancho' => $ancho,
                            'profundidad' => $profundidad,
                        ], $actualizar_si, $categoria, $product_id);
                    }

                    if (($actualizar_cat || $solo_tamano_desde_excel) && $tamano !== '') {
                        $class_res = self::update_shipping_class($product, $tamano);
                        if (!empty($class_res['detalles_errores'])) {
                            $detalles_errores[] = $class_res['detalles_errores'];
                        }
                        if (!empty($class_res['log_producto'])) {
                            $detalles_errores[] = $class_res['log_producto'];
                        }
                        $clase_modificada = !empty($class_res['modificado']);
                    }

                    if (!empty($dim_res['modificado'])) {
                        if (!empty($dim_res['actualizacion_total'])) {
                            $modificados_totales++;
                        } else {
                            $modificados_parciales++;
                        }
                        if (!empty($dim_res['log_producto'])) {
                            $productos_modificados[] = $dim_res['log_producto'];
                        }
                        if (!empty($dim_res['detalles_errores'])) {
                            $detalles_errores[] = $dim_res['detalles_errores'];
                        }
                    }

                    if (!empty($dim_res['modificado']) || $clase_modificada) {
                        $modificados_totales++;
                    }
                }
            }
        } catch (\Exception $e) {
            return ['error_general' => 'Error al leer el archivo Excel: ' . $e->getMessage()];
        }

        return [
            'totales' => $modificados_totales,
            'parciales' => $modificados_parciales,
            'errores' => $errores,
            'detalles' => $detalles_errores,
            'productos_modificados' => $productos_modificados,
        ];
    }

    private static function get_headers($hoja) {
        $encabezados = [];
        $highestColumn = $hoja->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($col = 1; $col <= $highestColumnIndex; ++$col) {
            $cell = $hoja->getCell([$col, 1]);
            $encabezados[$col] = self::normalize_header($cell->getFormattedValue());
        }

        return $encabezados;
    }

    private static function normalize_header($valor) {
        $texto = trim((string) $valor);
        $texto = str_replace("\xc2\xa0", ' ', $texto);
        $texto = strtolower(remove_accents($texto));
        $texto = preg_replace('/\s+/', ' ', $texto);
        return trim((string) $texto);
    }

    private static function validate_headers($col_categoria, $col_tamano, $actualizar_tam, $actualizar_tam_dimensiones, $actualizar_cat, $faltan_dimensiones) {
        $required = ['Categoría'];

        if ($actualizar_tam_dimensiones) {
            $required[] = 'Largo (cm) o Ancho (cm) o Profundidad (cm) o Peso (kg)';
        } elseif ($actualizar_tam && $col_tamano === false) {
            $required[] = 'Tamaño (para importación Categoría + tamaño)';
        }

        if ($actualizar_cat) {
            $required[] = 'Tamaño';
        }

        if (
            $col_categoria === false ||
            ($actualizar_tam && $faltan_dimensiones && $col_tamano === false) ||
            ($actualizar_cat && $col_tamano === false)
        ) {
            return [
                'error_general' => 'No se encontraron los encabezados esperados en el archivo Excel.',
                'detalles' => [
                    'Incluí al menos: ' . implode(', ', $required) . '.',
                ],
            ];
        }

        return null;
    }

    private static function resolve_category_id($categoria, $idcat, $row, &$detalles_errores) {
        $categoria_id = false;

        if (!empty($categoria)) {
            $term = get_term_by('name', $categoria, 'product_cat');
            $categoria_id = $term ? $term->term_id : false;
        }

        if (!$categoria_id && $idcat) {
            $term = get_term($idcat, 'product_cat');
            $categoria_id = ($term && !is_wp_error($term)) ? $term->term_id : false;
            if ($categoria_id) {
                $detalles_errores[] = "Fila {$row}: Categoría encontrada por ID {$idcat} ({$term->name}).";
            }
        }

        if (!$categoria_id) {
            $nombre = (string) $categoria;
            $detalles_errores[] = "Fila {$row}: No se encontró la categoría '{$nombre}'.";
        }

        return $categoria_id;
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
