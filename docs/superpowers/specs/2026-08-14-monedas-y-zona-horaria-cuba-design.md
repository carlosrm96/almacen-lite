# Diseño: monedas configurables y zona horaria de Cuba

Fecha: 2026-08-14
Estado: implementado

## Problema

`almacen-lite` nació con dos supuestos europeos que estorban al desplegarlo en
Cuba:

1. `APP_TIMEZONE=Europe/Madrid` determina los cortes de día/semana/mes de las
   métricas. Con Madrid, una venta hecha a las 20:00 en La Habana ya cuenta
   como "mañana" en el panel: Madrid va 6 horas por delante.
2. No existe el concepto de moneda. Los precios son `decimal(12,2)` sin
   unidad, y toda la agregación de métricas (`SUM(sales.total)`,
   `SUM(stocks.cantidad * products.precio_compra)`) es válida solo porque
   todos los importes están implícitamente en la misma moneda.

En Cuba conviven CUP y USD en el mismo negocio. Hace falta que el catálogo
pueda expresar precios en ambas sin que las métricas empiecen a sumar peras
con manzanas.

## Decisiones

### Zona horaria

`APP_TIMEZONE=America/Havana` en `.env`, `.env.example` y `phpunit.xml`. Es un
cambio de configuración; ninguna consulta lleva la zona escrita a mano.

### Moneda base y tasa

- Tabla `currencies`: `codigo` (ISO, único), `nombre`, `simbolo`, `tasa`,
  `es_base`, `activo`.
- **`tasa` = cuántas unidades de la moneda base vale 1 unidad de esta moneda.**
  Por definición, **la moneda base tiene `tasa = 1`**, igual que la unidad base
  tiene factor 1. El modelo lo fuerza al guardar.
- Solo puede haber una moneda base. `Currency::base()` es la autoridad en
  tiempo de ejecución; `config('almacen.moneda_base')` (por defecto `CUP`) es
  el código con el que se siembra y el fallback si aún no hay filas.

### Dónde vive la moneda

En el **producto** (`products.currency_id`), no en el almacén: un mismo almacén
vende leche en CUP y aceite importado en USD, que es justo el caso cubano.

`currency_id` es nullable y `null` significa "moneda base". Así las filas
existentes siguen siendo válidas sin migración de datos y el caso normal
(todo en CUP) no obliga a rellenar nada.

### Qué moneda guarda cada importe

Esta es la regla que sostiene todo lo demás:

| Columna | Moneda |
|---|---|
| `products.precio_compra` / `precio_venta` | la del producto |
| `sale_items.precio_compra_unit` / `precio_venta_unit` | la del producto (snapshot) |
| `sale_items.tasa_cambio` | snapshot de la tasa aplicada |
| `sale_items.subtotal` | **moneda base** |
| `sales.total` | **moneda base** |

Los precios unitarios se guardan en su moneda original porque el ticket tiene
que poder mostrar lo que se le cobró al cliente. Los importes agregados
(`subtotal`, `total`) se guardan ya convertidos a base para que **todas las
consultas de métricas existentes sigan siendo correctas sin tocarlas**.

`tasa_cambio` se congela en la línea de venta por la misma razón que ya se
congelan los precios: que la devaluación de mañana no reescriba la ganancia de
ayer.

### Métricas

- `SalesMetricsReporter`: la única suma que mezclaba monedas era `ganancia`,
  calculada sobre los precios unitarios. Pasa a
  `SUM((precio_venta_unit - precio_compra_unit) * cantidad_base * tasa_cambio)`.
- `InventoryMetricsReporter`: valora stock con los precios vivos del producto,
  así que hace `LEFT JOIN currencies` y multiplica por `COALESCE(tasa, 1)`.
- Ambos informes exponen `moneda` (el código base) para que el panel sepa en
  qué unidad están las cifras.

### API

- `GET /v1/currencies` — listado de solo lectura (permiso `products.view`, que
  ya tienen admin y vendedor). No hay CRUD de monedas por API: las tasas se
  administran por seeder/consola. Es deliberado — mantiene el alcance chico.
- `ProductResource` expone `moneda` (código y símbolo) **también al vendedor**:
  un precio sin moneda no es un precio. No es dato sensible; el vendedor sigue
  sin ver `precio_compra`.
- `SaleItemResource` expone la moneda y el precio unitario en su moneda
  original; `SaleResource` expone `moneda` (base) junto al `total`.

## Seeder

- `CurrenciesSeeder` (llamado desde `DatabaseSeeder` y `DemoSeeder`,
  idempotente): CUP base con tasa 1 y USD con tasa configurable.
- `DemoSeeder`: el catálogo pasa a tener productos en las dos monedas y los
  precios en CUP se re-escalan a magnitudes cubanas realistas (los valores
  anteriores eran precios en euros).

## Alcance excluido

- Sin conversión automática de tasas por API externa: la tasa se administra a
  mano, que es lo que corresponde a un mercado con tasa informal.
- Sin histórico de tasas en tabla aparte: el snapshot por línea de venta cubre
  la necesidad real (no reescribir el pasado) sin añadir una tabla.
- Sin moneda por almacén ni por cliente.
