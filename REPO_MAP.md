# REPO_MAP.md

Mapa rapido del repositorio `/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce`.

## Raiz

- [`actualizar_dimensiones_productos_woocommerce.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/actualizar_dimensiones_productos_woocommerce.php)
  - bootstrap del plugin
  - carga autoload
  - requiere clases
  - registra hooks y menus

- [`README.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/README.md)
  - documentacion funcional para uso del plugin

- [`composer.json`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/composer.json)
  - dependencias y scripts de calidad/tests

- [`composer.lock`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/composer.lock)
  - versionado de dependencias resueltas

## includes/

- [`includes/class-adpw-admin-menu.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-menu.php)
  - define menu principal y submenus del plugin

- [`includes/class-adpw-admin-job-progress-ui.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-admin-job-progress-ui.php)
  - UI reusable de progreso, snapshot, debug y polling JS

- [`includes/class-adpw-settings.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-settings.php)
  - settings del plugin
  - tabs `common`, `import`, `tree`

- [`includes/class-adpw-excel-import-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-page.php)
  - pantalla admin de importacion Excel
  - POST manual y snapshot de job

- [`includes/class-adpw-import-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-import-queue-manager.php)
  - orquestacion del job de importacion
  - AJAX y cron del flujo Excel

- [`includes/class-adpw-excel-import-service.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-excel-import-service.php)
  - parseo de planilla
  - validacion de encabezados
  - matching de categorias
  - actualizacion de metadata y productos

- [`includes/class-adpw-category-metadata-page.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-page.php)
  - pantalla admin del arbol de categorias

- [`includes/class-adpw-category-update-queue-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-update-queue-manager.php)
  - orquestacion del job de aplicacion de metadata por categoria

- [`includes/class-adpw-category-metadata-manager.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-category-metadata-manager.php)
  - lectura/escritura de `term_meta`
  - construccion de cola de productos
  - aplicacion de metadata a productos

- [`includes/class-adpw-background-job-utils.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/includes/class-adpw-background-job-utils.php)
  - utilidades compartidas para jobs en segundo plano

## docs/

- [`docs/`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/docs)
  - planillas de ejemplo usadas como referencia de formatos soportados

## tests/

- [`tests/bootstrap.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/tests/bootstrap.php)
  - bootstrap de PHPUnit

- [`tests/wp-stubs.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/tests/wp-stubs.php)
  - stubs para WordPress y WooCommerce en tests

- [`tests/ADPWBackgroundJobUtilsTest.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/tests/ADPWBackgroundJobUtilsTest.php)
  - tests del summary de jobs

- [`tests/ADPWExcelImportServiceTest.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/tests/ADPWExcelImportServiceTest.php)
  - tests base del parser y matching de categorias

## scripts/

- [`scripts/install-git-hooks.sh`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/scripts/install-git-hooks.sh)
  - instala hooks de git

- [`scripts/lint-staged-php.sh`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/scripts/lint-staged-php.sh)
  - sintaxis PHP

- [`scripts/lint-staged-phpcs.sh`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/scripts/lint-staged-phpcs.sh)
  - reglas PHPCS

- [`scripts/lint-staged-phpstan.sh`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/scripts/lint-staged-phpstan.sh)
  - analisis estatico con PHPStan

## Documentacion interna

- [`AI_CONTEXT.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/AI_CONTEXT.md)
- [`ARCHITECTURE.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/ARCHITECTURE.md)
- [`ARCHITECTURE_GUARDRAILS.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/ARCHITECTURE_GUARDRAILS.md)
- [`CODEBASE_ENTRYPOINTS.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/CODEBASE_ENTRYPOINTS.md)
- [`DOMAIN_MAP.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/DOMAIN_MAP.md)
- [`STYLE.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/STYLE.md)
- [`TESTING.md`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/TESTING.md)
