# STYLE.md

Reglas de estilo para `/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce`.

## PHP y WordPress

- Mantener el estilo procedural/minimalista actual del plugin.
- Seguir convenciones WordPress para sanitizacion, escaping y nonces.
- Mantener nombres existentes de clases `ADPW_*`.
- Evitar refactors grandes si el cambio es funcionalmente chico.

## Sanitizacion y escaping

- Entrada:
  - `sanitize_text_field`
  - `sanitize_key`
  - `sanitize_title`
  - `absint`
  - `wp_unslash`
- Salida en admin:
  - `esc_html`
  - `esc_attr`
  - `esc_url`
- Datos numericos:
  - normalizar con `ADPW_Category_Metadata_Manager::normalize_number()` cuando aplique

## Nonce y permisos

- Formularios admin deben validar nonce dedicado.
- Endpoints AJAX administrativos deben validar:
  - `check_ajax_referer(...)`
  - `current_user_can('manage_options')`

## Cambios sobre WooCommerce

- No asumir que todo producto tiene dimensiones cargadas.
- Mantener compatibilidad con:
  - taxonomia `product_cat`
  - taxonomia `product_shipping_class`
  - `wc_get_product()`
  - getters/setters de peso y dimensiones

## Colas y procesos en segundo plano

- Jobs se guardan en `wp_options`, no en tablas custom.
- No introducir nuevos hooks cron ni nuevas option keys sin necesidad clara.
- Si se cambia la forma del payload de job, revisar ambos managers:
  - [`includes/class-adpw-import-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-import-queue-manager.php)
  - [`includes/class-adpw-category-update-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-update-queue-manager.php)

## Estructura

- Bootstrap:
  - [`actualizar_dimensiones_productos_woocommerce.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/actualizar_dimensiones_productos_woocommerce.php)
- Admin y UI:
  - [`includes/class-adpw-admin-menu.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-menu.php)
  - [`includes/class-adpw-admin-job-progress-ui.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-job-progress-ui.php)
- Importacion Excel:
  - [`includes/class-adpw-excel-import-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-page.php)
  - [`includes/class-adpw-excel-import-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-service.php)
  - [`includes/class-adpw-import-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-import-queue-manager.php)
- Arbol de categorias:
  - [`includes/class-adpw-category-metadata-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-page.php)
  - [`includes/class-adpw-category-metadata-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-manager.php)
  - [`includes/class-adpw-category-update-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-update-queue-manager.php)

## Documentacion

- Si cambia el contrato de planillas soportadas, actualizar `README.md`.
- Si cambia la arquitectura o el flujo de jobs, actualizar `ARCHITECTURE.md` y `DOMAIN_MAP.md`.
