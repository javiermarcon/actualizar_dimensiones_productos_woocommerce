<?php
/**
 * Plugin Name: Actualizar Dimensiones Productos WooCommerce
 * Description: Plugin para actualizar las dimensiones y peso de los productos de WooCommerce desde un archivo Excel.
 * Version: 2.6
 * Author: Tu Nombre
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate; // Importante para obtener coordenadas

require_once __DIR__ . '/vendor/autoload.php'; // Asegúrate de tener la librería PhpSpreadsheet instalada

add_action('admin_menu', 'actualizar_dimensiones_menu');

function actualizar_dimensiones_menu() {
    add_menu_page(
        'Actualizar Productos',
        'Actualizar Productos',
        'manage_options',
        'actualizar-productos',
        'actualizar_productos_pagina',
        'dashicons-database-import',
        75
    );
}

function actualizar_productos_pagina() {
    echo '<div class="wrap">';
    echo '<h1>Importar Dimensiones</h1>';

    if (isset($_POST['modificar_productos'])) {
        $resultados = modificar_productos(); // Capturamos los resultados
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
             if (!empty($resultados['productos_modificados'])) {
                echo '<li><strong>Productos Modificados:</strong><ul>';
                foreach ($resultados['productos_modificados'] as $producto_modificado) {
                    echo '<li>' . esc_html($producto_modificado) . '</li>';
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
    echo '<input type="file" name="archivo_excel" required> <br />';
    echo '<input type="checkbox" name="actualizar_si" value="1"> Actualizar siempre <br />';
    echo '<input type="submit" name="modificar_productos" value="Importar" class="button button-primary">';
    echo '</form>';

    echo '</div>';
}

function modificar_productos() {
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
	$productos_modificados = []; // Array para trackear que productos se modificaron.

    $actualizar_si = isset($_POST['actualizar_si']); // Captura el estado del checkbox

    try {
        $spreadsheet = IOFactory::load($archivo);
        $hoja = $spreadsheet->getActiveSheet();

        // **1. Leer la primera fila (encabezados) y determinar los índices de las columnas**
        $encabezados = [];
        $highestColumn = $hoja->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($col = 1; $col <= $highestColumnIndex; ++$col) {
            $cell = $hoja->getCell([$col, 1]); // Leer solo la primera fila
            $encabezados[$col] = trim($cell->getFormattedValue());
        }

        $columna_categoria = array_search('Categoría', $encabezados);
        $columna_largo = array_search('Largo (cm)', $encabezados);
        $columna_ancho = array_search('Ancho (cm)', $encabezados);
        $columna_profundidad = array_search('Profundidad (cm)', $encabezados);
        $columna_peso = array_search('Peso (kg)', $encabezados);
        $columna_idcat = array_search('ID Categoría', $encabezados);

       // Si no se encuentran los encabezados, mostrar un error y salir
        if ($columna_categoria === false || $columna_largo === false || $columna_ancho === false || $columna_profundidad === false || $columna_peso === false) {
            wp_die('No se encontraron los encabezados esperados en el archivo Excel.  Asegúrate de que las columnas tengan los nombres correctos: Categoría, Largo (cm), Ancho (cm), Profundidad (cm), Peso (kg).');
        }


        // **2. Iterar sobre las filas de datos, usando los índices de columna**
        $highestRow = $hoja->getHighestRow();
        $empty_row_count = 0;

        for ($row = 2; $row <= $highestRow; ++$row) { // Empezar desde la segunda fila (datos)
            $row_is_empty = true;

            // Leer los valores de las celdas usando los índices de columna
            $categoria = ($columna_categoria !== false) ? trim((string)$hoja->getCell([$columna_categoria, $row])->getFormattedValue()) : null;
            $idcat = ($columna_idcat !== false) ? intval($hoja->getCell([$columna_idcat, $row])->getFormattedValue()) : false;
            $largo = ($columna_largo !== false) ? floatval($hoja->getCell([$columna_largo, $row])->getFormattedValue()) : 0;
            $ancho = ($columna_ancho !== false) ? floatval($hoja->getCell([$columna_ancho, $row])->getFormattedValue()) : 0;
            $profundidad = ($columna_profundidad !== false) ? floatval($hoja->getCell([$columna_profundidad, $row])->getFormattedValue()) : 0;
            $peso = ($columna_peso !== false) ? floatval($hoja->getCell([$columna_peso, $row])->getFormattedValue()) : 0;
            
             //Check if the row is empty
            if(!empty($categoria) || !empty($largo) || !empty($ancho) || !empty( $profundidad) || !empty($peso) || !empty($idcat)){
                    $row_is_empty = false;
            }

            if ($row_is_empty) {
                $empty_row_count++;
                if ($empty_row_count >= 3) {
                    $detalles_errores[] = "Detenido el procesamiento después de encontrar 3 filas vacías consecutivas.";
                    break;
                }
                continue; // Skip processing empty rows
            } else {
                $empty_row_count = 0; // Resetear el contador si la fila no está vacía
            }

            // Debug: Imprimir los valores que se están leyendo
            $detalles_errores[] = "Fila " . $row . ": Categoria leída = '" . $categoria . "', Largo = '" . $largo . "', Ancho = '" . $ancho . "', Profundidad = '" . $profundidad . "', Peso = '" . $peso . "', ID Cat = '" . $idcat . "'";

            // Verificar si $categoria es null antes de trim()
            if ($categoria === null) {
                $errores++;
                $detalles_errores[] = "Fila " . ($row) . ": Categoría es nula.";
                continue; // Saltar esta fila
            }

            // Si la categoría está vacía, pasar a la siguiente fila
            if (empty($categoria)) {
                $errores++;
                $detalles_errores[] = "Fila " . ($row) . ": Categoría está vacía.";
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
                       $detalles_errores[] = "Fila " . ($row) . ": Categoría encontrada por ID " . $idcat . " (" . $term->name . ").";
                    }
                }
            }

            if (!$categoria_id) {
                $errores++;
                $detalles_errores[] = "Fila " . ($row) . ": No se encontró la categoría '" . $categoria . "'.";
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
                $detalles_errores[] = "Fila " . ($row) . ": No se encontraron productos en la categoría '" . $categoria . "'.";
                $errores++;
                continue; // Skip if category has no products
            }

            foreach ($productos as $product_id) {
                $product = wc_get_product($product_id);

                if (!$product) {
                    $detalles_errores[] = "Fila " . ($row) . ": No se pudo cargar el producto con ID " . $product_id . ".";
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
                $log_producto = "Producto: " . $nombre_producto . " (ID: " . $product_id . ") - ";
                
                if ($peso || $largo || $ancho || $profundidad) {
                    $res_dimensiones = actualizar_dimensiones($product, $peso_actual, $largo_actual, $ancho_actual, $profundidad_actual, $peso, $largo, $ancho, $profundidad, $actualizar_si, $categoria, $nombre_producto, $product_id);
                }

                if ($res_dimensiones && $res_dimensiones['modificado']) {
                    if ($res_dimensiones['actualizacion_total']) {
                        $modificados_totales++;
                    } else {
                        $modificados_parciales++;
                    }
                    $productos_modificados[] = $res_dimensiones['log_producto'];
                    if ($res_dimensiones['detalles_errores']) {
                        $detalles_errores[] = $res_dimensiones['detalles_errores'];
                    }
                    
                } 

            }
        }
    } catch (\Exception $e) {
        wp_die('Error al leer el archivo Excel: ' . $e->getMessage());
        return false; // Importante: retornar false en caso de error
    }

   $resultados = array(
        'totales' => $modificados_totales,
        'parciales' => $modificados_parciales,
        'errores' => $errores,
        'detalles' => $detalles_errores,
         'productos_modificados' =>  $productos_modificados,
    );

    return $resultados;
}

function actualizar_dimensiones($product, $peso_actual, $largo_actual, $ancho_actual, $profundidad_actual, $peso, $largo, $ancho, $profundidad, $actualizar_si, $categoria, $nombre_producto, $product_id) {
    $modificado = false;
    $actualizacion_parcial = false;
    $actualizacion_total = false;
    $detalles_errores = '';
    $log_producto = '';
    $detalles_errores = '';
    // Actualizar siempre si el checkbox está marcado
    if ($actualizar_si || !$peso_actual || $peso_actual == 0 || (!$largo_actual || $largo_actual == 0) || (!$ancho_actual || $ancho_actual == 0) || (!$profundidad_actual || $profundidad_actual == 0)) {
        
        $log_producto .= "Actualizando el producto " . $nombre_producto . " (" . $product_id . ") de la categoria " . $categoria . "; ";

        if (($actualizar_si || !$peso_actual || $peso_actual == 0) && $peso > 0) {
            $product->set_weight($peso);
            $actualizacion_parcial = true;
            $log_producto .= "Peso actualizado de " . $peso_actual . " a " . $peso . "; ";
            $modificado = true;
        }
        if (($actualizar_si || !$largo_actual || $largo_actual == 0)  && $largo > 0) {
            $product->set_length($largo);
            $actualizacion_parcial = true;
            $log_producto .= "Largo actualizado de " . $largo_actual . " a " . $largo . "; ";
                $modificado = true;
        }
        if (($actualizar_si || !$ancho_actual || $ancho_actual == 0) && $ancho > 0) {
            $product->set_width($ancho);
            $actualizacion_parcial = true;
            $log_producto .= "Ancho actualizado de " . $ancho_actual . " a " . $ancho . "; ";
                $modificado = true;
        }
        if (($actualizar_si || !$profundidad_actual || $profundidad_actual == 0) && $profundidad > 0) {
            $product->set_height($profundidad);
            $actualizacion_parcial = true;
            $log_producto .= "Profundidad actualizada de " . $profundidad_actual . " a " . $profundidad . "; ";
                $modificado = true;
        }

        if ($actualizar_si || (!$peso_actual || $peso_actual == 0) && (!$largo_actual || $largo_actual == 0) && (!$ancho_actual || $ancho_actual == 0) && (!$profundidad_actual || $profundidad_actual == 0)) {
            $actualizacion_total = true;

        }
        
        if($modificado){
            $product->save();
        }
    } else {
        $detalles_errores = "Fila " . $row . ": Producto " . $nombre_producto . " (ID: " . $product_id . ") no necesita actualización.";
    }

    return array(
        'modificado' => $modificado, 
        'actualizacion_parcial' => $actualizacion_parcial, 
        'actualizacion_total' => $actualizacion_total, 
        'log_producto' => $log_producto,
        'detalles_errores' => $detalles_errores
    );
}