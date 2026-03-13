<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Settings {
    private const OPTION_KEY = 'adpw_import_settings';
    private const NONCE_ACTION = 'adpw_save_settings';
    private const NONCE_FIELD = 'adpw_settings_nonce';
    private const TAB_COMMON = 'common';
    private const TAB_IMPORT = 'import';
    private const TAB_TREE = 'tree';

    public static function get() {
        $defaults = [
            'actualizar_si' => 0,
            'actualizar_tam' => 0,
            'actualizar_cat' => 0,
            'actualizar_productos_desde_categorias' => 0,
            'categorias_por_lote' => 20,
        ];

        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return wp_parse_args($saved, $defaults);
    }

    public static function handle_save_request($tab) {
        if (!current_user_can('manage_options')) {
            return ['error' => 'No tenés permisos para guardar configuración.'];
        }

        if (
            !isset($_POST[self::NONCE_FIELD]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            return ['error' => 'No se pudo validar la solicitud.'];
        }

        $settings = self::get();

        if ($tab === self::TAB_COMMON) {
            $settings['categorias_por_lote'] = max(1, absint($_POST['categorias_por_lote'] ?? 20));
        } elseif ($tab === self::TAB_IMPORT) {
            $settings['actualizar_si'] = !empty($_POST['actualizar_si']) ? 1 : 0;
            $settings['actualizar_tam'] = !empty($_POST['actualizar_tam']) ? 1 : 0;
            $settings['actualizar_cat'] = !empty($_POST['actualizar_cat']) ? 1 : 0;
        } elseif ($tab === self::TAB_TREE) {
            $settings['actualizar_productos_desde_categorias'] = !empty($_POST['actualizar_productos_desde_categorias']) ? 1 : 0;
        }

        update_option(self::OPTION_KEY, $settings);

        return ['mensaje' => 'Configuración guardada correctamente.'];
    }

    public static function render_page() {
        $active_tab = self::get_active_tab();

        echo '<div class="wrap">';
        echo '<h1>Configuración</h1>';

        if (isset($_POST['adpw_save_settings'])) {
            $result = self::handle_save_request($active_tab);
            if (!empty($result['error'])) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html($result['mensaje']) . '</p></div>';
            }
        }

        $settings = self::get();
        self::render_tabs($active_tab);

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<input type="hidden" name="adpw_settings_tab" value="' . esc_attr($active_tab) . '">';

        echo '<table class="form-table" role="presentation">';
        if ($active_tab === self::TAB_COMMON) {
            echo '<tr><th scope="row">Tamaño de lote</th><td><input type="number" min="1" step="1" name="categorias_por_lote" value="' . esc_attr((string) $settings['categorias_por_lote']) . '"> <p class="description">Cantidad máxima de elementos por ejecución de cron. En el paso 3 se aplica sobre productos, no sobre categorías.</p></td></tr>';
        } elseif ($active_tab === self::TAB_IMPORT) {
            echo '<tr><th scope="row">Actualizar siempre</th><td><label><input type="checkbox" name="actualizar_si" value="1" ' . checked(1, (int) $settings['actualizar_si'], false) . '> Forzar actualización de dimensiones.</label></td></tr>';
            echo '<tr><th scope="row">Actualizar dimensiones</th><td><label><input type="checkbox" name="actualizar_tam" value="1" ' . checked(1, (int) $settings['actualizar_tam'], false) . '> Usa columnas: peso/largo/ancho/profundidad. Si solo existe tamaño, usa fallback.</label></td></tr>';
            echo '<tr><th scope="row">Actualizar tamaño</th><td><label><input type="checkbox" name="actualizar_cat" value="1" ' . checked(1, (int) $settings['actualizar_cat'], false) . '> Actualiza clase de envío usando columna tamaño.</label></td></tr>';
        } else {
            echo '<tr><th scope="row">Actualizar productos al guardar metadata</th><td><label><input type="checkbox" name="actualizar_productos_desde_categorias" value="1" ' . checked(1, (int) $settings['actualizar_productos_desde_categorias'], false) . '> Cuando guardás cambios en el árbol de categorías, aplicar esa metadata también a los productos afectados.</label></td></tr>';
        }
        echo '</table>';

        echo '<p><button type="submit" name="adpw_save_settings" class="button button-primary">Guardar configuración</button></p>';
        echo '</form>';

        echo '</div>';
    }

    private static function get_active_tab() {
        $tab = isset($_REQUEST['tab']) ? sanitize_key((string) $_REQUEST['tab']) : '';
        if ($tab === '') {
            $tab = isset($_POST['adpw_settings_tab']) ? sanitize_key((string) $_POST['adpw_settings_tab']) : self::TAB_COMMON;
        }

        $allowed_tabs = [
            self::TAB_COMMON,
            self::TAB_IMPORT,
            self::TAB_TREE,
        ];

        if (!in_array($tab, $allowed_tabs, true)) {
            return self::TAB_COMMON;
        }

        return $tab;
    }

    private static function render_tabs($active_tab) {
        $tabs = [
            self::TAB_COMMON => 'Común',
            self::TAB_IMPORT => 'Importación Excel',
            self::TAB_TREE => 'Árbol de categorías',
        ];

        echo '<nav class="nav-tab-wrapper" style="margin-bottom:16px;">';
        foreach ($tabs as $tab => $label) {
            $url = add_query_arg([
                'page' => 'adpw-settings',
                'tab' => $tab,
            ], admin_url('admin.php'));
            $class = ($tab === $active_tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }
}
