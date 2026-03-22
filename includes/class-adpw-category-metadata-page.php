<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Metadata_Page {
    private const NONCE_ACTION = 'guardar_metadata_por_categoria_action';
    private const NONCE_FIELD = 'guardar_metadata_por_categoria_nonce';
    private const POST_FIELD_METADATA = 'metadata_categoria';
    private const MANUAL_NONCE_ACTION = 'adpw_category_tree_manual_batch';
    private const MANUAL_NONCE_FIELD = 'adpw_category_tree_manual_batch_nonce';

    public static function render_page() {
        echo '<div class="wrap">';
        echo '<h1>Árbol de categorías</h1>';

        $manual = ADPW_Category_Metadata_Page_Actions::handle_manual_batch(self::MANUAL_NONCE_ACTION, self::MANUAL_NONCE_FIELD);
        $manual_message = (string) ($manual['manual_message'] ?? '');
        if (!empty($manual['manual_error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($manual['manual_error']) . '</p></div>';
        }

        $is_save_request = (
            isset($_SERVER['REQUEST_METHOD']) &&
            strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST' &&
            isset($_POST[self::NONCE_FIELD])
        );

        if ($is_save_request) {
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

        $settings = ADPW_Settings::get();
        $auto_update_products = !empty($settings['actualizar_productos_desde_categorias']);
        $ajax_nonce = wp_create_nonce('adpw_category_update_ajax');
        if ($manual_message !== '') {
            echo '<div class="notice notice-success"><p>' . esc_html($manual_message) . '</p></div>';
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

        echo '<form id="adpw-category-metadata-form" method="post" action="">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<div id="adpw-metadata-delta-container"></div>';

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
        echo 'Actualizar productos al guardar metadata: <strong>' . ($auto_update_products ? 'Sí' : 'No') . '</strong>. ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=adpw-settings&tab=tree')) . '">Cambiar en Configuración</a>.';
        echo '</p>';
        echo '<noscript><p style="color:#b32d2e;">Para guardar cambios en el árbol es necesario tener JavaScript habilitado.</p></noscript>';

        echo '<p><input type="submit" name="guardar_metadata_categorias" value="Guardar metadata" class="button button-primary"></p>';
        echo '</form>';

        echo '<form method="post" style="margin-top:8px;">';
        wp_nonce_field(self::MANUAL_NONCE_ACTION, self::MANUAL_NONCE_FIELD);
        echo '<button type="submit" class="button" name="adpw_run_category_tree_batch" value="1">Procesar siguiente lote ahora</button>';
        echo '</form>';

        ADPW_Admin_Job_Progress_UI::render_progress_markup('adpw-category-tree');
        ADPW_Admin_Job_Progress_UI::render_status_snapshot('Estado actualización de productos', ADPW_Category_Update_Queue_Manager::get_job_snapshot(), 'adpw-category-tree');
        ADPW_Admin_Job_Progress_UI::render_polling_js([
            'prefix' => 'adpw-category-tree',
            'nonce' => $ajax_nonce,
            'statusAction' => 'adpw_category_update_status',
            'runBatchAction' => 'adpw_category_update_run_batch',
            'startFormId' => 'adpw-category-metadata-form',
            'startButtonId' => '',
            'validateInputId' => '',
            'startText' => 'Guardando metadata e iniciando actualización...',
            'completedText' => 'Actualización del árbol completada',
            'errorText' => 'La actualización del árbol falló.',
            'emptyInputMessage' => '',
        ]);
        self::render_payload_script();
        echo '</div>';
    }

    private static function handle_save_request() {
        return ADPW_Category_Metadata_Page_Actions::handle_save_request(
            self::NONCE_ACTION,
            self::NONCE_FIELD,
            self::POST_FIELD_METADATA
        );
    }
    private static function render_category_rows($parent_id, $categories_by_parent, $shipping_classes, $level) {
        if (empty($categories_by_parent[$parent_id])) {
            return;
        }

        foreach ($categories_by_parent[$parent_id] as $category) {
            $category_id = (int) $category->term_id;
            $meta = ADPW_Category_Metadata_Manager::get_category_meta_values($category_id);
            $product_count = isset($category->count) ? (int) $category->count : 0;
            $category_slug = isset($category->slug) ? (string) $category->slug : '';
            $category_link = admin_url('edit.php?post_type=product&product_cat=' . rawurlencode($category_slug));

            echo '<tr>';
            echo '<td><span style="display:inline-block;padding-left:' . esc_attr((string) ($level * 20)) . 'px;">';
            if ($level > 0) {
                echo esc_html(str_repeat('└ ', min(1, $level)));
            }
            echo '<a href="' . esc_url($category_link) . '">' . esc_html($category->name) . '</a></span>';
            echo ' <span style="color:#50575e;">(' . esc_html((string) $product_count) . ')</span></td>';

            echo '<td><select class="adpw-meta-input" name="' . esc_attr(self::POST_FIELD_METADATA) . '[' . esc_attr((string) $category_id) . '][clase_envio]" data-category-id="' . esc_attr((string) $category_id) . '" data-field="clase_envio" data-original="' . esc_attr($meta['clase_envio']) . '">';
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
        echo '<td><input type="number" step="0.01" min="0" class="adpw-meta-input" name="' . esc_attr(self::POST_FIELD_METADATA) . '[' . esc_attr((string) $category_id) . '][' . esc_attr($field) . ']" data-category-id="' . esc_attr((string) $category_id) . '" data-field="' . esc_attr($field) . '" data-original="' . esc_attr($value) . '" value="' . esc_attr($value) . '" style="width:100%;"></td>';
    }

    private static function render_payload_script() {
        echo '<script>';
        echo '(function(){';
        echo 'var form = document.getElementById(\"adpw-category-metadata-form\");';
        echo 'if (!form) { return; }';
        echo 'form.addEventListener(\"submit\", function(){';
        echo 'var deltaContainer = document.getElementById(\"adpw-metadata-delta-container\");';
        echo 'if (!deltaContainer) { return; }';
        echo 'deltaContainer.innerHTML = \"\";';
        echo 'var inputs = form.querySelectorAll(\".adpw-meta-input\");';
        echo 'var changedCount = 0;';
        echo 'for (var i = 0; i < inputs.length; i++) {';
        echo 'var el = inputs[i];';
        echo 'var original = el.getAttribute(\"data-original\") || \"\";';
        echo 'var current = el.value || \"\";';
        echo 'var name = el.getAttribute(\"name\");';
        echo 'el.disabled = true;';
        echo 'if (!name || current === original) { continue; }';
        echo 'var hidden = document.createElement(\"input\");';
        echo 'hidden.type = \"hidden\";';
        echo 'hidden.name = name;';
        echo 'hidden.value = current;';
        echo 'deltaContainer.appendChild(hidden);';
        echo 'changedCount++;';
        echo '}';
        echo 'if (changedCount === 0) {';
        echo 'var marker = document.createElement(\"input\");';
        echo 'marker.type = \"hidden\";';
        echo 'marker.name = \"adpw_no_changes\";';
        echo 'marker.value = \"1\";';
        echo 'deltaContainer.appendChild(marker);';
        echo '}';
        echo '});';
        echo '})();';
        echo '</script>';
    }
}
