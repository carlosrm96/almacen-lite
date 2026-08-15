# Acceso

## 1. ¿Para qué sirve?

Autenticar a quien usa la API y gestionar el equipo que trabaja con ella. Hay
un registro de puesta en marcha para crear el primer administrador; a partir
de ahí solo el admin da de alta usuarios, y cada uno entra con un token que
identifica su rol y, si es vendedor, su almacén.

## 2. Conceptos clave

- **Registro de puesta en marcha.** `POST /v1/register` crea el usuario
  administrador de la instalación y devuelve ya su token. **Solo funciona
  mientras no exista ningún usuario**: en cuanto hay uno, responde `403` y las
  altas pasan a hacerse desde `POST /v1/users`. No es un registro abierto
  como el de `almacen-backend` porque aquí no hay multi-empresa que aísle a
  cada registrado: quien se registra manda sobre todos los almacenes.
- **Login por token.** `POST /v1/login` valida email y contraseña y devuelve
  un token de Sanctum. Ese token va en cada petición siguiente, como
  `Authorization: Bearer <token>`.
- **Login y registro llevan su propio límite de peticiones.** Son las dos
  únicas rutas públicas: `throttle:auth`, 10 por minuto y por IP, en vez de
  las 60 del resto de la API.
- **Dos roles fijos, sin roles personalizados.** `admin` puede hacer
  cualquier cosa; `vendedor` solo tiene `products.view`, `sales.view`,
  `sales.create` y `metrics.view`. La lista de permisos por rol vive en
  `RolesAndPermissionsSeeder` — es la fuente única del RBAC del proyecto.
- **El vendedor siempre está atado a un almacén.** `warehouse_id` es
  obligatorio al crear o editar un usuario con rol `vendedor`; un vendedor sin
  almacén es un estado inválido y la API lo rechaza con `422`. Es ese
  `warehouse_id` el que el resto de la API usa para acotar lo que el vendedor
  ve y puede hacer.
- **Solo el admin gestiona usuarios.** Nadie puede borrarse a sí mismo, y no
  se puede borrar un usuario con ventas o transferencias registradas a su
  nombre (se conserva la atribución histórica).

## 3. Flujos de uso

0. En una instalación recién desplegada y todavía sin usuarios, alguien se
   registra con `POST /v1/register` y queda como `admin`, con su token ya
   listo. Es un paso que solo ocurre una vez.
1. El usuario entra con `POST /v1/login` (email + password) y recibe el token
   y sus propios datos (rol, almacén si aplica).
2. El cliente guarda el token y lo envía en cada petición posterior.
3. `GET /v1/me` sirve para refrescar los datos del usuario autenticado, por
   ejemplo tras recargar la aplicación.
4. `POST /v1/logout` revoca el token en uso; hay que volver a hacer login
   para obtener uno nuevo.
5. El admin gestiona el equipo desde `GET/POST/PUT/DELETE /v1/users`,
   asignando `vendedor` a un almacén concreto en el alta.

## 4. Cómo lo usa el frontend

| Acción | Método y ruta | Permiso |
|---|---|---|
| Registrar el primer admin | `POST /v1/register` | público (solo sin usuarios) |
| Login | `POST /v1/login` | público |
| Logout | `POST /v1/logout` | autenticado |
| Usuario autenticado | `GET /v1/me` | autenticado |
| Listar usuarios | `GET /v1/users` | `users.view` |
| Crear usuario | `POST /v1/users` | `users.create` |
| Ver usuario | `GET /v1/users/{id}` | `users.view` |
| Editar usuario | `PUT /v1/users/{id}` | `users.update` |
| Borrar usuario | `DELETE /v1/users/{id}` | `users.delete` |

El cuerpo del registro:

```json
{
  "name": "Ana",
  "email": "ana@almacen.test",
  "password": "secreto123",
  "password_confirmation": "secreto123"
}
```

Devuelve `201` con `{"token": "...", "user": {...}}`. No acepta `rol` ni
`warehouse_id`: el registrado es siempre `admin` y un admin no lleva almacén.

El cuerpo del alta:

```json
{
  "name": "Vendedor Norte",
  "email": "vendedor.norte@almacen.test",
  "password": "secreto123",
  "rol": "vendedor",
  "warehouse_id": 2
}
```

`warehouse_id` es obligatorio si `rol` es `vendedor`; si `rol` es `admin` se
ignora. Un login con credenciales incorrectas devuelve `422` con el error en
`email`, no `401`, para no revelar si el email existe.

## 5. Qué no hace todavía

- El registro solo sirve para el primer admin: no hay auto-alta de vendedores
  ni cola de aprobación.
- No hay recuperación de contraseña por email ni verificación de email.
- No hay más roles que `admin` y `vendedor`, ni permisos configurables desde
  la API.
- No hay multi-empresa: todos los usuarios comparten el mismo espacio de
  almacenes y productos.
- Un usuario tiene como mucho un almacén asignado; no puede pertenecer a
  varios a la vez.
