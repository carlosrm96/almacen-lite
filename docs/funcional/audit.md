# Auditoría

## 1. ¿Para qué sirve?

Dejar constancia de quién hizo qué y cuándo sobre el catálogo, el stock y las
transferencias, para poder reconstruir un cambio o resolver una duda —
"¿quién bajó este precio?", "¿quién movió ese stock a Norte?" — sin tener
que rastrear los logs del servidor.

## 2. Conceptos clave

- **Se registra explícitamente desde el código, no de forma automática.**
  Cada Action que hace un cambio sensible (`CreateProduct`, `UpdateProduct`,
  `DeleteProduct`, `SetProductStock`, `TransferStock`) llama al servicio de
  auditoría antes de terminar. No hay un observer implícito escuchando
  cambios en los modelos: el rastro es legible siguiendo el código de la
  Action correspondiente.
- **Qué se audita.** Alta de un producto, actualización (con el detalle de
  qué campos cambiaron y sus valores antes/después), baja lógica de un
  producto, fijar el stock de un producto en un almacén (valor anterior y
  nuevo) y cada transferencia entre almacenes.
- **Qué guarda cada entrada.** Usuario que hizo la acción, el nombre de la
  acción, el tipo y el id del objeto afectado, el almacén cuando aplica,
  la fecha y hora, y un detalle en `datos` (JSON) con lo específico del
  cambio.
- **Solo el admin consulta la auditoría.** No hay una vista para que el
  vendedor vea su propio historial de acciones.

## 3. Flujos de uso

1. El admin actualiza un producto, fija stock o registra una transferencia:
   la aplicación crea la entrada de auditoría correspondiente sola, sin
   ningún paso adicional por su parte.
2. Cuando necesita revisar qué pasó, consulta `GET /v1/audit-logs`,
   filtrando por usuario, tipo de acción, producto afectado o rango de
   fechas.
3. Cada fila del listado muestra quién hizo la acción, sobre qué objeto, y
   el detalle del cambio en `datos`.

## 4. Cómo lo usa el frontend

| Acción | Método y ruta | Permiso |
|---|---|---|
| Listar auditoría | `GET /v1/audit-logs?user_id=&accion=&auditable_id=&desde=&hasta=` | `audit.view` |

Valores posibles de `accion`: `producto.creado`, `producto.actualizado`,
`producto.eliminado`, `stock.fijado`, `transferencia.realizada`. `desde` y
`hasta` filtran por fecha de creación de la entrada; el resto son filtros
exactos.

## 5. Qué no hace todavía

- No audita cada venta ni cada variación de stock que provoca una venta: ese
  movimiento es reconstruible desde las líneas de venta (`sale_items`), pero
  no genera una entrada de auditoría propia.
- No hay auditoría de usuarios ni de almacenes (alta, baja o edición): solo
  de productos, stock y transferencias.
- No hay forma de deshacer un cambio desde la auditoría: es un registro de
  solo lectura.
- No hay exportación ni política de retención: las entradas se acumulan sin
  purga automática.
