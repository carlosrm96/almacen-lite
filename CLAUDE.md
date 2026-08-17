# CLAUDE.md

Guía corta para trabajar en `almacen-lite`. Léela antes de tocar código.

## Qué es esto

API REST (sin frontend en este repo) para gestionar varios almacenes:
inventario por almacén, ventas con descuento automático de stock,
transferencias entre almacenes, métricas de ventas/inventario y auditoría de
movimientos. Cada negocio que se registra tiene lo suyo y no ve lo de nadie
más. Es una copia **reducida** de `almacen-backend` (un WMS+ERP completo):
mismas convenciones, dominio mucho más pequeño — 7 módulos y 13 tablas, con
multi-empresa en su forma mínima, pero sin ubicaciones, sin lotes y sin ERP.

Antes de asumir cómo funciona algo, lee el spec y el plan:

- **Diseño:** [`docs/superpowers/specs/2026-08-08-almacen-lite-design.md`](docs/superpowers/specs/2026-08-08-almacen-lite-design.md)
  (su §"Fuera del alcance" descartaba la multi-empresa; ese punto lo revoca el
  spec de abajo)
- **Monedas y zona horaria (Cuba):** [`docs/superpowers/specs/2026-08-14-monedas-y-zona-horaria-cuba-design.md`](docs/superpowers/specs/2026-08-14-monedas-y-zona-horaria-cuba-design.md)
- **Multi-empresa y registro público:** [`docs/superpowers/specs/2026-08-17-multi-empresa-y-registro-design.md`](docs/superpowers/specs/2026-08-17-multi-empresa-y-registro-design.md)
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
- `APP_TIMEZONE=America/Havana` — determina los cortes de día/semana/mes en
  métricas.
- `ALMACEN_MONEDA_BASE=CUP` — moneda de todos los importes agregados.
  `ALMACEN_TASA_USD` fija la tasa con la que se **siembra** USD.

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
| `Tenancy` | Empresas, registro público y aislamiento entre negocios |
| `Access` | Login por token, usuarios, roles y permisos |
| `Warehouses` | Almacenes, stock por almacén, transferencias |
| `Catalog` | Productos, unidades, asignación unidad↔producto, monedas |
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
- **Cada línea de venta guarda un snapshot de `precio_venta`,
  `precio_compra`, `moneda_codigo` y `tasa_cambio`.** Cambiar la tarifa o
  devaluar la moneda mañana no debe alterar el histórico ni las métricas de
  ayer.
- **La moneda base tiene tasa 1**, por definición, igual que la unidad base
  tiene factor 1. `products.currency_id` nulo significa moneda base. Las
  monedas son de cada empresa y se le siembran al registrarse; `ALMACEN_TASA_USD`
  es solo el valor de partida, no la tasa de la instalación.
- **Los importes agregados se guardan en moneda base.** `sales.total` y
  `sale_items.subtotal` van convertidos; los `precio_*_unit` se quedan en la
  moneda del producto. Es lo que permite que `SUM(total)` siga siendo válido
  con un catálogo que mezcla CUP y USD — no lo rompas sumando precios
  unitarios sin multiplicar por `tasa_cambio`.
- **El vendedor nunca ve `precio_compra` ni nada derivado de él** —
  `ganancia`, `top_productos`, `comparativa`, valor de inventario. Esto se
  resuelve con ramas explícitas en los Resources (`ProductResource`,
  `SaleItemResource`) y con `MetricsRoleFilter`, no con campos condicionales
  sueltos: un campo nuevo no debe filtrarse al vendedor por descuido.
- **`POST /v1/register` es público y repetible:** crea una empresa y su admin
  dueño. Lo que hace seguro dejarlo abierto es el aislamiento, no una guarda en
  el endpoint. No acepta `rol` ni `warehouse_id` del cliente.
- **Todo lo del dominio lleva `company_id` y `BelongsToCompany`.** El trait
  filtra las lecturas por la empresa de contexto y rellena `company_id` al
  crear; `company_id` no es fillable en ningún modelo. Un modelo nuevo sin el
  trait es una fuga entre negocios, no un descuido menor.
- **Toda regla `exists`/`unique` sobre una tabla con `company_id` va por
  `ScopesValidationToCompany`.** Las reglas nativas consultan la tabla en crudo
  y se saltan el scope: un `exists:warehouses,id` sin acotar acepta el almacén
  de otro negocio. La excepción es `users.email`, único global a propósito.
- **Un recurso de otra empresa responde `404`, no `403`.** Lo hace el scope
  solo; confirmar que existe ya sería una fuga.
- **El contexto de empresa se resuelve del token, nunca de la petición.**
  `ForgetCurrentCompany` lo limpia al principio de cada petición (resolver el
  token pasa por el scope de `User`) y `tenant` lo fija tras `auth:sanctum`,
  con prioridad explícita para ir por delante de `SubstituteBindings`.
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
