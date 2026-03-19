# ARCHITECTURE_GUARDRAILS.md

Limites y reglas entre dominios para `/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce`.

## 1) Bootstrap y registro de hooks

- Archivo:
  - [`actualizar_dimensiones_productos_woocommerce.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/actualizar_dimensiones_productos_woocommerce.php)
- Permitido:
  - cargar autoload
  - requerir clases
  - registrar hooks y menus
- No permitido:
  - logica de negocio de importacion
  - escritura directa de metadata o productos

## 2) Admin UI

- Archivos:
  - [`includes/class-adpw-admin-menu.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-menu.php)
  - [`includes/class-adpw-admin-job-progress-ui.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-job-progress-ui.php)
  - [`includes/class-adpw-excel-import-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-page.php)
  - [`includes/class-adpw-category-metadata-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-page.php)
  - [`includes/class-adpw-settings.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-settings.php)
- Permitido:
  - render de formularios, tablas y estado
  - validacion basica de request y nonce
  - disparar managers/orquestadores
  - JS de polling sobre `admin-ajax.php`
- No permitido:
  - parsear Excel
  - aplicar reglas complejas de matching de categorias
  - actualizar productos directamente salvo wiring minimo existente

## 3) Importer orchestration

- Archivo:
  - [`includes/class-adpw-import-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-import-queue-manager.php)
- Permitido:
  - crear y persistir `adpw_import_job`
  - exponer endpoints AJAX de estado y avance manual
  - ejecutar lotes y transicionar etapas
  - programar el siguiente batch
- No permitido:
  - render de UI
  - parseo detallado de planillas
  - logica de metadata por categoria fuera del flujo de importacion

## 4) Excel parsing y reglas de importacion

- Archivo:
  - [`includes/class-adpw-excel-import-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-service.php)
- Permitido:
  - leer planillas soportadas
  - detectar encabezados y modos de importacion
  - resolver categorias por nombre e ID
  - preparar temporales y colas de actualizacion
  - actualizar productos durante el flujo de importacion
- No permitido:
  - registrar hooks WordPress
  - renderizar HTML admin
  - manejar settings globales fuera de lo que recibe como input

Soporte asociado:

- [`includes/class-adpw-excel-import-support.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-support.php)
  - solo helpers de encabezados, validacion y matching
- [`includes/class-adpw-excel-product-update-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-product-update-service.php)
  - solo aplicacion de cambios sobre productos

## 5) Metadata por categoria

- Archivo:
  - [`includes/class-adpw-category-metadata-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-manager.php)
- Permitido:
  - leer/escribir `term_meta`
  - normalizar valores numericos
  - construir cola de productos afectados por categorias
  - elegir categoria mas especifica
  - aplicar metadata de categoria a productos
- No permitido:
  - parsear Excel
  - registrar AJAX o cron
  - renderizar UI

Servicio asociado:

- [`includes/class-adpw-category-metadata-save-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-save-service.php)
  - recibe payload ya validado desde la page y decide si dispara job

## 6) Category update orchestration

- Archivo:
  - [`includes/class-adpw-category-update-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-update-queue-manager.php)
- Permitido:
  - crear y persistir `adpw_category_update_job`
  - exponer endpoints AJAX de estado y avance manual
  - ejecutar batches de aplicacion a productos
- No permitido:
  - guardar directamente formularios del arbol
  - duplicar logica de aplicacion de metadata ya existente en `ADPW_Category_Metadata_Manager`

## 7) Scheduler boundary

- Estado actual:
  - el plugin usa `wp_schedule_single_event()` para reprogramar lotes cortos
  - no hay cron recurrente propio de mantenimiento
- Guardrail:
  - no introducir cron recurrente nuevo, workers persistentes ni tablas de cola sin decision explicita y documentacion de impacto

## Reglas de interaccion permitida

- `ADPW_*_Page` puede llamar a managers y helpers de UI.
- `ADPW_Import_Queue_Manager` puede llamar a `ADPW_Excel_Import_Service` y `ADPW_Background_Job_Utils`.
- `ADPW_Category_Update_Queue_Manager` puede llamar a `ADPW_Category_Metadata_Manager` y `ADPW_Background_Job_Utils`.
- `ADPW_Excel_Import_Service` puede usar `ADPW_Category_Metadata_Manager` para reutilizar cola de productos y metadata.
- `ADPW_Category_Metadata_Manager` no debe depender de paginas admin.
- `ADPW_Background_Job_Utils` debe mantenerse agnostico del dominio.

## Cambios que requieren aprobacion explicita

- Nuevas option keys persistentes.
- Cambio de shape de `adpw_import_job` o `adpw_category_update_job` si rompe compatibilidad con jobs en curso.
- Nuevas meta keys o renombre de `_adpw_categoria_*`.
- Cambios de comportamiento masivo sobre productos fuera de los flujos actuales.
- Nuevos formatos de planilla o cambios de matching que puedan afectar importaciones existentes.
