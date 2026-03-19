# LIFECYCLE.md

Ciclos de vida principales del plugin.

## 1. Lifecycle de importacion Excel

1. Usuario entra a `Actualizar Productos > Importar Excel`.
2. [`ADPW_Excel_Import_Page`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-page.php) recibe el POST con archivo.
3. [`ADPW_Import_Queue_Manager::start_import_job()`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-import-queue-manager.php) crea `adpw_import_job`.
4. [`ADPW_Excel_Import_Service::initialize_job()`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-service.php) valida archivo, encabezados y modo de importacion.
5. Se agenda `adpw_process_import_batch`.
6. El job avanza por etapas:
   - `parse_sheet`
   - `save_category_meta`
   - `update_products`
7. La UI consulta estado por AJAX con polling.
8. Al completar o fallar, el job queda persistido para snapshot y debug.

## 2. Lifecycle de guardado de metadata por categoria

1. Usuario entra a `Actualizar Productos > Árbol de Categorías`.
2. Edita inputs de metadata.
3. [`ADPW_Category_Metadata_Page`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-page.php) arma delta y procesa POST.
4. [`ADPW_Category_Metadata_Manager::save_category_metadata()`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-manager.php) persiste cambios en `term_meta`.
5. Si la config `actualizar_productos_desde_categorias` esta activa, se inicia `adpw_category_update_job`.

## 3. Lifecycle de actualizacion de productos desde categorias

1. [`ADPW_Category_Update_Queue_Manager::start_job()`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-update-queue-manager.php) recibe categorias modificadas.
2. [`ADPW_Category_Metadata_Manager::build_product_queue_for_categories()`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-manager.php) construye cola.
3. Se agenda `adpw_process_category_update_batch`.
4. Cada batch aplica metadata a productos usando la categoria mas especifica.
5. La UI consulta progreso por AJAX hasta completar.

## 4. Lifecycle de polling y progreso

1. La pantalla admin renderiza markup de progreso con [`ADPW_Admin_Job_Progress_UI`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-job-progress-ui.php).
2. El JS embebido hace polling a `admin-ajax.php`.
3. Si detecta job corriendo, muestra progreso, debug y resultados.
4. Si el job se estanca, puede disparar batch manual via AJAX.

## 5. Lifecycle de configuracion

1. Usuario entra a `Configuración`.
2. [`ADPW_Settings`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-settings.php) carga defaults y valores guardados.
3. El POST actualiza `adpw_import_settings`.
4. Las proximas importaciones y jobs usan esos valores.

## Guardrails de lifecycle

- Cada lifecycle debe tener un entrypoint claro.
- Las pantallas admin no deben contener logica de negocio pesada.
- La logica de negocio debe vivir en managers/services.
- Los jobs deben ser reanudables por lotes pequeños.
- Las etapas del job no deben depender de estado solo en memoria.
