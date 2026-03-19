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
        return ADPW_Settings_Save_Service::handle_save_request($tab, self::OPTION_KEY, self::NONCE_FIELD, self::NONCE_ACTION, [
            'actualizar_si' => 0,
            'actualizar_tam' => 0,
            'actualizar_cat' => 0,
            'actualizar_productos_desde_categorias' => 0,
            'categorias_por_lote' => 20,
        ]);
    }

    public static function render_page() {
        $active_tab = self::get_active_tab();

        if (isset($_POST['adpw_save_settings'])) {
            $result = self::handle_save_request($active_tab);
            echo '<div class="wrap">';
            echo '<h1>Configuración</h1>';
            if (!empty($result['error'])) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html($result['mensaje']) . '</p></div>';
            }
        } else {
            ADPW_Settings_Page_Renderer::render($active_tab, self::get(), self::NONCE_ACTION, self::NONCE_FIELD);
            return;
        }
        ADPW_Settings_Page_Renderer::render($active_tab, self::get(), self::NONCE_ACTION, self::NONCE_FIELD);
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
}
