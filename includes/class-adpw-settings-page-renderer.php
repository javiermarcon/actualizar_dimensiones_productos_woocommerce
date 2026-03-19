<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Settings_Page_Renderer {
    public static function render($active_tab, $settings, $nonce_action, $nonce_field) {
        echo '<div class="wrap">';
        echo '<h1>Configuración</h1>';

        self::render_tabs($active_tab);

        echo '<form method="post">';
        wp_nonce_field($nonce_action, $nonce_field);
        echo '<input type="hidden" name="adpw_settings_tab" value="' . esc_attr($active_tab) . '">';

        echo '<table class="form-table" role="presentation">';
        if ($active_tab === 'common') {
            echo '<tr><th scope="row">Tamaño de lote</th><td><input type="number" min="1" step="1" name="categorias_por_lote" value="' . esc_attr((string) $settings['categorias_por_lote']) . '"> <p class="description">Cantidad máxima de elementos por ejecución de cron. En el paso 3 se aplica sobre productos, no sobre categorías.</p></td></tr>';
        } elseif ($active_tab === 'import') {
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

    public static function render_tabs($active_tab) {
        $tabs = [
            'common' => 'Común',
            'import' => 'Importación Excel',
            'tree' => 'Árbol de categorías',
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
