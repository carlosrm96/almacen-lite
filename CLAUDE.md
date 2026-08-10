# CLAUDE.md

Guía corta para trabajar en `almacen-lite`. Léela antes de tocar código.

## Qué es esto

API REST (sin frontend en este repo) para gestionar varios almacenes:
inventario por almacén, ventas con descuento automático de stock,
transferencias entre almacenes, métricas de ventas/inventario y auditoría de
movimientos. Es una copia **reducida** de `almacen-backend` (un WMS+ERP
completo): mismas convenciones, dominio mucho más pequeño — 6 módulos y 11
tablas, sin multi-empresa, sin ubicaciones, sin lotes, sin ERP.

Antes de asumir cómo funciona algo, lee el spec y el plan:

- **Diseño:** [`docs/superpowers/specs/2026-08-08-almacen-lite-design.md`](docs/superpowers/specs/2026-08-08-almacen-lite-design.md)
- **Plan de implementación (orden de las tareas, dependencias):** [`docs/superpowers/plans/2026-08-08-almacen-lite.md`](docs/superpowers/plans/2026-08-08-almacen-lite.md)
- **Guía funcional por módulo (para cliente/frontend, no repetir aquí):** [`docs/funcional/README.md`](docs/funcional/README.md)

No dupliques el contenido de esos documentos en este fichero: enlázalos y
mantenlos como fuente de verdad.

## Stack

- PHP ^8.3 · Laravel ^13
- `laravel/sanctum` (tokens de API) · `spatie/laravel-permission` (roles y
  permisos) · `spatie/laravel-query-builder` (filtros/orden/paginación)
- Dev: PHPUnit, Laravel Pint, Scribe (documentación de la API)
- Base de datos real: MySQL en `127.0.0.1:3310`, esquema `almacen_lite`. Los
  tests corren sobre SQLite en memoria.
- `APP_TIMEZONE=Europe/Madrid` — determina los cortes de día/semana/mes en
  métricas.

## Comandos

```bash
php artisan test              # toda la suite (SQLite en memoria)
php artisan test --filter=X   # un test o clase concretos
php artisan test --compact    # salida breve
vendor/bin/pint --dirty       # formatear solo lo modificado, antes de cada commit
vendor/bin/pint --test        # comprobar formato sin tocar nada
php artisan scribe:generate   # regenerar la doc de la API en /docs
php artisan migrate --seed    # esquema + roles/permisos (DatabaseSeeder)
php artisan db:seed --class="Database\Seeders\DemoSeeder"  # datos de demo
```

## Estructura de módulos

Monolito modular en `app/Modules/<Modulo>/`, con `Actions/`, `Enums/`,
`Http/{Controllers,Requests,Resources}`, `Models/`, `Policies/`,
`Providers/`, `routes.php`. Cada módulo aporta su `routes.php`, incluido
desde `routes/api.php` bajo el prefijo `/v1`.

| Módulo | Responsabilidad |
|---|---|
| `Access` | Login por token, usuarios, roles y permisos |
| `Warehouses` | Almacenes, stock por almacén, transferencias |
| `Catalog` | Productos, unidades, asignación unidad↔producto |
| `Sales` | Ventas y líneas de venta |
| `Metrics` | Métricas de ventas e inventario |
| `Audit` | Registro de auditoría (transversal, invocado desde las Actions) |

**Convenciones:** controladores delgados; la lógica de negocio va en
Actions; la validación, en Form Requests (que también comprueban el permiso
en `authorize()`, no solo el controlador); las respuestas siempre pasan por
API Resources, nunca modelos crudos; autorización con Policies.

## Reglas de dominio que no debes romper por accidente

- **La unidad base tiene factor 1**, por definición. El stock se guarda y se
  descuenta siempre en unidad base (`cantidad × unit.factor`).
- **Una venta es todo o nada.** Si un solo producto no llega al stock
  pedido, se rechaza la venta entera con `422` y un `productos_afectados`
  detallado; el inventario no se toca. Se valida todo antes de escribir
  nada (ver `RegisterSale`).
- **Cada línea de venta guarda un snapshot de `precio_venta` y
  `precio_compra`.** Cambiar la tarifa mañana no debe alterar el histórico
  ni las métricas de ayer.
- **El vendedor nunca ve `precio_compra` ni nada derivado de él** —
  `ganancia`, `top_productos`, `comparativa`, valor de inventario. Esto se
  resuelve con ramas explícitas en los Resources (`ProductResource`,
  `SaleItemResource`) y con `MetricsRoleFilter`, no con campos condicionales
  sueltos: un campo nuevo no debe filtrarse al vendedor por descuido.
- **El vendedor está siempre atado a un almacén** y solo puede pedir
  métricas `weekly`. El middleware `scope.warehouse` fuerza su
  `warehouse_id` e ignora el que venga en la petición, en vez de rechazarla.
- **Productos con borrado lógico** (`SoftDeletes`); almacenes y transferencias
  con guardas de borrado por FK (no se borra lo que tiene stock, ventas,
  usuarios o transferencias asociadas — se desactiva en su lugar).
- **Auditoría explícita, no observers.** Cada Action que cambia algo
  sensible llama a `AuditLogger` ella misma; no hay un listener implícito
  escuchando eventos de modelo.

## Flujo de trabajo

Spec → plan → TDD, tarea por tarea. Cada tarea del plan trae su propio test
primero (rojo), luego la implementación (verde), y termina con
`vendor/bin/pint --dirty` limpio y la suite completa en verde antes de
commitear. No adelantes trabajo de una tarea futura ni relajes las reglas de
arriba para que un test pase más rápido.
