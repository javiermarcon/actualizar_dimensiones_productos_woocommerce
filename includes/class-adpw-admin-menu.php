<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Admin_Menu {
    public static function register() {
        add_menu_page(
            'Actualizar Productos',
            'Actualizar Productos',
            'manage_options',
            'adpw-import',
            ['ADPW_Excel_Import_Page', 'render_page'],
            'dashicons-database-import',
            75
        );

        add_submenu_page(
            'adpw-import',
            'Importar Excel',
            'Importar Excel',
            'manage_options',
            'adpw-import',
            ['ADPW_Excel_Import_Page', 'render_page']
        );

        add_submenu_page(
            'adpw-import',
            'Árbol de Categorías',
            'Árbol de Categorías',
            'manage_options',
            'adpw-category-tree',
            ['ADPW_Category_Metadata_Page', 'render_page']
        );

        add_submenu_page(
            'adpw-import',
            'Configuración',
            'Configuración',
            'manage_options',
            'adpw-settings',
            ['ADPW_Settings', 'render_page']
        );
    }
}
