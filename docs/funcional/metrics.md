# Métricas

## 1. ¿Para qué sirve?

Dar visibilidad del rendimiento de ventas por periodo y del estado del
inventario (valor y quiebres de stock), sin tener que exportar datos a otra
herramienta. Todo se calcula al vuelo sobre las ventas y el stock actuales:
no hay tablas de agregados que puedan quedar desincronizadas.

## 2. Conceptos clave

- **Dos endpoints.** `GET /v1/metrics/sales` (ventas por periodo) y
  `GET /v1/metrics/inventory` (valor de inventario y stock bajo, solo admin).
- **Tres periodos.** `daily` (el día indicado, serie por hora), `weekly`
  (semana ISO de lunes a domingo, serie por día) y `monthly` (mes natural,
  serie por día). Sin `date` se usa el momento actual; los cortes respetan
  la zona horaria de la aplicación (`America/Havana`).
- **Todas las cifras van en moneda base.** El informe incluye `moneda` con
  su código (`CUP` por defecto). Los importes de productos en otra moneda se
  convierten con la tasa: los de ventas con la tasa congelada en cada línea,
  los de inventario con la tasa vigente del producto.
- **El vendedor solo puede pedir `weekly`.** Cualquier otro periodo devuelve
  `403`. Además siempre ve su propio almacén: el middleware fuerza
  `warehouse_id` al suyo, igual que en ventas.
- **El vendedor ve una versión recortada del informe.** Nunca `ganancia`,
  `top_productos` ni `comparativa` — exponen o se derivan de
  `precio_compra` — y en `ventas_por_vendedor` solo aparecen sus propias
  filas, no las de sus compañeros.
- **El inventario es exclusivo del admin.** Expone el precio de compra para
  calcular el valor a coste, así que el vendedor no tiene acceso al
  endpoint en absoluto (`403`).

| Métrica | Admin | Vendedor (`weekly`, su almacén) |
|---|---|---|
| Ingresos, nº ventas, unidades, ticket promedio | ✔ | ✔ |
| Serie de tiempo | ✔ | ✔ |
| Ganancia | ✔ | ✘ |
| Top de productos | ✔ | ✘ |
| Ventas por vendedor | Todos | Solo las suyas |
| Comparativa con el periodo anterior | ✔ | ✘ |
| Valor de inventario / stock bajo | ✔ | ✘ |

## 3. Flujos de uso

1. El admin abre el panel y pide
   `GET /v1/metrics/sales?period=weekly` (o `daily`/`monthly`), opcionalmente
   filtrando por `warehouse_id` para ver un almacén concreto.
2. Recibe ingresos, número de ventas, unidades vendidas, ticket promedio,
   ganancia, la serie de tiempo del periodo, el top 10 de productos (por
   unidades y por ingresos), las ventas agrupadas por vendedor y la
   comparativa porcentual con el periodo anterior.
3. El vendedor pide la misma métrica pero solo con `period=weekly`; recibe
   la versión recortada de la tabla anterior, siempre de su propio almacén.
4. El admin consulta `GET /v1/metrics/inventory` (opcionalmente con
   `warehouse_id` y `umbral`) para ver el valor del inventario a coste y a
   venta, y qué filas de stock están por debajo de su mínimo.

## 4. Cómo lo usa el frontend

| Acción | Método y ruta | Permiso |
|---|---|---|
| Métricas de ventas | `GET /v1/metrics/sales?period=&date=&warehouse_id=` | `metrics.view` |
| Métricas de inventario | `GET /v1/metrics/inventory?warehouse_id=&umbral=` | `metrics.full` |

`period` es obligatorio (`daily`, `weekly` o `monthly`); `date` es opcional
(por defecto, ahora); `warehouse_id` es opcional para el admin y se ignora
para el vendedor, que siempre ve el suyo. En inventario, `umbral` es
opcional: si se indica, sustituye al mínimo propio de cada fila de stock
para decidir qué cuenta como "stock bajo" en esa consulta.

## 5. Qué no hace todavía

- No hay tablas de agregados precalculados: cada petición recalcula sobre
  `sales` y `sale_items`, lo que basta con el volumen esperado pero no
  escalaría sin más a un histórico muy grande.
- La comparativa es solo contra el periodo inmediatamente anterior, no
  contra el mismo periodo del año pasado.
- No hay exportación a CSV/Excel ni informes programados por email.
- No hay métricas de compras: solo de ventas y de inventario actual.
