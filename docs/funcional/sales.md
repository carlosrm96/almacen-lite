# Ventas

## 1. ¿Para qué sirve?

Registrar lo que se vende en cada almacén y mantener el inventario al día sin
un paso manual de descuento. Una venta puede llevar varios productos y devuelve
el importe total en la misma respuesta, para imprimir el ticket sin una segunda
llamada.

## 2. Conceptos clave

- **La venta es de un almacén.** El vendedor vende siempre en el suyo; el admin
  indica cuál.
- **Unidad de venta.** Cada línea puede venderse en cualquier unidad asignada al
  producto. La API convierte a unidad base (`cantidad × factor`) y descuenta ese
  valor: 2 cajas de 24 descuentan 48.
- **Todo o nada.** Si un solo producto no tiene stock suficiente, se rechaza la
  venta entera y el inventario queda intacto. La respuesta lista qué productos
  fallaron, cuánto se pedía y cuánto había.
- **Snapshot de precios, moneda y tasa.** Cada línea guarda el precio de venta
  y el de compra del momento, junto con la moneda del producto y la tasa de
  cambio aplicada, así que ni cambiar la tarifa ni devaluar la moneda mañana
  altera el histórico ni las métricas de ayer.
- **El total va en moneda base.** Los precios unitarios de cada línea se
  muestran en la moneda con la que se vendió (`moneda` y `tasa_cambio` en la
  línea), pero `subtotal` y `total` están convertidos a la moneda base
  (`CUP`). Así una venta que mezcla productos en CUP y en USD tiene un total
  con sentido, y las métricas suman importes comparables.

## 3. Flujos de uso

1. El vendedor entra (`POST /v1/login`) y lista productos (`GET /v1/products`),
   donde ve nombre, precio de venta y la cantidad de su almacén.
2. Registra la venta (`POST /v1/sales`) con las líneas.
3. Si algo no tiene stock, recibe `422` con `productos_afectados` y corrige.
4. Consulta lo vendido (`GET /v1/sales`) y sus métricas semanales
   (`GET /v1/metrics/sales?period=weekly`).

## 4. Cómo lo usa el frontend

| Acción | Método y ruta | Permiso |
|---|---|---|
| Registrar venta | `POST /v1/sales` | `sales.create` |
| Listar ventas | `GET /v1/sales` | `sales.view` |
| Ver una venta | `GET /v1/sales/{id}` | `sales.view` |

El cuerpo del alta:

```json
{
  "warehouse_id": 1,
  "items": [
    {"product_id": 7, "cantidad": 3},
    {"product_id": 9, "unit_id": 2, "cantidad": 2}
  ]
}
```

`warehouse_id` es obligatorio para el admin; en el vendedor se ignora y se usa
siempre el suyo. `unit_id` es opcional: si falta, se vende en unidad base.

## 5. Qué no hace todavía

- No hay clientes ni facturación: la venta es un ticket interno.
- No hay devoluciones ni anulación de ventas.
- No hay descuentos, impuestos ni formas de pago.
- El stock es un número por almacén: sin lotes, caducidades ni ubicaciones.
