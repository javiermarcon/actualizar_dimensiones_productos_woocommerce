# AGENTS.md

Guia para agentes AI que trabajan en `/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce`.

## Objetivo del plugin

Este plugin resuelve dos flujos relacionados sobre WooCommerce:

- importar dimensiones y clase de envio de productos desde planillas Excel
- administrar metadata por categoria y aplicarla masivamente a productos

## Workflow obligatorio

1. PLAN
- Identificar si el cambio toca importacion Excel, arbol de categorias, configuracion o colas en segundo plano.
- Confirmar impacto en hooks WordPress, endpoints AJAX, `wp-cron` y opciones persistidas en `wp_options`.

2. PATCH
- Cambiar solo lo necesario.
- Mantener compatibilidad con:
  - bootstrap en [`actualizar_dimensiones_productos_woocommerce.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/actualizar_dimensiones_productos_woocommerce.php)
  - menues admin en [`includes/class-adpw-admin-menu.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-menu.php)
  - importacion en [`includes/class-adpw-excel-import-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-service.php)
  - metadata por categoria en [`includes/class-adpw-category-metadata-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-manager.php)

3. VERIFY
- Ejecutar al menos:
  - `composer test`
  - `composer lint` si el cambio toca PHP o configuracion del proyecto
- Si se tocaron colas o AJAX, verificar manualmente el flujo desde wp-admin.

## Invariantes funcionales

- No romper los menus:
  - `adpw-import`
  - `adpw-category-tree`
  - `adpw-settings`
- No romper los hooks AJAX:
  - `adpw_start_import`
  - `adpw_import_status`
  - `adpw_import_run_batch`
  - `adpw_category_update_status`
  - `adpw_category_update_run_batch`
- No romper los hooks `wp-cron`:
  - `adpw_process_import_batch`
  - `adpw_process_category_update_batch`
- No cambiar sin necesidad las option keys:
  - `adpw_import_settings`
  - `adpw_import_job`
  - `adpw_category_update_job`
- No cambiar sin motivo las meta keys de categoria:
  - `_adpw_categoria_clase_envio`
  - `_adpw_categoria_peso`
  - `_adpw_categoria_alto`
  - `_adpw_categoria_ancho`
  - `_adpw_categoria_profundidad`

## Riesgos que requieren cuidado extra

- Cambios en matching de categorias desde Excel pueden afectar importaciones masivas.
- Cambios en lotes o cron pueden dejar jobs colgados o duplicados.
- Cambios en metadata de categorias pueden impactar muchos productos a la vez.
- Cambios de encabezados soportados deben reflejarse en `README.md` y, si aplica, en `docs/`.

## Documentacion relacionada

- [`AI_CONTEXT.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/AI_CONTEXT.md)
- [`ARCHITECTURE.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/ARCHITECTURE.md)
- [`CODEBASE_ENTRYPOINTS.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/CODEBASE_ENTRYPOINTS.md)
- [`DOMAIN_MAP.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/DOMAIN_MAP.md)
- [`STYLE.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/STYLE.md)
- [`TESTING.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/TESTING.md)
