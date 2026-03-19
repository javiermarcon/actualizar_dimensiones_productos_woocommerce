# ARCHITECTURE.md

## Vista general

La arquitectura esta separada por flujo funcional:

- bootstrap y registro de hooks
- UI admin
- importacion Excel
- metadata de categorias
- colas en segundo plano

## Bootstrap

[`actualizar_dimensiones_productos_woocommerce.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/actualizar_dimensiones_productos_woocommerce.php) carga el autoloader, requiere las clases del plugin y registra:

- `admin_menu`
- hooks AJAX y cron de importacion
- hooks AJAX y cron de actualizacion por categorias

## UI admin

- [`includes/class-adpw-admin-menu.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-menu.php)
  - define menus y submenus
- [`includes/class-adpw-admin-job-progress-ui.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-job-progress-ui.php)
  - renderiza cajas de estado, progreso y polling JS
- [`includes/class-adpw-excel-import-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-page.php)
  - pantalla de carga de planilla y monitoreo del job
- [`includes/class-adpw-category-metadata-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-page.php)
  - pantalla del arbol de categorias
- [`includes/class-adpw-settings.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-settings.php)
  - pantalla y persistencia de configuracion

## Importacion Excel

### Orquestacion

[`includes/class-adpw-import-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-import-queue-manager.php) crea y procesa el job `adpw_import_job`.

### Servicio de negocio

[`includes/class-adpw-excel-import-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-service.php) hace tres etapas:

1. `parse_sheet`
2. `save_category_meta`
3. `update_products`

La etapa intermedia usa un JSON temporal en uploads para desacoplar parseo de escritura masiva.

Soporte extraido:

- [`includes/class-adpw-excel-import-support.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-support.php)
  - encabezados, columnas, validacion y matching de categorias
- [`includes/class-adpw-excel-product-update-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-product-update-service.php)
  - actualizacion de dimensiones y clase de envio

## Metadata de categorias

### Servicio de negocio

[`includes/class-adpw-category-metadata-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-manager.php) centraliza:

- lectura/escritura de `term_meta`
- cola de productos afectados
- aplicacion de metadata a productos
- eleccion de la categoria mas profunda

[`includes/class-adpw-category-metadata-save-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-save-service.php) encapsula el guardado desde request y el disparo opcional del job de actualizacion.

### Cola

[`includes/class-adpw-category-update-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-update-queue-manager.php) crea y procesa `adpw_category_update_job`.

## Procesamiento en segundo plano

[`includes/class-adpw-background-job-utils.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-background-job-utils.php) encapsula utilidades comunes:

- leer/escribir jobs en `wp_options`
- append de debug log
- schedule del siguiente lote
- armado de summary para UI

## Decisiones actuales

- Persistencia simple en options y term meta en vez de tablas custom.
- Polling AJAX desde wp-admin para ver progreso.
- `wp_schedule_single_event()` para lotes sucesivos, no un cron recurrente propio.
