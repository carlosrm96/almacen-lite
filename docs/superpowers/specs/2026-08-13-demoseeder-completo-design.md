# DemoSeeder completo — dataset de demo para pruebas de API y frontend

**Fecha:** 2026-08-13
**Estado:** aprobado (diseño)

## Objetivo

Sustituir el `DemoSeeder` mínimo actual por un seeder que deje la base de datos
con datos en **todos** los módulos, de modo que:

1. El equipo de frontend tenga un entorno de pruebas con usuarios conocidos,
   catálogo, ventas y métricas no vacías.
2. Se puedan verificar los 37 endpoints de la API con datos reales, incluidas
   las métricas (serie temporal, top de productos, ventas por vendedor,
   comparativa) y la auditoría.

No se introduce el concepto de "empresa" ni un super-admin: almacen-lite **no**
tiene multi-empresa, y el rol `admin` ya tiene control total. "Empresa de test"
se traduce aquí en un **dataset de demo completo**.

## Alcance

- **Sí:** ampliar `database/seeders/DemoSeeder.php` (mismo archivo, misma clase).
- **No:** nuevas migraciones, columna `is_super_admin`, `Gate::before`, ni
  cambios en el modelo de autorización. Eso pertenece a `almacen-backend`.
- **No:** fabricar entradas de auditoría que la app real no generaría (las
  ventas, por diseño, no se auditan).

## Datos a sembrar

### Actores y catálogo (base, `firstOrCreate` → idempotente)

| Elemento | Detalle |
|---|---|
| Almacenes | `Almacén Central`, `Almacén Norte` (ya existen) |
| admin | `admin@almacen.test` / `secreto123`, rol `admin` |
| vendedor Central | `vendedor@almacen.test` / `secreto123`, `warehouse_id` = Central |
| vendedor Norte | `vendedor.norte@almacen.test` / `secreto123`, `warehouse_id` = Norte |
| Unidades | `unidad` (factor 1, base), `caja` (factor 24) |
| Catálogo | ~8 productos con precios variados (amplía los 3 actuales) |
| Stock | Cantidades holgadas en ambos almacenes; **al menos un producto bajo mínimo** |

Contraseñas de demo conocidas (`secreto123`): aceptable para un entorno de
pruebas **no público**. Se documenta el riesgo al sembrar en producción.

### Movimientos (bloque guardado por idempotencia)

- **Altas de catálogo y stock vía Actions**, actuando como el admin, para que
  `/v1/audit-logs` tenga variedad real de acciones:
  - Cada producto se crea con `CreateProduct` → auditoría `producto.creado`.
    `CreateProduct` solo engancha la **unidad base**; la unidad `caja` se
    adjunta después con `$product->units()->firstOrCreate(...)`.
  - El stock de cada almacén se fija con `SetProductStock` (que acepta
    `minimo`) → auditoría `stock.fijado`. `CreateProduct` solo cubre un
    almacén, así que el segundo se siembra con una llamada extra.
- **Ventas vía `RegisterSale`** (Action real: descuenta stock, guarda snapshot
  de `precio_venta`/`precio_compra`), repartidas ~8 semanas hacia atrás
  retro-fechando `created_at` tras crearlas. Reparto que enciende cada métrica:
  - Varias **hoy** a distintas horas → serie `daily`.
  - A lo largo de **esta semana** y la **anterior** → serie `weekly` y
    `comparativa` no nula.
  - En **este mes** y el **anterior** → `monthly`.
  - Mezcla de los tres usuarios (admin, vendedor Central, vendedor Norte) y de
    ambos almacenes → `ventas_por_vendedor` con varias filas.
- **Transferencias vía `TransferStock`** (Central→Norte, unas pocas): mueven
  stock y escriben auditoría `transferencia.realizada` → `/v1/transfers` y
  `/v1/audit-logs` con datos reales.

## Idempotencia

- Actores, unidades y catálogo: `firstOrCreate` / `updateOrCreate` (re-ejecutable
  sin duplicar).
- Bloque de movimientos (ventas y transferencias): protegido con
  `if (Sale::count() === 0) { ... }`. Así, volver a correr el seeder no duplica
  ventas ni agota stock dos veces. Las transferencias se incluyen en la misma
  guarda.
- Consecuencia: para regenerar los movimientos desde cero hay que vaciar
  `sales`/`sale_items`/`transfers`/`audit_logs` (documentado), no basta con
  re-ejecutar.

## Retro-fechado de ventas

`RegisterSale` crea la venta con `now()`. Tras cada venta, el seeder actualiza
`created_at` (y `updated_at`) de la fila `sales` a la fecha objetivo. Las
métricas agregan por `sales.created_at`, así que solo esa columna importa; no
hace falta tocar `sale_items`.

Las fechas se calculan con `CarbonImmutable::now()` y offsets relativos
(`->subDays()`, `->subWeeks()`), respetando `APP_TIMEZONE=Europe/Madrid`, que es
lo que determina los cortes de día/semana/mes en las métricas.

## Verificación (fuera del seeder, tarea posterior)

1. Suite completa en verde (`php artisan test`) y `pint --dirty` limpio.
2. Sembrar en local (SQLite) y comprobar que `/v1/metrics/sales?period=weekly`,
   `?period=daily`, `?period=monthly`, `/v1/metrics/inventory` y
   `/v1/audit-logs` devuelven datos.
3. Correr los 37 endpoints contra **producción** logueado como admin y como
   vendedor; verificar en particular:
   - El vendedor **no** ve `precio_compra`, `ganancia`, `top_productos`,
     `comparativa` ni valor de inventario.
   - `scope.warehouse` fuerza el almacén del vendedor.
4. Corregir lo que falle. Ya detectados (a tratar aparte, no son del seeder):
   - `/api/up` → 500 al renderizar HTML (200 en JSON).
   - `/api/docs.openapi` → 500 (falta `storage/app/scribe/openapi.yaml`).

## Despliegue en producción

Tras mergear, ejecutar en el servidor:

```bash
php artisan db:seed --class="Database\Seeders\DemoSeeder" --force
```

Nota de seguridad: usa contraseñas conocidas; úsese solo en un entorno de
pruebas no público, o cámbiense las contraseñas después.
