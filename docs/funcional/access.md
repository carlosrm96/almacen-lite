# Acceso

## 1. ¿Para qué sirve?

Autenticar a quien usa la API y gestionar el equipo que trabaja con ella. El
dueño del negocio llega aquí ya registrado (ver [Empresa y
registro](tenancy.md)); desde entonces es él quien da de alta a los suyos, y
cada uno entra con un token que identifica su rol y, si es vendedor, su
almacén.

## 2. Conceptos clave

- **Las altas las hace el admin.** `POST /v1/users` crea usuarios dentro de su
  empresa. `POST /v1/register` existe, y es público, pero lo que crea es otro
  negocio aparte, no un usuario de este.
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

0. El dueño registra su negocio con `POST /v1/register` y queda como `admin`,
   con su token ya listo (ver [Empresa y registro](tenancy.md)).
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
| Registrar un negocio | `POST /v1/register` | público — ver [Empresa y registro](tenancy.md) |
| Login | `POST /v1/login` | público |
| Logout | `POST /v1/logout` | autenticado |
| Usuario autenticado | `GET /v1/me` | autenticado |
| Listar usuarios | `GET /v1/users` | `users.view` |
| Crear usuario | `POST /v1/users` | `users.create` |
| Ver usuario | `GET /v1/users/{id}` | `users.view` |
| Editar usuario | `PUT /v1/users/{id}` | `users.update` |
| Borrar usuario | `DELETE /v1/users/{id}` | `users.delete` |

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

- No hay auto-alta de vendedores ni cola de aprobación: al equipo lo da de alta
  el admin.
- No hay recuperación de contraseña por email ni verificación de email.
- No hay más roles que `admin` y `vendedor`, ni permisos configurables desde
  la API.
- Un usuario tiene como mucho un almacén asignado; no puede pertenecer a
  varios a la vez.
