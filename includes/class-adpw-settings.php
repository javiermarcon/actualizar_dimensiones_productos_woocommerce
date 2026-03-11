<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Settings {
    private const OPTION_KEY = 'adpw_import_settings';
    private const NONCE_ACTION = 'adpw_save_settings';
    private const NONCE_FIELD = 'adpw_settings_nonce';

    public static function get() {
        $defaults = [
            'actualizar_si' => 0,
            'actualizar_tam' => 0,
            'actualizar_cat' => 0,
        ];

        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return wp_parse_args($saved, $defaults);
    }

    public static function handle_save_request() {
        if (!current_user_can('manage_options')) {
            return ['error' => 'No tenés permisos para guardar configuración.'];
        }

        if (
            !isset($_POST[self::NONCE_FIELD]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            return ['error' => 'No se pudo validar la solicitud.'];
        }

        $settings = [
            'actualizar_si' => !empty($_POST['actualizar_si']) ? 1 : 0,
            'actualizar_tam' => !empty($_POST['actualizar_tam']) ? 1 : 0,
            'actualizar_cat' => !empty($_POST['actualizar_cat']) ? 1 : 0,
        ];

        update_option(self::OPTION_KEY, $settings);

        return ['mensaje' => 'Configuración guardada correctamente.'];
    }

    public static function render_page() {
        echo '<div class="wrap">';
        echo '<h1>Configuración de importación</h1>';

        if (isset($_POST['adpw_save_settings'])) {
            $result = self::handle_save_request();
            if (!empty($result['error'])) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html($result['mensaje']) . '</p></div>';
            }
        }

        $settings = self::get();

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row">Actualizar siempre</th><td><label><input type="checkbox" name="actualizar_si" value="1" ' . checked(1, (int) $settings['actualizar_si'], false) . '> Forzar actualización de dimensiones.</label></td></tr>';
        echo '<tr><th scope="row">Actualizar dimensiones</th><td><label><input type="checkbox" name="actualizar_tam" value="1" ' . checked(1, (int) $settings['actualizar_tam'], false) . '> Usa columnas: peso/largo/ancho/profundidad. Si solo existe tamaño, usa fallback.</label></td></tr>';
        echo '<tr><th scope="row">Actualizar tamaño</th><td><label><input type="checkbox" name="actualizar_cat" value="1" ' . checked(1, (int) $settings['actualizar_cat'], false) . '> Actualiza clase de envío usando columna tamaño.</label></td></tr>';
        echo '</table>';

        echo '<p><button type="submit" name="adpw_save_settings" class="button button-primary">Guardar configuración</button></p>';
        echo '</form>';

        echo '</div>';
    }
}
