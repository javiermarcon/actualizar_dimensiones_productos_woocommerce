<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Settings_Save_Service {
    public static function handle_save_request($tab, $option_key, $nonce_field, $nonce_action, $defaults) {
        if (!current_user_can('manage_options')) {
            return ['error' => 'No tenés permisos para guardar configuración.'];
        }

        if (
            !isset($_POST[$nonce_field]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), $nonce_action)
        ) {
            return ['error' => 'No se pudo validar la solicitud.'];
        }

        $saved = get_option($option_key, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        $settings = wp_parse_args($saved, $defaults);

        if ($tab === 'common') {
            $settings['categorias_por_lote'] = max(1, absint($_POST['categorias_por_lote'] ?? 20));
        } elseif ($tab === 'import') {
            $settings['actualizar_si'] = !empty($_POST['actualizar_si']) ? 1 : 0;
            $settings['actualizar_tam'] = !empty($_POST['actualizar_tam']) ? 1 : 0;
            $settings['actualizar_cat'] = !empty($_POST['actualizar_cat']) ? 1 : 0;
        } elseif ($tab === 'tree') {
            $settings['actualizar_productos_desde_categorias'] = !empty($_POST['actualizar_productos_desde_categorias']) ? 1 : 0;
        }

        update_option($option_key, $settings);

        return ['mensaje' => 'Configuración guardada correctamente.'];
    }
}
