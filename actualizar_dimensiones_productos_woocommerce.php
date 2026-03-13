<?php
/**
 * Plugin Name: Actualizar Dimensiones Productos WooCommerce
 * Description: Plugin para actualizar dimensiones y tamaño de productos WooCommerce desde Excel y administrar metadata por categoría.
 * Version: 3.0
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/includes/class-adpw-background-job-utils.php';
require_once __DIR__ . '/includes/class-adpw-admin-job-progress-ui.php';
require_once __DIR__ . '/includes/class-adpw-settings.php';
require_once __DIR__ . '/includes/class-adpw-category-metadata-manager.php';
require_once __DIR__ . '/includes/class-adpw-category-update-queue-manager.php';
require_once __DIR__ . '/includes/class-adpw-category-metadata-page.php';
require_once __DIR__ . '/includes/class-adpw-excel-import-service.php';
require_once __DIR__ . '/includes/class-adpw-import-queue-manager.php';
require_once __DIR__ . '/includes/class-adpw-excel-import-page.php';
require_once __DIR__ . '/includes/class-adpw-admin-menu.php';

add_action('admin_menu', ['ADPW_Admin_Menu', 'register']);
ADPW_Import_Queue_Manager::register_hooks();
ADPW_Category_Update_Queue_Manager::register_hooks();
