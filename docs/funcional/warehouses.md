# Almacenes

## 1. ¿Para qué sirve?

Dar de alta los almacenes, saber cuánto stock tiene cada uno de cada
producto, y mover mercancía de uno a otro sin pasos manuales de descuento y
alta por separado.

## 2. Conceptos clave

- **Catálogo global, stock por almacén.** El producto (nombre, precios,
  unidades) se define una sola vez; la cantidad que hay de él en cada
  almacén vive aparte, en una fila de stock por `(producto, almacén)`. Es lo
  que permite comparar el mismo producto entre almacenes y transferirlo sin
  duplicar el catálogo.
- **Fijar stock, no incrementarlo.** `POST /v1/products/{id}/stock` deja la
  cantidad del producto en ese almacén en el valor indicado; no lo suma al
  que hubiera. Sirve tanto para el alta inicial como para una corrección de
  inventario, y cada llamada queda auditada con el valor anterior y el nuevo.
- **Las transferencias son inmediatas y solo del admin.** No hay estado "en
  tránsito" ni aprobación: en la misma transacción se descuenta del almacén
  de origen y se suma en el de destino (creando su fila de stock si aún no
  existía).
- **Borrado protegido.** Un almacén no se puede borrar si tiene stock
  distinto de cero, usuarios asignados, ventas registradas o transferencias
  (como origen o como destino). Para retirarlo de circulación sin borrarlo
  está el campo `activo`.

## 3. Flujos de uso

1. El admin crea los almacenes que va a usar (`POST /v1/warehouses`).
2. Da de alta productos con stock inicial en uno de ellos, o lo fija después
   con `POST /v1/products/{id}/stock`.
3. Cuando un almacén se queda corto, transfiere desde otro
   (`POST /v1/transfers`) indicando producto, origen, destino y cantidad.
4. Revisa el historial de movimientos con `GET /v1/transfers`.
5. Si un almacén deja de operar, se desactiva (`activo: false`) en vez de
   borrarlo, para conservar su histórico de ventas y stock.

## 4. Cómo lo usa el frontend

| Acción | Método y ruta | Permiso |
|---|---|---|
| Listar almacenes | `GET /v1/warehouses` | `warehouses.view` |
| Crear almacén | `POST /v1/warehouses` | `warehouses.create` |
| Ver almacén | `GET /v1/warehouses/{id}` | `warehouses.view` |
| Editar almacén | `PUT /v1/warehouses/{id}` | `warehouses.update` |
| Borrar almacén | `DELETE /v1/warehouses/{id}` | `warehouses.delete` |
| Fijar stock de un producto | `POST /v1/products/{id}/stock` | `stock.set` |
| Listar transferencias | `GET /v1/transfers` | `transfers.view` |
| Registrar transferencia | `POST /v1/transfers` | `transfers.create` |

El cuerpo de una transferencia:

```json
{
  "product_id": 7,
  "from_warehouse_id": 1,
  "to_warehouse_id": 2,
  "unit_id": 2,
  "cantidad": 5
}
```

`unit_id` es opcional: si falta, la cantidad se interpreta en unidad base.
Origen y destino deben ser distintos, y la cantidad se convierte y se
descuenta siempre en unidad base, igual que en una venta.

## 5. Qué no hace todavía

- No hay zonas ni ubicaciones dentro de un almacén: el stock es un único
  número por `(producto, almacén)`.
- No hay lotes, números de serie ni caducidad.
- No hay estados de stock (reservado, en cuarentena, en tránsito): una
  transferencia se completa al instante o no se completa.
- No hay flujo de aprobación ni de picking para las transferencias.
