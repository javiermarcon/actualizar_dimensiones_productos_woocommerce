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

Desde esa pantalla tenés dos bloques:

1. **Importar Dimensiones** (Excel)
2. **Árbol de categorías y metadata**

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

### Formatos de Excel soportados

1. **Dimensiones por categoría**
- `Categoría` + una o más columnas de dimensiones/peso.

2. **Solo tamaño por categoría**
- `Categoría` + `Tamaño`.

### Checkboxes de importación

- `Actualizar siempre`
  - Fuerza actualización de dimensiones incluso si el producto ya tiene datos.
- `Actualizar dimensiones (peso/largo/ancho/profundidad) o tamaño si el Excel solo trae Categoría + tamaño`
  - Actualiza dimensiones cuando están presentes.
  - Si no hay columnas de dimensiones y sí hay `Tamaño`, usa fallback para actualizar tamaño.
- `Actualizar tamaño (clase de envío) de los productos`
  - Actualiza explícitamente la clase de envío usando la columna `Tamaño`.

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

Si marcás `Actualizar productos con esta metadata` al guardar:

- El plugin actualiza productos asociados a esas categorías.
- Si un producto pertenece a múltiples categorías editadas, aplica la metadata de la categoría **más específica** (más profunda en el árbol).

## Errores y validaciones

- Los errores de importación se muestran dentro de la misma pantalla del plugin (sin cortar la UI).
- Si faltan encabezados requeridos para la acción seleccionada, se muestra un mensaje con el detalle.

## Notas

- El plugin opera sobre productos (`post_type = product`) de WooCommerce.
- Antes de importar en producción, se recomienda probar con una copia o un lote reducido.

## Autor

- Tu Nombre
