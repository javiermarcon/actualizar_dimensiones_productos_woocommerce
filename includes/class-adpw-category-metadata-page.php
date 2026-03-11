<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Metadata_Page {
    private const NONCE_ACTION = 'guardar_metadata_por_categoria_action';
    private const NONCE_FIELD = 'guardar_metadata_por_categoria_nonce';
    private const POST_FIELD_METADATA = 'metadata_categoria';
    private const POST_FIELD_UPDATE_PRODUCTS = 'actualizar_productos_desde_categorias';

    public static function render_page() {
        echo '<div class="wrap">';
        echo '<h1>Árbol de categorías</h1>';

        if (isset($_POST['guardar_metadata_categorias'])) {
            $result = self::handle_save_request();
            if (!empty($result['error'])) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html($result['mensaje']) . '</p>';
                if (!empty($result['detalle_productos'])) {
                    echo '<p>' . esc_html($result['detalle_productos']) . '</p>';
                }
                echo '</div>';
            }
        }

        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        $shipping_classes = get_terms([
            'taxonomy' => 'product_shipping_class',
            'hide_empty' => false,
        ]);

        if (is_wp_error($categories) || is_wp_error($shipping_classes)) {
            echo '<div class="notice notice-error"><p>No se pudieron cargar categorías o clases de envío.</p></div>';
            echo '</div>';
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

        echo '<p>Editá metadata por categoría: clase de envío, peso, alto, ancho y profundidad.</p>';

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

        echo '<p><button type="submit" name="guardar_metadata_categorias" class="button button-primary">Guardar metadata</button></p>';
        echo '</form>';
        echo '</div>';
    }

    private static function handle_save_request() {
        if (!current_user_can('manage_options')) {
            return ['error' => 'No tenés permisos para guardar metadata.'];
        }

        if (
            !isset($_POST[self::NONCE_FIELD]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            return ['error' => 'No se pudo validar la solicitud.'];
        }

        $metadata_by_category = isset($_POST[self::POST_FIELD_METADATA]) ? (array) $_POST[self::POST_FIELD_METADATA] : [];
        $should_update_products = !empty($_POST[self::POST_FIELD_UPDATE_PRODUCTS]);
        $valid_shipping_slugs = ADPW_Category_Metadata_Manager::get_valid_shipping_slugs();

        if ($valid_shipping_slugs === null) {
            return ['error' => 'No se pudieron validar las clases de envío disponibles.'];
        }

        $saved_count = 0;
        $category_ids = [];

        foreach ($metadata_by_category as $category_id => $metadata) {
            $term_id = absint($category_id);
            if (!$term_id) {
                continue;
            }

            ADPW_Category_Metadata_Manager::save_category_metadata($term_id, (array) $metadata, $valid_shipping_slugs);
            $saved_count++;
            $category_ids[] = $term_id;
        }

        $result = [
            'mensaje' => sprintf('Se actualizaron %d categorías.', $saved_count),
        ];

        if ($should_update_products && !empty($category_ids)) {
            $updated_products = ADPW_Category_Metadata_Manager::update_products_using_most_specific_category($category_ids);
            $result['detalle_productos'] = sprintf('Productos actualizados desde metadata: %d.', $updated_products);
        }

        return $result;
    }

    private static function render_category_rows($parent_id, $categories_by_parent, $shipping_classes, $level) {
        if (empty($categories_by_parent[$parent_id])) {
            return;
        }

        foreach ($categories_by_parent[$parent_id] as $category) {
            $category_id = (int) $category->term_id;
            $meta = ADPW_Category_Metadata_Manager::get_category_meta_values($category_id);

            echo '<tr>';
            echo '<td><span style="display:inline-block;padding-left:' . esc_attr((string) ($level * 20)) . 'px;">';
            if ($level > 0) {
                echo esc_html(str_repeat('└ ', min(1, $level)));
            }
            echo esc_html($category->name) . '</span></td>';

            echo '<td><select name="' . esc_attr(self::POST_FIELD_METADATA) . '[' . esc_attr((string) $category_id) . '][clase_envio]">';
            echo '<option value="">Sin clase</option>';
            foreach ($shipping_classes as $shipping_class) {
                $slug = (string) $shipping_class->slug;
                echo '<option value="' . esc_attr($slug) . '" ' . selected($meta['clase_envio'], $slug, false) . '>' . esc_html($shipping_class->name) . '</option>';
            }
            echo '</select></td>';

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
}
