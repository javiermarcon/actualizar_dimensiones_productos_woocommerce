# ARCHITECTURE_DECISIONS.md

Decisiones arquitectonicas vigentes para este plugin.

## AD-001: Persistencia simple en `wp_options` y `term_meta`

### Decision

Usar `wp_options` para settings y jobs, y `term_meta` para metadata de categorias.

### Motivo

- el plugin no necesita tablas custom hoy
- reduce complejidad operativa
- simplifica instalacion y mantenimiento

### Implicancias

- los jobs deben tener payload acotado
- cambios de shape en jobs requieren cuidado
- no hay consultas avanzadas ni auditoria historica fuerte

## AD-002: Jobs por lotes con `wp_schedule_single_event()`

### Decision

Procesar importaciones y actualizaciones por categoria en lotes, reprogramando el siguiente paso con `wp_schedule_single_event()`.

### Motivo

- evitar timeouts en procesos grandes
- aprovechar infraestructura estandar de WordPress
- permitir batch manual y polling desde admin

### Implicancias

- el progreso depende de `wp-cron`
- la UI necesita polling y boton de batch manual
- no introducir cron recurrente adicional sin justificarlo

## AD-003: Separar orquestacion de negocio

### Decision

Separar:

- page classes para UI y request handling
- queue managers para cron/AJAX/orquestacion
- services/managers para reglas de negocio

### Motivo

- reducir duplicacion
- mejorar testabilidad
- sostener `SRP` y `DRY` de forma pragmatica

### Implicancias

- evitar mover logica compleja a pages
- evitar duplicar reglas entre importacion y metadata por categoria

## AD-004: Importacion basada en categoria antes que en producto

### Decision

La importacion desde Excel primero resuelve y guarda datos por categoria y despues actualiza productos afectados.

### Motivo

- el contrato funcional del plugin esta centrado en categoria
- permite reutilizar metadata y cola de productos
- simplifica importaciones repetidas sobre la misma categoria

### Implicancias

- cambios en matching de categoria tienen mucho impacto
- hay que documentar bien encabezados y formatos soportados

## AD-005: Matching flexible de categorias

### Decision

La importacion intenta primero coincidencia exacta normalizada y, si no encuentra, usa matching flexible por nombre.

### Motivo

- las planillas reales no siempre usan el nombre exacto de WooCommerce
- los ejemplos en `docs/` incluyen variantes abreviadas

### Implicancias

- riesgo de sobrecoincidencia si el catalogo crece
- conviene priorizar `ID Categoría` cuando exista
- cualquier ajuste de esta regla debe revisarse con cuidado

## AD-006: Calidad automatizada minima en repo

### Decision

Mantener `composer lint`, `composer test` y CI como baseline obligatoria.

### Motivo

- evitar roturas triviales
- sostener disciplina minima de cambio
- dar soporte a agentes y contributors

### Implicancias

- cambios en `composer.json` deben sincronizar `composer.lock`
- nuevas reglas deben ser realistas para este repo
- la documentacion de calidad debe mantenerse alineada con el codigo
