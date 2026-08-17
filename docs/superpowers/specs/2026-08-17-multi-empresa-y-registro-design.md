# Diseño: multi-empresa y registro público

Fecha: 2026-08-17
Estado: implementado

## Problema

`almacen-lite` nació como una instalación de un solo negocio. El registro
(`POST /v1/register`) crea el admin dueño y se cierra con `403` en cuanto
existe cualquier usuario; a partir de ahí las altas las hace ese admin desde
`POST /v1/users`. La razón era buena: sin nada que aísle a un registrado de
otro, dejar el registro abierto convertiría a cualquiera que conozca la URL en
operador del inventario ajeno.

Lo que se necesita es lo que hace `almacen-backend`: que cualquiera pueda
registrarse, autenticarse y, ya dentro, crear sus propios almacenes y su
catálogo sin ver ni tocar los de nadie más. Eso no es abrir el registro: es
reintroducir el aislamiento por empresa que el diseño original
([2026-08-08](2026-08-08-almacen-lite-design.md), §"Fuera del alcance")
descartó.

## Decisiones

### La empresa es el inquilino

Tabla `companies` (`nombre`, `activo`) y `company_id` en todas las tablas del
dominio. La alternativa —usar el usuario dueño como raíz, con `owner_id` y sin
tabla— ahorra una tabla y cuesta caro: el negocio pasaría a ser una persona,
sin sitio donde guardar su nombre, y borrar o traspasar al dueño dejaría
huérfano el inventario.

Llevan `company_id` NOT NULL: `users`, `warehouses`, `units`, `products`,
`product_units`, `stocks`, `sales`, `sale_items`, `transfers`, `audit_logs` y
`currencies`. Como todavía no hay datos en producción, la columna entra en las
migraciones existentes en lugar de por una migración de relleno: el esquema
queda limpio y nada tiene que tolerar filas sin empresa.

### Índices únicos por empresa

`warehouses.nombre`, `units.nombre` y `currencies.codigo` eran únicos globales.
Pasan a ser únicos **por empresa** (`unique(['company_id', ...])`): que un
negocio llame "Central" a su almacén no puede impedir que otro haga lo mismo.
`stocks(product_id, warehouse_id)` y `product_units(product_id, unit_id)` ya
son compuestos y ambas columnas apuntan a filas del mismo inquilino, así que no
cambian.

`users.email` sigue siendo único **global**, igual que en `almacen-backend`: un
email es una cuenta. Que la misma persona pertenezca a dos negocios no está
soportado, y hacerlo único por empresa convertiría el login —que ocurre antes
de saber qué empresa es— en ambiguo.

### Aislamiento en tres capas

**1. Contexto.** `CurrentCompany` es un singleton por petición que guarda la
empresa activa. Lo fija el middleware `ResolveCurrentCompany` a partir del
usuario autenticado, y aborta con `403` si la empresa está inactiva. Empieza
poniéndolo a `null` para que un worker de larga vida no herede la empresa de la
petición anterior.

El middleware se registra con alias `tenant` y se añade explícitamente a los
grupos `auth:sanctum` de cada módulo, **no** como *append* al grupo global de
la API. El grupo global corre antes que el middleware de ruta, así que ahí
`$request->user()` todavía no está resuelto; atarlo detrás de `auth:sanctum`
hace el orden explícito en vez de depender de la ordenación por prioridad de
Laravel.

**2. Lectura y escritura.** El trait `BelongsToCompany` añade un scope global
que filtra por la empresa de contexto y un hook `creating` que rellena
`company_id` solo. Las Actions existentes no cambian: siguen creando filas como
hasta ahora y quedan atadas a la empresa correcta. Cuando no hay contexto
(consola, seeders, el propio registro) el scope no filtra y el `company_id` hay
que darlo a mano — es lo que hace `RegisterCompany`.

**3. Validación.** Las reglas `exists` y `unique` de Laravel consultan la tabla
en crudo y **no** pasan por el scope global. Sin blindarlas, un admin podría
atar un vendedor al almacén de otro negocio con un `warehouse_id` adivinado, y
un `unique` filtraría qué códigos existen en otras empresas. El trait
`ScopesValidationToCompany` da `companyScopedExists()` y `companyScopedUnique()`,
que añaden el filtro `company_id`, y los Form Requests los usan en lugar de
`Rule::exists`/`Rule::unique` sobre tablas del inquilino.

Las métricas se salvan solas: sus consultas parten de un builder de Eloquent y
hacen `join()` sobre él, así que la tabla base queda filtrada por el scope y
las unidas se alcanzan por id dentro del mismo inquilino. Ninguna usa
`DB::table`.

### Registro

`POST /v1/register` deja de ser de un solo uso y deja de responder `403`. Sigue
bajo `throttle:auth`, que es el limitador estrecho de las rutas públicas.

Al cuerpo actual (`name`, `email`, `password`, `password_confirmation`) se le
suma `empresa`, el nombre del negocio. Plano, sin anidar `company.*`/`user.*`
como el backend: anidar rompería el contrato que ya consumen los clientes a
cambio de nada.

`RegisterAdmin` se convierte en `RegisterCompany`, dentro del módulo `Tenancy`.
En una transacción crea la empresa, crea al usuario con rol `admin` y sin
almacén, y despacha `CompanyRegistered`. Responde `201` con el token, el
usuario y la empresa.

Crear almacenes ya funciona: `POST /v1/warehouses` es solo-admin y con el
aislamiento pasa a crear dentro de la empresa de quien llama, sin tocar nada.

### Monedas por empresa

Las monedas dejan de ser catálogo global de la instalación y pasan a ser de
cada empresa. En Cuba la tasa del USD la fija el negocio y cambia por su
cuenta: es dato de negocio, no de despliegue. Con monedas globales, el dueño
del servidor la fijaría para todos.

Un listener en `Catalog` escucha `CompanyRegistered` y siembra CUP y USD para
la empresa nueva, con `ALMACEN_TASA_USD` para el USD y tasa 1 para la base.
El evento evita que `Tenancy` dependa de `Catalog`.

`Currency::base()` memoriza la moneda base en un binding «scoped» del
contenedor. Como ese binding vive lo mismo que la petición y la empresa también,
sigue siendo correcto sin cambios: cada petición resuelve la base de su propia
empresa.

Las unidades siguen sin sembrarse. Las crea el admin, como hasta ahora, y ahora
son suyas.

`CurrenciesSeeder` deja de correr desde `DatabaseSeeder`: sin empresa no hay
monedas que sembrar. `DatabaseSeeder` se queda con roles y permisos, que son
globales de la instalación. `DemoSeeder` crea su propia empresa demo.

### Qué deja de ser verdad

Dos reglas del diseño original y de `CLAUDE.md` se caen y hay que reescribirlas:

- *"`POST /v1/register` es de un solo uso"* — ahora cada registro crea su
  propia empresa aislada, que es justo la condición que faltaba para poder
  abrirlo.
- *"Multi-empresa (Tenancy): fuera del alcance"* — vuelve al alcance, en su
  forma mínima: una tabla, un trait, un scope y un middleware. Sin planes por
  empresa, sin módulos contratables, sin super-admin de plataforma.

El resto del dominio no se mueve: unidad base con factor 1, venta todo o nada,
snapshot de precios y tasa en cada línea, moneda base con tasa 1, importes
agregados en moneda base, el vendedor sin ver `precio_compra` y atado a su
almacén.

## API

| Método | Ruta | Cambio |
|---|---|---|
| `POST` | `/v1/register` | Público y repetible. Nuevo campo `empresa`. Devuelve también `company`. |
| `GET` | `/v1/company` | Nuevo. La empresa de quien llama. |
| `PUT` | `/v1/company` | Nuevo. Renombrar la empresa. Solo admin. |

El resto de endpoints mantiene su contrato; lo único que cambia es que cada uno
ve solo lo de su empresa. Un recurso de otra empresa responde `404`, no `403`:
el scope global lo hace invisible, que es la respuesta correcta —confirmar su
existencia ya sería una fuga.

## Tests

Los tests existentes necesitan contexto de empresa: `actingAsRole()` crea la
empresa, ata el usuario a ella y fija `CurrentCompany`; las factories la crean
o la heredan.

Casos nuevos:

- Listar, leer, actualizar y borrar un recurso de otra empresa da `404`.
- Asignar un vendedor a un almacén de otra empresa da `422`.
- Dos empresas pueden tener un almacén, una unidad o una moneda con el mismo
  nombre o código.
- Las métricas de una empresa no cuentan las ventas de otra.
- El registro es repetible y cada uno arranca con sus monedas.
- La resolución del inquilino se prueba con un token Bearer real, no con
  `Sanctum::actingAs`: fijar el contexto en el setup enmascara un fallo en el
  propio middleware.
- Una empresa inactiva da `403`.

## Alcance excluido

Planes y módulos contratables por empresa, panel de super-admin de plataforma,
invitaciones, verificación de email, cambio de empresa de un usuario, borrado
de empresas y que una persona pertenezca a varias. Cada uno es su propia
conversación.
