# Actualizar Dimensiones Productos WooCommerce

Plugin de WordPress para actualizar productos de WooCommerce desde Excel y para administrar metadata por categoría en un árbol.

## Qué hace

- Importa datos desde Excel para actualizar productos por categoría.
- Permite actualizar:
  - peso
  - largo
  - ancho
  - profundidad
  - tamaño (clase de envío)
- Incluye un árbol de categorías (`product_cat`) para editar metadata por categoría:
  - clase de envío
  - peso
  - alto
  - ancho
  - profundidad
- Puede aplicar la metadata de categorías a productos masivamente.

## Requisitos

- WordPress
- WooCommerce activo
- PHP compatible con tu instalación de WordPress/WooCommerce
- Dependencias del plugin instaladas (autoload en `vendor/`)

## Instalación

1. Copiar la carpeta del plugin en `wp-content/plugins/`.
2. Activar el plugin desde el panel de WordPress.
3. Verificar que exista `vendor/autoload.php` en el plugin.

## Pantalla de administración

Menú: `Actualizar Productos`

El plugin ahora separa responsabilidades en 3 menús:

1. **Importar Excel**
2. **Árbol de categorías**
3. **Configuración**

La importación se ejecuta en segundo plano por lotes usando `wp-cron` y muestra barra de progreso en la pantalla de importación.

## Importación desde Excel

### Encabezados reconocidos

- `Categoría`
- `Largo (cm)`
- `Ancho (cm)`
- `Profundidad (cm)`
- `Peso (kg)`
- `ID Categoría` (opcional)
- `Tamaño`

Nota: los encabezados se normalizan para tolerar mayúsculas/minúsculas y acentos (`Categoría`/`Categoria`, `Tamaño`/`Tamano`).

Si una fila trae una `Categoría` cuyo nombre existe en varias ramas del árbol, la importación aplica esa fila a todas las categorías coincidentes y luego actualiza los productos de todas esas categorías.

### Formatos de Excel soportados

1. **Dimensiones por categoría**

- `Categoría` + una o más columnas de dimensiones/peso.

1. **Solo tamaño por categoría**

- `Categoría` + `Tamaño`.

### Configuración

La pantalla `Configuración` está dividida en 3 pestañas:

- `Común`
  - Define el `Tamaño de lote` usado por `wp-cron`.
- `Importación Excel`
  - Contiene las opciones que afectan la importación desde planilla.
- `Árbol de categorías`
  - Contiene las opciones que afectan el guardado del árbol.

#### Pestaña Importación Excel

- `Actualizar siempre`
  - Fuerza actualización de dimensiones incluso si el producto ya tiene datos.
- `Actualizar dimensiones (peso/largo/ancho/profundidad) o tamaño si el Excel solo trae Categoría + tamaño`
  - Actualiza dimensiones cuando están presentes.
  - Si no hay columnas de dimensiones y sí hay `Tamaño`, usa fallback para actualizar tamaño.
- `Actualizar tamaño (clase de envío) de los productos`
  - Actualiza explícitamente la clase de envío usando la columna `Tamaño`.

Estos valores se guardan y se aplican automáticamente en cada importación.

#### Pestaña Común

- `Tamaño de lote`
  - Define cuántos elementos procesa cada ejecución de cron.
  - En los pasos 1 y 2 el lote aplica sobre filas/categorías; en el paso 3 aplica sobre productos.
  - Ayuda a evitar `max_execution_time` en importaciones grandes.

#### Pestaña Árbol de categorías

- `Actualizar productos al guardar metadata`
  - Si está activa, al guardar cambios en el árbol también se actualizan los productos afectados.
  - Si un producto pertenece a múltiples categorías editadas, aplica la metadata de la categoría **más específica**.

## Árbol de categorías y metadata

Permite editar por categoría:

- Clase de envío
- Peso
- Alto
- Ancho
- Profundidad

Al guardar, los valores se almacenan en `term_meta` con estas keys:

- `_adpw_categoria_clase_envio`
- `_adpw_categoria_peso`
- `_adpw_categoria_alto`
- `_adpw_categoria_ancho`
- `_adpw_categoria_profundidad`

### Aplicar metadata a productos

Si en `Configuración > Árbol de categorías` está activa la opción `Actualizar productos al guardar metadata`:

- El plugin actualiza productos asociados a esas categorías.
- Si un producto pertenece a múltiples categorías editadas, aplica la metadata de la categoría **más específica** (más profunda en el árbol).

## Errores y validaciones

- Los errores de importación se muestran dentro de la misma pantalla del plugin (sin cortar la UI).
- Si faltan encabezados requeridos para la acción seleccionada, se muestra un mensaje con el detalle.

## Notas

- El plugin opera sobre productos (`post_type = product`) de WooCommerce.
- Antes de importar en producción, se recomienda probar con una copia o un lote reducido.

## Autor

- Javier Marcon
