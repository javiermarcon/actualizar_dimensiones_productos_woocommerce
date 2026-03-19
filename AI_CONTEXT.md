# AI_CONTEXT.md

## Resumen rapido

Plugin WordPress para WooCommerce con dos capacidades principales:

- importar dimensiones y clase de envio de productos desde Excel
- guardar metadata por categoria y aplicarla a productos en segundo plano

## Limites del sistema

- No hay tablas custom.
- La persistencia propia del plugin vive en:
  - `wp_options` para settings y jobs
  - `term_meta` para metadata de categorias
- El plugin asume WooCommerce activo porque usa `wc_get_product()` y taxonomias de WooCommerce.

## Objetos persistidos

- Opcion de settings:
  - `adpw_import_settings`
- Job de importacion:
  - `adpw_import_job`
- Job de arbol de categorias:
  - `adpw_category_update_job`
- Meta de categoria:
  - `_adpw_categoria_clase_envio`
  - `_adpw_categoria_peso`
  - `_adpw_categoria_alto`
  - `_adpw_categoria_ancho`
  - `_adpw_categoria_profundidad`

## Flujos principales

### Importacion Excel

1. Usuario sube archivo desde wp-admin.
2. `ADPW_Import_Queue_Manager` crea job en `adpw_import_job`.
3. `ADPW_Excel_Import_Service` parsea planilla y arma mapa temporal por categoria.
4. Se guarda metadata por categoria.
5. Se construye cola de productos afectados.
6. `wp-cron` procesa lotes hasta completar.

### Arbol de categorias

1. Usuario edita metadata por categoria.
2. Se guarda en `term_meta`.
3. Si la config lo pide, se crea job `adpw_category_update_job`.
4. `wp-cron` aplica metadata a productos usando la categoria mas especifica.

## Dónde mirar primero

- Bootstrap: [`actualizar_dimensiones_productos_woocommerce.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/actualizar_dimensiones_productos_woocommerce.php)
- Importacion: [`includes/class-adpw-import-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-import-queue-manager.php)
- Parser Excel: [`includes/class-adpw-excel-import-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-service.php)
- Metadata categorias: [`includes/class-adpw-category-metadata-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-manager.php)
- Settings: [`includes/class-adpw-settings.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-settings.php)
