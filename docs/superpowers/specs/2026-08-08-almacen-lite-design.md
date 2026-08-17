# almacen-lite — Diseño

**Fecha:** 2026-08-08
**Estado:** aprobado
**Origen:** copia reducida de `almacen-backend` (WMS+ERP completo, 18 módulos, 611 archivos PHP, 94 migraciones).

> **Revocado en parte (2026-08-17).** Este documento da por descartada la
> multi-empresa y por inexistente el registro público (§1 «Fuera del alcance» y
> §5 regla 1). Ambas cosas volvieron: cada registro crea ahora su propia
> empresa aislada. Ver
> [2026-08-17-multi-empresa-y-registro-design.md](2026-08-17-multi-empresa-y-registro-design.md).
> El resto del documento sigue vigente.

---

## 1. Objetivo y alcance

API REST para la gestión de **múltiples almacenes**. Cada almacén administra su
propio inventario y genera sus propias métricas de ventas. Hay un panel
administrativo con métricas globales y desglosadas por almacén.

Es un proyecto **nuevo**, no un fork podado. Reutiliza convenciones y patrones de
`almacen-backend` (monolito modular, Form Requests, API Resources, Policies,
Actions), pero no arrastra su modelo de dominio.

### Dentro del alcance

- Dos roles: admin/owner y vendedor. Sin registro público.
- Almacenes con stock propio.
- Productos con sistema de unidades y factor de conversión.
- Ventas multi-producto con descuento automático de stock.
- Transferencias de stock entre almacenes (inmediatas, solo admin).
- Métricas de ventas (diaria, semanal, mensual) y de inventario.
- Auditoría de movimientos sobre productos.

### Fuera del alcance (heredado de `almacen-backend`, descartado)

Multi-empresa (Tenancy) y módulos por plan (Platform), zonas y ubicaciones,
lotes/series/caducidad, estados de stock (reservado, cuarentena, en tránsito,
bloqueado), estrategias FIFO/FEFO/LIFO, recepciones contra orden de compra,
salidas con picking, devoluciones, inventario cíclico, sincronización con ERP y
con dispositivos, y todo el ERP (facturación, compras, bancos, contabilidad,
tesorería, valoración, CRM, tarifas).

---

## 2. Stack

- PHP ^8.3 · Laravel ^13
- **Solo API**, sin frontend en este repo.
- `laravel/sanctum` — tokens de API
- `spatie/laravel-permission` — roles y permisos
- `spatie/laravel-query-builder` — filtros, orden y paginación en listados
- Dev: PHPUnit, Laravel Pint, Scribe (documentación de la API)
- **Base de datos:** MySQL en `127.0.0.1:3310`, esquema `almacen_lite`.
  Los tests corren sobre SQLite en memoria.
- **Zona horaria:** `APP_TIMEZONE=Europe/Madrid`. Determina el corte de día y de
  semana en las métricas.

Descartadas respecto al proyecto grande: dompdf, bacon-qr-code, sentry,
spatie/laravel-backup, spatie/laravel-health, laravel/boost, laravel/mcp.

---

## 3. Arquitectura

Monolito modular en `app/Modules/<Modulo>/`, con la misma estructura interna que
`almacen-backend`: `Actions/`, `Enums/`, `Http/{Controllers,Requests,Resources}`,
`Models/`, `Policies/`, `Providers/`, `routes.php`.

Las rutas se agregan en `routes/api.php` bajo el prefijo `/v1`, cada módulo
aportando su `routes.php`.

| Módulo | Responsabilidad | Depende de |
|---|---|---|
| `Access` | Autenticación, usuarios, roles y permisos | — |
| `Warehouses` | Almacenes, stock por almacén, transferencias | `Catalog`, `Audit` |
| `Catalog` | Productos, unidades, asignación unidad↔producto | `Audit` |
| `Sales` | Ventas y líneas de venta | `Catalog`, `Warehouses` |
| `Metrics` | Métricas de ventas e inventario | `Sales`, `Warehouses` |
| `Audit` | Registro de auditoría (transversal) | — |

**Convenciones (heredadas):** controladores delgados, lógica de negocio en
Actions/Services, validación en Form Requests, respuestas siempre vía API
Resources (nunca modelos crudos), una migración por tabla, tablas en plural
snake_case, autorización con Policies.

---

## 4. Modelo de datos

11 tablas propias, más las de Sanctum (`personal_access_tokens`) y las cinco de
spatie/laravel-permission.

```
users            id, name, email, password, warehouse_id (nullable), timestamps
warehouses       id, nombre, activo, timestamps
units            id, nombre, factor, timestamps
products         id, nombre, precio_compra, precio_venta, deleted_at, timestamps
product_units    id, product_id, unit_id, is_base, timestamps
stocks           id, product_id, warehouse_id, cantidad, minimo, timestamps
sales            id, warehouse_id, user_id, total, created_at, updated_at
sale_items       id, sale_id, product_id, unit_id, cantidad, cantidad_base,
                 precio_venta_unit, precio_compra_unit, subtotal
transfers        id, product_id, from_warehouse_id, to_warehouse_id,
                 cantidad_base, user_id, created_at
audit_logs       id, user_id, accion, auditable_type, auditable_id,
                 warehouse_id (nullable), datos (json), created_at
```

**Tipos:** cantidades en `decimal(14,3)`, precios e importes en `decimal(12,2)`.

**Índices y restricciones:**

- `product_units`: único `(product_id, unit_id)`; como máximo una fila con
  `is_base = true` por producto.
- `stocks`: único `(product_id, warehouse_id)`.
- `users.warehouse_id`: FK a `warehouses`, nullable en el esquema; la regla
  «vendedor ⇒ almacén obligatorio» se valida en el Form Request y en el modelo.
- `sales`: índices en `(warehouse_id, created_at)` y `(user_id, created_at)`
  para las agregaciones de métricas.
- `audit_logs`: índices en `(auditable_type, auditable_id)` y `created_at`.
- `products` usa soft delete (`deleted_at`).

### 4.1 Decisiones de modelado

**Catálogo global + stock por almacén.** El producto (nombre, precios, unidades)
se define una sola vez; `stocks` guarda la existencia en cada almacén. Es lo que
permite transferir stock sin duplicar productos y comparar el mismo producto
entre almacenes en las métricas. «La cantidad del producto» es la suma de sus
filas de `stocks`, o la del almacén filtrado.

**El `factor` vive en `units`.** Una unidad es un par (nombre, factor), tal como
dice la especificación funcional. Consecuencia aceptada: `caja = 24` es la misma
caja para todos los productos; un producto con cajas de 12 necesita otra unidad
(`caja 12`). `almacen-backend` pone el factor en la relación producto↔unidad;
si esa flexibilidad hiciera falta, mover `factor` a `product_units` es una
migración pequeña y localizada.

**Unidad base.** Todo producto se crea con exactamente una unidad base, cuyo
factor debe ser `1`. El stock se almacena y se descuenta siempre en unidad base.

**Snapshots de precio en `sale_items`.** Cada línea guarda el precio de venta y
el de compra vigentes en el momento de la venta. La ganancia histórica se
calcula con esos valores, no con los precios actuales, de modo que un cambio de
tarifa no reescribe el pasado.

---

## 5. Roles y autorización

Roles con spatie/laravel-permission, sembrados por seeder:

| Rol | Permisos |
|---|---|
| `admin` | Todos |
| `vendedor` | `products.view`, `sales.create`, `sales.view`, `metrics.view` |

**Reglas invariantes:**

1. Solo el admin crea, edita y elimina usuarios. No existe registro público.
2. Todo usuario con rol `vendedor` debe tener `warehouse_id`. Se valida al crear
   y al actualizar; un vendedor sin almacén es un estado inválido.
3. El vendedor solo opera sobre su almacén. Un middleware
   (`ScopeToOwnWarehouse`) fuerza `warehouse_id` al del usuario e **ignora** el
   que venga en la petición, en lugar de rechazarla.
4. El vendedor solo puede pedir métricas con `period=weekly`; cualquier otro
   valor devuelve `403`.
5. El vendedor nunca ve `precio_compra` ni ningún dato derivado de él
   (ganancia, valor de inventario a coste).

Autorización mediante Policies: `UserPolicy`, `WarehousePolicy`, `UnitPolicy`,
`ProductPolicy`, `SalePolicy`, `TransferPolicy`, y un Gate `metrics.*` para las
restricciones por periodo y alcance.

---

## 6. Endpoints

Prefijo `/v1`. Todo bajo `auth:sanctum` salvo el login.

### Autenticación

```
POST   /v1/login                  email + password → token
POST   /v1/logout
GET    /v1/me
```

### Solo admin

```
GET    /v1/users            POST /v1/users            (rol vendedor ⇒ warehouse_id requerido)
GET    /v1/users/{id}       PUT /v1/users/{id}        DELETE /v1/users/{id}

GET    /v1/warehouses       POST /v1/warehouses
GET    /v1/warehouses/{id}  PUT /v1/warehouses/{id}   DELETE /v1/warehouses/{id}

GET    /v1/units            POST /v1/units
GET    /v1/units/{id}       PUT /v1/units/{id}        DELETE /v1/units/{id}

POST   /v1/products         PUT /v1/products/{id}     DELETE /v1/products/{id}   (soft delete)
POST   /v1/products/{id}/units            asignar unidad al producto
DELETE /v1/products/{id}/units/{unit}     desasignar (nunca la unidad base)
POST   /v1/products/{id}/stock            fijar stock en un almacén

POST   /v1/transfers        GET /v1/transfers
GET    /v1/audit-logs                     filtros: usuario, acción, producto, rango de fechas
GET    /v1/metrics/inventory?warehouse_id=&umbral=
```

### Admin y vendedor

```
GET    /v1/products                       campos según rol
GET    /v1/products/{id}
POST   /v1/sales                          devuelve el total de la venta
GET    /v1/sales                          GET /v1/sales/{id}
GET    /v1/metrics/sales?period=&date=&warehouse_id=
```

### Visibilidad de producto por rol

Un único `ProductResource` con **dos ramas explícitas**:

- **admin:** `id, nombre, precio_compra, precio_venta, unidades, stocks por
  almacén, timestamps`.
- **vendedor:** `id, nombre, precio_venta, cantidad` (la de su almacén) y las
  unidades asignadas (necesarias para vender).

Ramas explícitas, no campos condicionales sobre el conjunto completo: así un
campo nuevo no se filtra al vendedor por descuido.

### Contrato de errores

Errores de validación estándar de Laravel (`422` con `errors`). El caso de
stock insuficiente devuelve `422` con un cuerpo específico:

```json
{
  "message": "Stock insuficiente",
  "errors": {
    "items": ["Stock insuficiente para 1 producto."]
  },
  "productos_afectados": [
    {"product_id": 7, "nombre": "Agua 1L", "solicitado": 48, "disponible": 30}
  ]
}
```

---

## 7. Lógica de negocio

### 7.1 Registrar una venta — `RegisterSale`

Una única transacción de base de datos:

1. Resolver el almacén: el del vendedor (forzado por middleware), o el indicado
   por el admin.
2. Para cada ítem, resolver la unidad. Si no se indica, la unidad base del
   producto. La unidad debe estar asignada a ese producto.
3. Convertir a unidad base: `cantidad_base = cantidad × unit.factor`.
4. Bloquear las filas de `stocks` implicadas con `lockForUpdate()`.
5. **Validar todos los ítems antes de escribir nada.** Si alguno supera el stock
   disponible, abortar con `422` y la lista de productos afectados: no se
   registra la venta ni se altera el inventario de ningún ítem.
6. Descontar `cantidad_base` de cada fila de `stocks`.
7. Crear `sales` y sus `sale_items` con los snapshots de precio.
8. Devolver el total: `Σ (precio_venta × cantidad_base)`.

Un mismo producto repetido en varios ítems suma sus cantidades a efectos de la
comprobación de stock.

### 7.2 Alta de producto — `CreateProduct`

`POST /v1/products` acepta nombre, precios, la unidad base (`unit_id` con factor
`1`) y, **opcionalmente**, `warehouse_id` + `cantidad` para crear la existencia
inicial en un almacén en la misma llamada. Sin esos dos campos el producto nace
sin stock y se le asigna después con `POST /v1/products/{id}/stock`. Ambas vías
escriben en `stocks` y quedan auditadas.

`POST /v1/products/{id}/stock` **fija** la cantidad del producto en un almacén
(no la incrementa); la entrada de auditoría guarda el valor anterior y el nuevo.

### 7.3 Transferencia entre almacenes — `TransferStock`

Solo admin, inmediata (sin aprobación), en una transacción: valida que origen y
destino son distintos, convierte a unidad base, bloquea la fila de origen,
comprueba disponibilidad, resta en origen, suma en destino (creando la fila de
`stocks` si aún no existe), registra `transfers` y escribe la entrada de
auditoría.

### 7.4 Eliminación de productos, unidades y almacenes

**Productos:** soft delete — se marca `deleted_at`, no se borra la fila. Los
productos eliminados desaparecen de los listados y no pueden venderse ni
transferirse, pero sus `sale_items` históricos siguen siendo válidos y siguen
contando en las métricas de periodos pasados. Queda registrado en auditoría
quién los eliminó.

**Unidades:** `DELETE /v1/units/{id}` se rechaza con `422` si la unidad está
asignada a algún producto o aparece en alguna línea de venta.

**Almacenes:** `DELETE /v1/warehouses/{id}` se rechaza con `422` si el almacén
tiene stock distinto de cero, ventas registradas o usuarios asignados. Para
retirarlo de circulación sin borrarlo está el campo `activo`.

### 7.5 Auditoría

Un servicio `AuditLogger` invocado **explícitamente** desde las Actions —
`CreateProduct`, `UpdateProduct`, `DeleteProduct`, `SetProductStock`,
`TransferStock` — en lugar de un observer implícito: el rastro es legible
siguiendo el código.

Cada entrada guarda usuario, acción, tipo y id del objeto afectado, almacén
cuando aplica, fecha/hora y un `datos` JSON con el detalle (campos cambiados,
cantidades, almacenes origen/destino).

---

## 8. Métricas

Se calculan **al vuelo** con agregación SQL sobre `sales` y `sale_items`, sin
tablas de agregados precalculados. Con el volumen esperado (un puñado de
almacenes) sobra, y se evita el riesgo de agregados desincronizados.

### 8.1 Periodos

| Periodo | Ventana | Serie de tiempo |
|---|---|---|
| `daily` | El día de `date` | Por hora (24 puntos) |
| `weekly` | Semana ISO (lunes–domingo) que contiene `date` | Por día (7 puntos) |
| `monthly` | Mes natural de `date` | Por día |

Si no se indica `date`, se usa el momento actual. Los cortes se calculan en la
zona horaria de la aplicación.

### 8.2 Métricas calculadas

Un `SalesMetricsReporter` produce el conjunto completo, a nivel global (sin
`warehouse_id`) o por almacén:

1. **Ingresos totales** — suma de `sales.total` del periodo.
2. **Número de ventas** — cuenta de transacciones.
3. **Unidades vendidas** — suma de `cantidad_base`.
4. **Ganancia** — `Σ (precio_venta_unit − precio_compra_unit) × cantidad_base`.
5. **Ticket promedio** — ingresos ÷ número de ventas (`0` si no hay ventas).
6. **Top productos** — los 10 más vendidos, por unidades y por ingresos.
7. **Ventas por vendedor** — ingresos y número de ventas por usuario.
8. **Comparativa con el periodo anterior** — variación porcentual de ingresos y
   de número de ventas frente al día/semana/mes previo. Si el periodo anterior
   tiene ingresos `0`, la variación se devuelve como `null`, no como infinito.
9. **Serie de tiempo** — según la tabla de 8.1.

### 8.3 Recorte por rol

Un filtro posterior al cálculo elimina lo que el vendedor no puede ver:

| Métrica | Admin | Vendedor (weekly, su almacén) |
|---|---|---|
| Ingresos, nº ventas, unidades, ticket promedio | ✔ | ✔ |
| Serie de tiempo | ✔ | ✔ |
| Ganancia | ✔ | ✘ (expone `precio_compra`) |
| Top productos | ✔ | ✘ |
| Ventas por vendedor | ✔ | Solo las suyas |
| Comparativa con periodo anterior | ✔ | ✘ |
| Valor de inventario / stock bajo | ✔ | ✘ |

### 8.4 Métricas de inventario (solo admin)

`GET /v1/metrics/inventory` devuelve, por almacén (o el conjunto si no se filtra):

- **Valor del inventario:** `Σ cantidad × precio_compra` y
  `Σ cantidad × precio_venta`.
- **Productos con stock bajo:** filas de `stocks` con `cantidad <= minimo`. El
  umbral es por `(producto, almacén)`; el parámetro `?umbral=` permite forzar
  uno global para la consulta.

---

## 9. Estrategia de pruebas

TDD: test primero, implementación después. PHPUnit sobre SQLite en memoria,
factories para todos los modelos, seeder de roles y permisos.

Feature tests por módulo, cubriendo:

- **Access:** login, matriz de permisos por rol, creación de usuarios solo por
  admin, rechazo de vendedor sin almacén.
- **Catalog:** CRUD, la unidad base debe tener factor 1, no se puede desasignar
  la unidad base, soft delete deja rastro en auditoría, visibilidad de campos
  por rol (el vendedor **no** recibe `precio_compra` en ninguna respuesta),
  alta de producto con stock inicial, rechazo de borrado de unidad en uso.
- **Warehouses:** transferencia correcta, rechazo por stock insuficiente,
  rechazo de transferencia con origen = destino, creación de la fila de stock en
  destino cuando no existía, rechazo de borrado de almacén con stock o ventas.
- **Sales:** conversión de unidades, venta multi-producto, venta con un ítem sin
  stock que deja el inventario **intacto**, producto repetido en varios ítems,
  el vendedor no puede vender en otro almacén, el total devuelto es correcto.
- **Metrics:** cada métrica con datos conocidos, los tres periodos y sus series,
  comparativa con periodo anterior vacío, recorte por rol, `403` para vendedor
  con `period != weekly`.
- **Audit:** cada acción auditable genera exactamente una entrada con el usuario
  correcto.

Se considera terminado un módulo cuando sus tests pasan, Pint está limpio y sus
endpoints están documentados con Scribe.

---

## 10. Riesgos y limitaciones conocidas

- **Factor por unidad, no por producto** (§4.1): limita el reuso de nombres de
  unidad entre productos con empaquetados distintos.
- **Métricas al vuelo:** si el volumen de ventas creciera mucho, las
  agregaciones sobre `sale_items` pedirán índices adicionales o una tabla de
  agregados. No se optimiza por adelantado.
- **Concurrencia:** el bloqueo pesimista sobre `stocks` protege venta y
  transferencia. Requiere un motor transaccional real (InnoDB); en SQLite los
  tests de concurrencia son necesariamente limitados.
- **Sin trazabilidad de stock:** a diferencia de `almacen-backend`, no hay
  ledger de movimientos de inventario. La auditoría cubre acciones sobre
  productos y transferencias, no cada variación de stock derivada de una venta;
  esa variación es reconstruible desde `sale_items`.
