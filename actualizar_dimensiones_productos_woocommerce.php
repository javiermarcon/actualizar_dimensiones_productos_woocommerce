<?php
/**
 * Plugin Name: Actualizar Dimensiones WooCommerce
 * Description: Importa dimensiones y peso desde un archivo Excel y solo actualiza los productos con valores incompletos.
 * Version: 1.2
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) {
    exit; // Evita acceso directo
}

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Agregar un menú en WooCommerce
add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Actualizar Dimensiones',
        'Actualizar Dimensiones',
        'manage_options',
        'actualizar-dimensiones',
        'importar_dimensiones_pagina'
    );
});

// Página de importación
function importar_dimensiones_pagina() {
    echo '<div class="wrap"><h1>Importar Dimensiones</h1>';
    
    if (isset($_POST['importar_dimensiones'])) {
        importar_dimensiones();
    }
    
    echo '<form method="post" enctype="multipart/form-data">
            <input type="file" name="archivo_excel" required>
            <input type="submit" name="importar_dimensiones" value="Importar" class="button button-primary">
          </form>
          <div id="resultado_importacion"></div>
          </div>';
}

function normalizar_texto($texto) {
    $texto = strtolower($texto); // Convertir a minúsculas
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto); // Eliminar acentos
    $texto = preg_replace('/[^a-z0-9 ]/', '', $texto); // Eliminar caracteres especiales
    return trim($texto);
}

// Función de importación
function importar_dimensiones() {
    if (!isset($_FILES['archivo_excel'])) {
        echo '<p style="color:red;">No se seleccionó ningún archivo.</p>';
        return;
    }

    $archivo = $_FILES['archivo_excel']['tmp_name'];
    $spreadsheet = IOFactory::load($archivo);
    $hoja = $spreadsheet->getActiveSheet();
    $datos = $hoja->toArray();

    global $wpdb;
    $modificados_totales = 0;
    $modificados_parciales = 0;
    $errores = 0;

    foreach ($datos as $i => $fila) {
        if ($i == 0) continue; // Saltar encabezados

        $categoria = $fila[0];
        $largo = $fila[1];
        $ancho = $fila[2];
        $profundidad = $fila[3];
        $peso = $fila[4];
        $idcat = $fila[5];

        echo "<p>Categoría: $categoria</p>";

        // Buscar categoría por nombre (sin mayúsculas ni acentos)
        $categoria_id = $wpdb->get_var($wpdb->prepare(
            "SELECT term_id FROM {$wpdb->terms} WHERE LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), ' ', '')) = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s, 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), ' ', ''))",
            $categoria
        ));

        // Si no encontró la categoría y hay ID de categoría en el Excel, buscar por ID
        if (!$categoria_id && $idcat !== false) {
            $categoria_id = intval($idcat);
            echo "<p style='color:red;'>Buscando id: $idcat</p>";
        }

        if (!$categoria_id) {
            echo "<p style='color:red;'>Categoría no encontrada: $categoria</p>";
            $errores++;
            continue;
        }

        // Obtener productos de la categoría
        $productos = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => [[
                'taxonomy' => 'product_cat',
                'field' => 'id',
                'terms' => $categoria_id,
            ]],
        ]);

        foreach ($productos as $producto) {
            $product_id = $producto->ID;
            $product = wc_get_product($product_id);
            $nombre_producto = $product->get_name();

            $peso_actual = $product->get_weight();
            $largo_actual = $product->get_length();
            $ancho_actual = $product->get_width();
            $profundidad_actual = $product->get_height();

            $actualizacion_total = false;
            $actualizacion_parcial = false;

            // Solo actualizar si algún valor no está seteado
            if (!$peso_actual || !$largo_actual || !$ancho_actual || !$profundidad_actual) {
                if (!$peso_actual) {
                    $product->set_weight($peso);
                    $actualizacion_parcial = true;
                }
                if (!$largo_actual) {
                    $product->set_length($largo);
                    $actualizacion_parcial = true;
                }
                if (!$ancho_actual) {
                    $product->set_width($ancho);
                    $actualizacion_parcial = true;
                }
                if (!$profundidad_actual) {
                    $product->set_height($profundidad);
                    $actualizacion_parcial = true;
                }

                if (!$peso_actual && !$largo_actual && !$ancho_actual && !$profundidad_actual) {
                    $actualizacion_total = true;
                }

                $product->save();

                if ($actualizacion_total) {
                    echo "<p style='color:green;'>Actualización TOTAL: Producto ID $product_id ($nombre_producto)</p>";
                    $modificados_totales++;
                } else {
                    echo "<p style='color:orange;'>Actualización PARCIAL: Producto ID $product_id ($nombre_producto)</p>";
                    $modificados_parciales++;
                }
            }
        }
    }

    echo "<p><strong>Importación completada: $modificados_totales actualizaciones totales, $modificados_parciales actualizaciones parciales, $errores errores.</strong></p>";
}
