# CODEBASE_ENTRYPOINTS.md

## Entry points principales

### Bootstrap del plugin

- [`actualizar_dimensiones_productos_woocommerce.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/actualizar_dimensiones_productos_woocommerce.php)

Responsabilidades:

- cargar autoload
- requerir clases
- registrar `admin_menu`
- registrar hooks de importacion y categoria

### Menus admin

- [`includes/class-adpw-admin-menu.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-menu.php)

Slugs:

- `adpw-import`
- `adpw-category-tree`
- `adpw-settings`

### AJAX importacion

- [`includes/class-adpw-import-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-import-queue-manager.php)

Actions:

- `wp_ajax_adpw_start_import`
- `wp_ajax_adpw_import_status`
- `wp_ajax_adpw_import_run_batch`

### AJAX actualizacion por categorias

- [`includes/class-adpw-category-update-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-update-queue-manager.php)

Actions:

- `wp_ajax_adpw_category_update_status`
- `wp_ajax_adpw_category_update_run_batch`

### Cron hooks

- importacion:
  - `adpw_process_import_batch`
- arbol de categorias:
  - `adpw_process_category_update_batch`

### Formularios POST admin

- importacion:
  - [`includes/class-adpw-excel-import-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-page.php)
- arbol de categorias:
  - [`includes/class-adpw-category-metadata-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-page.php)
- settings:
  - [`includes/class-adpw-settings.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-settings.php)
