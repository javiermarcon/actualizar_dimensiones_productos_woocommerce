<?php
/**
 * Plugin Name: Actualizar Dimensiones Productos WooCommerce
 * Description: Plugin para actualizar las dimensiones y peso de los productos de WooCommerce desde un archivo Excel.
 * Version: 2.4
 * Author: Tu Nombre
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate; // Importante para obtener coordenadas

require_once __DIR__ . '/vendor/autoload.php'; // Asegúrate de tener la librería PhpSpreadsheet instalada

add_action('admin_menu', 'actualizar_dimensiones_menu');

function actualizar_dimensiones_menu() {
    add_menu_page(
        'Actualizar Dimensiones',
        'Actualizar Dimensiones',
        'manage_options',
        'actualizar-dimensiones',
        'actualizar_dimensiones_pagina',
        'dashicons-database-import',
        75
    );
}

function actualizar_dimensiones_pagina() {
    echo '<div class="wrap">';
    echo '<h1>Importar Dimensiones</h1>';

    if (isset($_POST['importar_dimensiones'])) {
        $resultados = importar_dimensiones(); // Capturamos los resultados
        // Mostramos los resultados
        echo '<div id="resultado_importacion">';
        if ($resultados) {
            echo '<p><strong>Resultados de la importación:</strong></p>';
            echo "<ul>";
            echo "<li>Actualizaciones Totales: " . esc_html($resultados['totales']) . "</li>";
            echo "<li>Actualizaciones Parciales: " . esc_html($resultados['parciales']) . "</li>";
            echo "<li>Errores: " . esc_html($resultados['errores']) . "</li>";
            if (!empty($resultados['detalles'])) {
                echo '<li><strong>Detalles de errores:</strong><ul>';
                foreach ($resultados['detalles'] as $detalle) {
                    echo '<li>' . esc_html($detalle) . '</li>';
                }
                echo '</ul></li>';
            }
            echo "</ul>";
        } else {
            echo '<p>Error al procesar el archivo.</p>';
        }
        echo '</div>';
    }

    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="archivo_excel" required>';
    echo '<input type="submit" name="importar_dimensiones" value="Importar" class="button button-primary">';
    echo '</form>';

    echo '</div>';
}

function importar_dimensiones() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción.');
    }

    if (empty($_FILES['archivo_excel']['tmp_name'])) {
        wp_die('No se seleccionó ningún archivo.');
    }

    $archivo = $_FILES['archivo_excel']['tmp_name'];

    // Aumentar el tiempo máximo de ejecución
    set_time_limit(300); // Establecer a 5 minutos (300 segundos)

    global $wpdb;
    $modificados_totales = 0;
    $modificados_parciales = 0;
    $errores = 0;
    $detalles_errores = []; // Array para almacenar detalles de los errores
    $datos = [];

    try {
        $spreadsheet = IOFactory::load($archivo);
        $hoja = $spreadsheet->getActiveSheet();

        // Leer los datos celda por celda
        $highestRow = $hoja->getHighestRow(); // Número de filas
        $highestColumn = $hoja->getHighestColumn(); // Letra de la columna
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn); // Indice de la columna

        $empty_row_count = 0; // Contador de filas vacías

        for ($row = 1; $row <= $highestRow; ++$row) {
            $datos[$row] = []; // Inicializar la fila
            $row_is_empty = true; // Asumir que la fila está vacía inicialmente

            for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                try {
                    $cell = $hoja->getCell([$col, $row]); // Usar getCell con un array
                    $value = $cell->getFormattedValue(); // Usar getFormattedValue()
                    $datos[$row][$col] = $value;
                    if (!empty(trim($value))) { // Si la celda no está vacía, la fila no está vacía
                        $row_is_empty = false;
                    }
                } catch (\Exception $e) {
                    $datos[$row][$col] = ''; // Si hay error al obtener el valor, asigna una cadena vacía
                    $detalles_errores[] = "Fila " . $row . ", Columna " . Coordinate::stringFromColumnIndex($col) . ": Error al leer la celda - " . $e->getMessage();
                }
            }

            if ($row_is_empty) {
                $empty_row_count++; // Incrementar el contador de filas vacías
                if ($empty_row_count >= 3) {
                    $detalles_errores[] = "Detenido el procesamiento después de encontrar 3 filas vacías consecutivas.";
                    break; // Detener el bucle si se encuentran 3 filas vacías
                }
            } else {
                $empty_row_count = 0; // Resetear el contador si la fila no está vacía
            }
        }
    } catch (\Exception $e) {
        wp_die('Error al leer el archivo Excel: ' . $e->getMessage());
        return false; // Importante: retornar false en caso de error
    }

    // Verificar si la primera fila contiene los encabezados esperados
    if(isset($datos[1])) {
        $encabezados = array_map('trim', $datos[1]); // Eliminar espacios en blanco alrededor de los encabezados
    } else {
        wp_die('No se encontraron encabezados en el archivo Excel.');
        return false;
    }

    $columna_categoria = array_search('Categoría', $encabezados);
    $columna_largo = array_search('Largo (cm)', $encabezados);
    $columna_ancho = array_search('Ancho (cm)', $encabezados);
    $columna_profundidad = array_search('Profundidad (cm)', $encabezados);
    $columna_peso = array_search('Peso (kg)', $encabezados);
    $columna_idcat = array_search('ID Categoría', $encabezados); // Nueva columna para ID de categoría

    // Si no se encuentran los encabezados, mostrar un error y salir
    if ($columna_categoria === false || $columna_largo === false || $columna_ancho === false || $columna_profundidad === false || $columna_peso === false) {
        wp_die('No se encontraron los encabezados esperados en el archivo Excel.  Asegúrate de que las columnas tengan los nombres correctos: Categoría, Largo (cm), Ancho (cm), Profundidad (cm), Peso (kg).');
    }

    foreach ($datos as $i => $fila) {
        if ($i == 1) continue; // Saltar encabezados
        if ($empty_row_count >= 3 && $i > $row) break; // Detener el bucle si se encontraron 3 filas vacías

        // Corrección crucial: usar los índices de columna correctos
        $categoria = isset($fila[$columna_categoria]) ? trim((string)$fila[$columna_categoria]) : null;
        $largo = isset($fila[$columna_largo]) ? floatval($fila[$columna_largo]) : 0;
        $ancho = isset($fila[$columna_ancho]) ? floatval($fila[$columna_ancho]) : 0;
        $profundidad = isset($fila[$columna_profundidad]) ? floatval($fila[$columna_profundidad]) : 0;
        $peso = isset($fila[$columna_peso]) ? floatval($fila[$columna_peso]) : 0;
        $idcat = ($columna_idcat !== false && isset($fila[$columna_idcat])) ? intval($fila[$columna_idcat]) : false;

        // Debug: Imprimir los valores que se están leyendo
        $detalles_errores[] = "Fila " . $i . ": Categoria leída = '" . $categoria . "', Largo = '" . $largo . "', Ancho = '" . $ancho . "', Profundidad = '" . $profundidad . "', Peso = '" . $peso . "', ID Cat = '" . $idcat . "'";

        // Verificar si $categoria es null antes de trim()
        if ($categoria === null) {
            $errores++;
            $detalles_errores[] = "Fila " . ($i) . ": Categoría es nula.";
            continue; // Saltar esta fila
        }

        // Si la categoría está vacía, pasar a la siguiente fila
        if (empty($categoria)) {
            $errores++;
            $detalles_errores[] = "Fila " . ($i) . ": Categoría está vacía.";
            continue;
        }

        // Buscar categoría por nombre (sin mayúsculas ni acentos)
        $term = get_term_by('name', $categoria, 'product_cat');
        $categoria_id = $term ? $term->term_id : false;

        if (!$categoria_id) {
            // Si no se encuentra la categoría, intenta buscar por ID
            if ($idcat) {
                $term = get_term($idcat, 'product_cat');
                $categoria_id = ($term && !is_wp_error($term)) ? $term->term_id : false;
                if ($categoria_id) {
                   $detalles_errores[] = "Fila " . ($i) . ": Categoría encontrada por ID " . $idcat . " (" . $term->name . ").";
                }
            }
        }

        if (!$categoria_id) {
            $errores++;
            $detalles_errores[] = "Fila " . ($i) . ": No se encontró la categoría '" . $categoria . "'.";
            continue;
        }

        // Obtener productos de la categoría
        $productos = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields'        => 'ids', // Only get post IDs
            'tax_query' => [[
                'taxonomy' => 'product_cat',
                'field' => 'id',
                'terms' => $categoria_id,
            ]],
        ]);

        if (empty($productos)) {
            $detalles_errores[] = "Fila " . ($i) . ": No se encontraron productos en la categoría '" . $categoria . "'.";
            $errores++;
            continue; // Skip if category has no products
        }

        foreach ($productos as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product) {
                $detalles_errores[] = "Fila " . ($i) . ": No se pudo cargar el producto con ID " . $product_id . ".";
                $errores++;
                continue; // Skip if product doesn't exist
            }

            $nombre_producto = $product->get_name();
            $peso_actual = $product->get_weight();
            $largo_actual = $product->get_length();
            $ancho_actual = $product->get_width();
            $profundidad_actual = $product->get_height();
            $actualizacion_total = false;
            $actualizacion_parcial = false;

            // Solo actualizar si algún valor no está seteado o si es cero
            if (!$peso_actual || $peso_actual == 0 || !$largo_actual || $largo_actual == 0 || !$ancho_actual || $ancho_actual == 0 || !$profundidad_actual || $profundidad_actual == 0) {
                if ((!$peso_actual || $peso_actual == 0) && $peso > 0) {
                    $product->set_weight($peso);
                    $actualizacion_parcial = true;
                }
                if ((!$largo_actual || $largo_actual == 0)  && $largo > 0) {
                    $product->set_length($largo);
                    $actualizacion_parcial = true;
                }
                if ((!$ancho_actual || $ancho_actual == 0) && $ancho > 0) {
                    $product->set_width($ancho);
                    $actualizacion_parcial = true;
                }
                if ((!$profundidad_actual || $profundidad_actual == 0) && $profundidad > 0) {
                    $product->set_height($profundidad);
                    $actualizacion_parcial = true;
                }

                if ((!$peso_actual || $peso_actual == 0) && (!$largo_actual || $largo_actual == 0) && (!$ancho_actual || $ancho_actual == 0) && (!$profundidad_actual || $profundidad_actual == 0)) {
                    $actualizacion_total = true;
                }

                $product->save();

                if ($actualizacion_total) {
                    $modificados_totales++;
                } else {
                    $modificados_parciales++;
                }
            }
        }
    }

    $resultados = array(
        'totales' => $modificados_totales,
        'parciales' => $modificados_parciales,
        'errores' => $errores,
        'detalles' => $detalles_errores,
    );

    return $resultados; // Retornamos el array con los resultados
}
