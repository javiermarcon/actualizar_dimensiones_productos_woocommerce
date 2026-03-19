# TESTING.md

Guia de testing para `/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce`.

## Stack actual

- PHPUnit:
  - [`phpunit.xml.dist`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/phpunit.xml.dist)
- Bootstrap y stubs:
  - [`tests/bootstrap.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/tests/bootstrap.php)
  - [`tests/wp-stubs.php`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/tests/wp-stubs.php)
- Lint:
  - [`phpcs.xml`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/phpcs.xml)
  - [`phpstan.neon`](/home/javier/proyectos/wordpress/plugins/actualizar_dimensiones_productos_woocommerce/phpstan.neon)

## Comandos

- `composer test`
- `composer test:coverage`
- `composer lint`

## Alcance recomendado por tipo de cambio

- Importacion Excel:
  - probar normalizacion de encabezados
  - probar matching de categorias
  - probar validacion de formatos soportados
- Colas en segundo plano:
  - probar resumen/progreso
  - probar transiciones de estado
- Metadata por categoria:
  - probar normalizacion numerica
  - probar seleccion de categoria mas especifica cuando aplique

## Reglas practicas

- No usar red real en tests.
- No depender de un WordPress completo para tests unitarios; usar stubs locales.
- Si se toca logica pura o helpers, agregar tests unitarios.
- Si se toca wiring con WordPress, al menos correr `composer lint` y documentar la validacion manual necesaria.

## Estado actual

- La suite actual es minima y valida infraestructura basica y parte de la logica de importacion.
- La cobertura actual no representa exhaustividad funcional completa; usarla como base, no como objetivo final.
