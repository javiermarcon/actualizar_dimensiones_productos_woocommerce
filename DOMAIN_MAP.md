# DOMAIN_MAP.md

## Dominios funcionales

### 1. Configuracion

Archivo principal:

- [`includes/class-adpw-settings.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-settings.php)

Conceptos:

- `actualizar_si`
- `actualizar_tam`
- `actualizar_cat`
- `actualizar_productos_desde_categorias`
- `categorias_por_lote`

### 2. Importacion desde Excel

Archivos:

- [`includes/class-adpw-excel-import-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-page.php)
- [`includes/class-adpw-import-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-import-queue-manager.php)
- [`includes/class-adpw-excel-import-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-service.php)

Conceptos:

- encabezados soportados
- matching de categoria por nombre e ID
- modos de importacion
- job por etapas
- cola de productos afectados

### 3. Metadata por categoria

Archivos:

- [`includes/class-adpw-category-metadata-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-page.php)
- [`includes/class-adpw-category-metadata-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-manager.php)
- [`includes/class-adpw-category-update-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-update-queue-manager.php)

Conceptos:

- metadata persistida en `term_meta`
- clase de envio
- peso
- alto
- ancho
- profundidad
- categoria mas especifica para productos con multiples categorias

### 4. UI y progreso

Archivo:

- [`includes/class-adpw-admin-job-progress-ui.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-job-progress-ui.php)

Conceptos:

- snapshots
- progress bar
- polling AJAX
- debug log

### 5. Infraestructura de jobs

Archivo:

- [`includes/class-adpw-background-job-utils.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-background-job-utils.php)

Conceptos:

- job en `wp_options`
- summary estandar
- reschedule del siguiente lote
- log acotado para debug
