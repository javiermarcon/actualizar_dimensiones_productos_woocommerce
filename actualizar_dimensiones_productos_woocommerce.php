<?php
/**
 * Plugin Name: Actualizar Dimensiones WooCommerce
 * Description: Importa dimensiones y peso desde un archivo Excel y solo actualiza los productos sin estos valores seteados.
 * Version: 1.1
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) {
    echo "ABSPATH no definido";
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
    $modificados = 0;
    $errores = 0;

    foreach ($datos as $i => $fila) {
        if ($i == 0) continue; // Saltar encabezados
        
        list($categoria, $largo, $ancho, $profundidad, $peso) = $fila;

        $categoria_id = $wpdb->get_var($wpdb->prepare(
            "SELECT term_id FROM {$wpdb->terms} WHERE name = %s",
            $categoria
        ));

        if (!$categoria_id) {
            echo "<p style='color:red;'>Categoría no encontrada: $categoria</p>";
            $errores++;
            continue;
        }

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
            
            $peso_actual = $product->get_weight();
            $largo_actual = $product->get_length();
            $ancho_actual = $product->get_width();
            $profundidad_actual = $product->get_height();

            if (!$peso_actual && !$largo_actual && !$ancho_actual && !$profundidad_actual) {
                $product->set_weight($peso);
                $product->set_length($largo);
                $product->set_width($ancho);
                $product->set_height($profundidad);
                $product->save();
                $modificados++;
                echo "<p style='color:green;'>Producto ID $product_id actualizado.</p>";
            }
        }
    }

    echo "<p><strong>Importación completada: $modificados productos actualizados, $errores errores.</strong></p>";
}
