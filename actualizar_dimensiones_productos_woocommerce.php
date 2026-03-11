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

    if (isset($_POST['guardar_metadata_categorias'])) {
        $resultado_guardado_metadata = ADPW_Category_Metadata_Manager::handle_save_request();

        if (!empty($resultado_guardado_metadata['error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($resultado_guardado_metadata['error']) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>' . esc_html($resultado_guardado_metadata['mensaje']) . '</p></div>';
            if (!empty($resultado_guardado_metadata['detalle_productos'])) {
                echo '<p>' . esc_html($resultado_guardado_metadata['detalle_productos']) . '</p>';
            }
            echo '</div>';
        }
    }

    if (isset($_POST['modificar_productos'])) {
        $resultados = modificar_productos(); // Capturamos los resultados
        // Mostramos los resultados
        echo '<div id="resultado_importacion">';
        if (!empty($resultados['error_general'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($resultados['error_general']) . '</p></div>';
            if (!empty($resultados['detalles'])) {
                echo '<ul>';
                foreach ($resultados['detalles'] as $detalle) {
                    echo '<li>' . esc_html($detalle) . '</li>';
                }
                echo '</ul>';
            }
        } elseif ($resultados) {
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
    echo '<input type="checkbox" name="actualizar_tam" value="1"> Actualizar dimensiones (peso/largo/ancho/profundidad) o tamaño si el Excel solo trae Categoría + tamaño <br />';
    echo '<input type="checkbox" name="actualizar_cat" value="1"> Actualizar tamaño (clase de envío) de los productos <br />';
    echo '<input type="submit" name="modificar_productos" value="Importar" class="button button-primary">';
    echo '</form>';

    ADPW_Category_Metadata_Manager::render_tree();

    echo '</div>';
}

final class ADPW_Category_Metadata_Manager {
    private const NONCE_ACTION = 'guardar_metadata_por_categoria_action';
    private const NONCE_FIELD = 'guardar_metadata_por_categoria_nonce';
    private const POST_FIELD_METADATA = 'metadata_categoria';
    private const POST_FIELD_UPDATE_PRODUCTS = 'actualizar_productos_desde_categorias';

    private const META_CLASS = '_adpw_categoria_clase_envio';
    private const META_WEIGHT = '_adpw_categoria_peso';
    private const META_HEIGHT = '_adpw_categoria_alto';
    private const META_WIDTH = '_adpw_categoria_ancho';
    private const META_DEPTH = '_adpw_categoria_profundidad';

    public static function handle_save_request() {
        if (!current_user_can('manage_options')) {
            return [
                'error' => 'No tenés permisos para guardar metadata por categoría.',
            ];
        }

        if (
            !isset($_POST[self::NONCE_FIELD]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            return [
                'error' => 'No se pudo validar la solicitud. Recargá la página e intentá nuevamente.',
            ];
        }

        $metadata_by_category = isset($_POST[self::POST_FIELD_METADATA]) ? (array) $_POST[self::POST_FIELD_METADATA] : [];
        $should_update_products = !empty($_POST[self::POST_FIELD_UPDATE_PRODUCTS]);
        $valid_shipping_slugs = self::get_valid_shipping_slugs();

        if ($valid_shipping_slugs === null) {
            return [
                'error' => 'No se pudieron validar las clases de envío disponibles.',
            ];
        }

        $saved_count = 0;
        $category_ids = [];

        foreach ($metadata_by_category as $category_id => $metadata) {
            $term_id = absint($category_id);
            if (!$term_id) {
                continue;
            }

            self::save_category_metadata($term_id, (array) $metadata, $valid_shipping_slugs);
            $saved_count++;
            $category_ids[] = $term_id;
        }

        $result = [
            'mensaje' => sprintf('Se actualizaron %d categorías.', $saved_count),
        ];

        if ($should_update_products && !empty($category_ids)) {
            $updated_products = self::update_products_using_most_specific_category($category_ids);
            $result['detalle_productos'] = sprintf('Productos actualizados desde metadata: %d.', $updated_products);
        }

        return $result;
    }

    public static function render_tree() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);
        if (is_wp_error($categories)) {
            echo '<div class="notice notice-error"><p>No se pudieron cargar las categorías de producto.</p></div>';
            return;
        }

        $shipping_classes = get_terms([
            'taxonomy' => 'product_shipping_class',
            'hide_empty' => false,
        ]);
        if (is_wp_error($shipping_classes)) {
            echo '<div class="notice notice-error"><p>No se pudieron cargar las clases de envío.</p></div>';
            return;
        }

        $categories_by_parent = [];
        foreach ($categories as $category) {
            $parent_id = (int) $category->parent;
            if (!isset($categories_by_parent[$parent_id])) {
                $categories_by_parent[$parent_id] = [];
            }
            $categories_by_parent[$parent_id][] = $category;
        }

        echo '<hr style="margin:24px 0;">';
        echo '<h2>Árbol de categorías y metadata</h2>';
        echo '<p>Editá por categoría: clase de envío, peso, alto, ancho y profundidad.</p>';

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        echo '<table class="widefat striped" style="max-width:1200px;">';
        echo '<thead><tr><th style="width:30%;">Categoría</th><th>Clase de envío</th><th>Peso</th><th>Alto</th><th>Ancho</th><th>Profundidad</th></tr></thead>';
        echo '<tbody>';

        if (empty($categories_by_parent[0])) {
            echo '<tr><td colspan="6">No hay categorías de producto para mostrar.</td></tr>';
        } else {
            self::render_category_rows(0, $categories_by_parent, $shipping_classes, 0);
        }

        echo '</tbody>';
        echo '</table>';

        echo '<p style="margin-top:12px;">';
        echo '<label><input type="checkbox" name="' . esc_attr(self::POST_FIELD_UPDATE_PRODUCTS) . '" value="1"> Actualizar productos con esta metadata</label>';
        echo '</p>';

        echo '<p style="margin-top:12px;">';
        echo '<button type="submit" name="guardar_metadata_categorias" class="button button-primary">Guardar metadata por categoría</button>';
        echo '</p>';
        echo '</form>';
    }

    private static function render_category_rows($parent_id, $categories_by_parent, $shipping_classes, $level) {
        if (empty($categories_by_parent[$parent_id])) {
            return;
        }

        foreach ($categories_by_parent[$parent_id] as $category) {
            $category_id = (int) $category->term_id;
            $meta = self::get_category_meta_values($category_id);

            echo '<tr>';
            echo '<td>';
            echo '<span style="display:inline-block;padding-left:' . esc_attr((string) ($level * 20)) . 'px;">';
            if ($level > 0) {
                echo esc_html(str_repeat('└ ', min(1, $level)));
            }
            echo esc_html($category->name);
            echo '</span>';
            echo '</td>';

            echo '<td>';
            echo '<select name="' . esc_attr(self::POST_FIELD_METADATA) . '[' . esc_attr((string) $category_id) . '][clase_envio]">';
            echo '<option value="">Sin clase</option>';
            foreach ($shipping_classes as $shipping_class) {
                $slug = (string) $shipping_class->slug;
                echo '<option value="' . esc_attr($slug) . '" ' . selected($meta['clase_envio'], $slug, false) . '>' . esc_html($shipping_class->name) . '</option>';
            }
            echo '</select>';
            echo '</td>';

            self::render_number_cell($category_id, 'peso', $meta['peso']);
            self::render_number_cell($category_id, 'alto', $meta['alto']);
            self::render_number_cell($category_id, 'ancho', $meta['ancho']);
            self::render_number_cell($category_id, 'profundidad', $meta['profundidad']);
            echo '</tr>';

            self::render_category_rows($category_id, $categories_by_parent, $shipping_classes, $level + 1);
        }
    }

    private static function render_number_cell($category_id, $field, $value) {
        echo '<td><input type="number" step="0.01" min="0" name="' . esc_attr(self::POST_FIELD_METADATA) . '[' . esc_attr((string) $category_id) . '][' . esc_attr($field) . ']" value="' . esc_attr($value) . '" style="width:100%;"></td>';
    }

    private static function get_category_meta_values($category_id) {
        return [
            'clase_envio' => (string) get_term_meta($category_id, self::META_CLASS, true),
            'peso' => (string) get_term_meta($category_id, self::META_WEIGHT, true),
            'alto' => (string) get_term_meta($category_id, self::META_HEIGHT, true),
            'ancho' => (string) get_term_meta($category_id, self::META_WIDTH, true),
            'profundidad' => (string) get_term_meta($category_id, self::META_DEPTH, true),
        ];
    }

    private static function get_valid_shipping_slugs() {
        $terms = get_terms([
            'taxonomy' => 'product_shipping_class',
            'hide_empty' => false,
            'fields' => 'slugs',
        ]);
        if (is_wp_error($terms)) {
            return null;
        }

        return array_map('strval', $terms);
    }

    private static function save_category_metadata($term_id, $metadata, $valid_shipping_slugs) {
        $shipping_slug = sanitize_title(wp_unslash((string) ($metadata['clase_envio'] ?? '')));

        if ($shipping_slug === '') {
            delete_term_meta($term_id, self::META_CLASS);
        } elseif (in_array($shipping_slug, $valid_shipping_slugs, true)) {
            update_term_meta($term_id, self::META_CLASS, $shipping_slug);
        }

        self::save_numeric_meta($term_id, self::META_WEIGHT, self::normalize_number($metadata['peso'] ?? ''));
        self::save_numeric_meta($term_id, self::META_HEIGHT, self::normalize_number($metadata['alto'] ?? ''));
        self::save_numeric_meta($term_id, self::META_WIDTH, self::normalize_number($metadata['ancho'] ?? ''));
        self::save_numeric_meta($term_id, self::META_DEPTH, self::normalize_number($metadata['profundidad'] ?? ''));
    }

    private static function normalize_number($value) {
        $text = trim(wp_unslash((string) $value));
        if ($text === '') {
            return '';
        }

        $text = str_replace(',', '.', $text);
        if (!is_numeric($text)) {
            return '';
        }

        return (string) max(0, (float) $text);
    }

    private static function save_numeric_meta($term_id, $meta_key, $value) {
        if ($value === '') {
            delete_term_meta($term_id, $meta_key);
            return;
        }

        update_term_meta($term_id, $meta_key, $value);
    }

    private static function update_products_using_most_specific_category($category_ids) {
        $category_ids = array_values(array_unique(array_map('absint', $category_ids)));
        if (empty($category_ids)) {
            return 0;
        }

        $product_ids = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [[
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $category_ids,
                'include_children' => false,
            ]],
        ]);

        $category_depth_cache = [];
        $shipping_class_cache = [];
        $updated_products = 0;

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            $product_term_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
            if (is_wp_error($product_term_ids) || empty($product_term_ids)) {
                continue;
            }

            $candidate_ids = array_values(array_intersect($category_ids, array_map('intval', $product_term_ids)));
            if (empty($candidate_ids)) {
                continue;
            }

            $selected_category_id = self::pick_deepest_category($candidate_ids, $category_depth_cache);
            if (!$selected_category_id) {
                continue;
            }

            if (self::apply_category_metadata_to_product($product, $selected_category_id, $shipping_class_cache)) {
                $updated_products++;
            }
        }

        return $updated_products;
    }

    private static function pick_deepest_category($category_ids, &$depth_cache) {
        $selected_id = 0;
        $max_depth = -1;

        foreach ($category_ids as $category_id) {
            if (!isset($depth_cache[$category_id])) {
                $depth_cache[$category_id] = count(get_ancestors($category_id, 'product_cat', 'taxonomy'));
            }

            if ($depth_cache[$category_id] > $max_depth) {
                $max_depth = $depth_cache[$category_id];
                $selected_id = (int) $category_id;
            }
        }

        return $selected_id;
    }

    private static function apply_category_metadata_to_product($product, $category_id, &$shipping_class_cache) {
        $meta = self::get_category_meta_values($category_id);
        $has_changes = false;

        $new_shipping_class_id = self::resolve_shipping_class_id($meta['clase_envio'], $shipping_class_cache);
        if ((int) $product->get_shipping_class_id() !== $new_shipping_class_id) {
            $product->set_shipping_class_id($new_shipping_class_id);
            $has_changes = true;
        }

        $has_changes = self::set_product_numeric_if_needed($product, 'weight', $meta['peso']) || $has_changes;
        $has_changes = self::set_product_numeric_if_needed($product, 'height', $meta['alto']) || $has_changes;
        $has_changes = self::set_product_numeric_if_needed($product, 'width', $meta['ancho']) || $has_changes;
        $has_changes = self::set_product_numeric_if_needed($product, 'length', $meta['profundidad']) || $has_changes;

        if ($has_changes) {
            $product->save();
        }

        return $has_changes;
    }

    private static function set_product_numeric_if_needed($product, $field, $new_value) {
        if ($new_value === '') {
            return false;
        }

        $getter = 'get_' . $field;
        $setter = 'set_' . $field;
        $current_value = (string) $product->{$getter}();
        $new_value = (string) $new_value;

        if ($current_value === $new_value) {
            return false;
        }

        $product->{$setter}($new_value);
        return true;
    }

    private static function resolve_shipping_class_id($shipping_slug, &$shipping_class_cache) {
        if ($shipping_slug === '') {
            return 0;
        }

        if (!array_key_exists($shipping_slug, $shipping_class_cache)) {
            $shipping_class = get_term_by('slug', $shipping_slug, 'product_shipping_class');
            $shipping_class_cache[$shipping_slug] = ($shipping_class && !is_wp_error($shipping_class))
                ? (int) $shipping_class->term_id
                : 0;
        }

        return $shipping_class_cache[$shipping_slug];
    }
}

function adpw_normalizar_encabezado($valor) {
    $texto = trim((string) $valor);
    $texto = str_replace("\xc2\xa0", ' ', $texto);
    $texto = strtolower(remove_accents($texto));
    $texto = preg_replace('/\s+/', ' ', $texto);
    return trim((string) $texto);
}

function modificar_productos() {
    if (!current_user_can('manage_options')) {
        return [
            'error_general' => 'No tienes permisos para realizar esta acción.',
        ];
    }

    if (empty($_FILES['archivo_excel']['tmp_name'])) {
        return [
            'error_general' => 'No se seleccionó ningún archivo.',
        ];
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
    $actualizar_tam = isset($_POST['actualizar_tam']);
    $actualizar_cat = isset($_POST['actualizar_cat']);

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

        $encabezados = array_map('adpw_normalizar_encabezado', $encabezados); // Normalizar acentos/espacios para mejorar match
        $columna_categoria = array_search('categoria', $encabezados);
        $columna_largo = array_search('largo (cm)', $encabezados);
        $columna_ancho = array_search('ancho (cm)', $encabezados);
        $columna_profundidad = array_search('profundidad (cm)', $encabezados);
        $columna_peso = array_search('peso (kg)', $encabezados);
        $columna_idcat = array_search('id categoria', $encabezados);
        $columna_tamaño = array_search('tamano', $encabezados);

        $faltan_columnas_dimensiones = ($columna_largo === false && $columna_ancho === false && $columna_profundidad === false && $columna_peso === false);
        $encabezados_requeridos = ['Categoría'];

        $solo_tamano_desde_excel = $actualizar_tam && $faltan_columnas_dimensiones && $columna_tamaño !== false;
        $actualizar_tam_dimensiones = $actualizar_tam && !$faltan_columnas_dimensiones;

        if ($actualizar_tam_dimensiones) {
            $encabezados_requeridos[] = 'Largo (cm) o Ancho (cm) o Profundidad (cm) o Peso (kg)';
        } elseif ($actualizar_tam && $columna_tamaño === false) {
            $encabezados_requeridos[] = 'Tamaño (cuando se usa importación solo Categoría + tamaño)';
        }
        if ($actualizar_cat) {
            $encabezados_requeridos[] = 'Tamaño';
        }

        if (
            $columna_categoria === false ||
            ($actualizar_tam && $faltan_columnas_dimensiones && $columna_tamaño === false) ||
            ($actualizar_cat && $columna_tamaño === false)
        ) {
            return [
                'error_general' => 'No se encontraron los encabezados esperados en el archivo Excel.',
                'detalles' => [
                    'Incluí al menos: ' . implode(', ', $encabezados_requeridos) . '.',
                    'Si el archivo es solo Categoría + tamaño, marcá "Actualizar tamaño (clase de envío)" o dejá marcada "Actualizar dimensiones..." para fallback automático.',
                ],
            ];
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
            $tamaño = ($columna_tamaño !== false) ? trim((string)$hoja->getCell([$columna_tamaño, $row])->getFormattedValue()) : null;
            
             //Check if the row is empty
            if(!empty($categoria) || !empty($largo) || !empty($ancho) || !empty( $profundidad) || !empty($peso) || !empty($idcat) || !empty($tamaño)){
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
            $detalles_errores[] = "Fila " . $row . ": Categoria leída = '" . $categoria . "', Largo = '" . $largo . "', Ancho = '" . $ancho . "', Profundidad = '" . $profundidad . "', Peso = '" . $peso . "', ID Cat = '" . $idcat . "', Tamaño = '" . $tamaño . "'";

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
                $res_dimensiones = [
                    'modificado' => false,
                    'actualizacion_total' => false,
                    'log_producto' => '',
                    'detalles_errores' => '',
                ];
                
                $clase_modificada = false; // Variable para rastrear si la clase de envío fue modificada

                if ($actualizar_tam_dimensiones && ($peso || $largo || $ancho || $profundidad)) {
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

                if (($actualizar_cat || $solo_tamano_desde_excel) && $tamaño) {
                    $res_clase_envio = actualizar_clase_envio($product, $tamaño, $actualizar_cat);
                    if ($res_clase_envio['detalles_errores']) {
                        $detalles_errores[] = $res_clase_envio['detalles_errores'];
                    }
                    if ($res_clase_envio['log_producto']) {
                        $detalles_errores[] = $res_clase_envio['log_producto'];
                    }
                    if ($res_clase_envio['modificado']) {
                        $clase_modificada = true; // Marcar que la clase fue modificada
                    }
                }

                // **Contar cambios en modificados_totales**
                if ($res_dimensiones['modificado'] || $clase_modificada) {
                    $modificados_totales++;
                }
            }
        }
    } catch (\Exception $e) {
        return [
            'error_general' => 'Error al leer el archivo Excel: ' . $e->getMessage(),
        ];
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

function actualizar_clase_envio($product, $tamaño, $actualizar_cat) {
    $modificado = false;
    $detalles_errores = '';
    $log_producto = '';

    // Obtener la clase de envío actual
    $clase_actual = $product->get_shipping_class();

    // Obtener la clase de envío por nombre o slug
    $shipping_class = get_term_by('name', $tamaño, 'product_shipping_class') ?: get_term_by('slug', $tamaño, 'product_shipping_class');

    if ($shipping_class && ($actualizar_cat || empty($clase_actual))) {
        // Actualizar la clase de envío
        $product->set_shipping_class_id($shipping_class->term_id);
        $modificado = true;
        $log_producto = "Clase de envío modificada para el producto " . $product->get_name() . " (ID: " . $product->get_id() . ") - Categoría: " . implode(', ', wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names'])) . "; Clase original: " . $clase_actual . "; Nueva clase: " . $shipping_class->name . ".";
        
        // Guardar el producto después de la modificación
        $product->save();
    } else {
        $detalles_errores = "Clase de envío '$tamaño' no encontrada o no se requiere actualización.";
    }

    return array(
        'modificado' => $modificado,
        'detalles_errores' => $detalles_errores,
        'log_producto' => $log_producto
    );
}
