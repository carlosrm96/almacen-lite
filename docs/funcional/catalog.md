# Catálogo

## 1. ¿Para qué sirve?

Mantener el catálogo de productos y las unidades en las que se pueden vender
o transferir, con la conversión a la unidad base que usa el inventario para
llevar la cuenta del stock.

## 2. Conceptos clave

- **Producto = nombre + dos precios.** `precio_compra` y `precio_venta`. El
  precio de compra solo lo ve el admin: nunca aparece en la respuesta que
  recibe el vendedor, en ningún endpoint.
- **Cada producto tiene una moneda.** `currency_id` indica en qué moneda
  están sus dos precios; omitirlo significa la moneda base (`CUP`). Así un
  mismo catálogo puede tener lo local en CUP y lo importado en USD.
  `GET /v1/currencies` lista las monedas disponibles con su tasa; el
  vendedor también ve el código y el símbolo de cada precio, porque un
  precio sin moneda no es un precio.
- **Las tasas no se editan por API.** Se siembran y se administran en base
  de datos. La moneda base tiene siempre tasa 1, por definición.
- **Una unidad es un par (nombre, factor).** Por ejemplo, `unidad` con
  factor `1` o `caja` con factor `24`. El factor vive en la unidad, no en la
  relación con el producto: `caja` vale `24` para cualquier producto que la
  use. Un producto con cajas de 12 necesita otra unidad (`caja 12`), no
  puede reutilizar `caja` con otro factor.
- **Unidad base.** Todo producto nace con exactamente una unidad base, cuyo
  factor debe ser `1`. El stock se guarda y se descuenta siempre en unidad
  base; las demás unidades son solo una forma cómoda de vender o transferir.
  La unidad base nunca se puede desasignar del producto.
- **Asignar y desasignar unidades.** `POST /v1/products/{id}/units` añade una
  unidad de venta adicional al producto (por ejemplo, `caja`, además de la
  base); `DELETE /v1/products/{id}/units/{unit}` la retira. La unidad base
  está excluida de este borrado.
- **Borrado lógico del producto.** `DELETE /v1/products/{id}` no borra la
  fila: marca `deleted_at`. El producto deja de listarse, venderse o
  transferirse, pero sus ventas pasadas siguen siendo válidas y siguen
  contando en las métricas de periodos anteriores.
- **Una unidad no se puede borrar si está en uso**, ni asignada a un
  producto ni presente en alguna línea de venta.

## 3. Flujos de uso

1. El admin crea las unidades que va a necesitar (`POST /v1/units`):
   `unidad`, `caja`, etc.
2. Da de alta un producto (`POST /v1/products`) indicando su unidad base y,
   opcionalmente, un almacén y una cantidad para crear el stock inicial en
   la misma llamada.
3. Asigna al producto las unidades adicionales con las que se podrá vender
   (`POST /v1/products/{id}/units`).
4. El vendedor consulta el catálogo (`GET /v1/products`) para vender; ve
   solo los campos que le corresponden por rol.
5. Si un producto se descontinúa, se borra (baja lógica); si una unidad deja
   de usarse en ningún producto, se puede borrar.

## 4. Cómo lo usa el frontend

| Acción | Método y ruta | Permiso |
|---|---|---|
| Listar unidades | `GET /v1/units` | `units.view` |
| Crear unidad | `POST /v1/units` | `units.create` |
| Editar unidad | `PUT /v1/units/{id}` | `units.update` |
| Borrar unidad | `DELETE /v1/units/{id}` | `units.delete` |
| Listar productos | `GET /v1/products` | `products.view` |
| Crear producto | `POST /v1/products` | `products.create` |
| Ver producto | `GET /v1/products/{id}` | `products.view` |
| Editar producto | `PUT /v1/products/{id}` | `products.update` |
| Borrar producto (baja lógica) | `DELETE /v1/products/{id}` | `products.delete` |
| Asignar unidad al producto | `POST /v1/products/{id}/units` | `products.update` |
| Desasignar unidad | `DELETE /v1/products/{id}/units/{unit}` | `products.update` |

El cuerpo del alta de un producto, con stock inicial opcional:

```json
{
  "nombre": "Agua 1L",
  "precio_compra": 0.35,
  "precio_venta": 0.90,
  "base_unit_id": 1,
  "warehouse_id": 1,
  "cantidad": 480
}
```

Sin `warehouse_id`/`cantidad` el producto nace sin stock, y se fija después
con `POST /v1/products/{id}/stock`. `GET /v1/products` devuelve campos
distintos según el rol: el admin ve precios, unidades y stock por almacén; el
vendedor ve nombre, precio de venta, la cantidad de su propio almacén y las
unidades con las que puede vender.

## 5. Qué no hace todavía

- No hay categorías, variantes ni atributos de producto.
- No hay imágenes ni ficha extendida: solo nombre y los dos precios.
- El factor de conversión vive en la unidad, no en la relación
  producto-unidad, así que no puede haber dos "cajas" con factores distintos
  en el mismo catálogo.
- No hay códigos de barras ni SKU.
