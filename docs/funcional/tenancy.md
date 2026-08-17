# Empresa y registro

## 1. ¿Para qué sirve?

Cada negocio que se registra en la aplicación tiene lo suyo: sus almacenes, su
catálogo, sus unidades, sus monedas, sus ventas, su equipo. Nadie de otro
negocio los ve. Eso es lo que permite que el registro esté abierto: quien se
registra no aterriza en el inventario de otro, sino en el suyo, vacío.

## 2. Conceptos clave

- **La empresa es el negocio.** Se crea al registrarse y todo lo demás cuelga
  de ella. No hay forma de moverse entre empresas ni de ver las de otros: la
  empresa se deduce del token, no se pide en la petición.
- **Registro abierto.** `POST /v1/register` crea la empresa y su usuario
  administrador —el dueño— y devuelve ya su token. Se puede repetir tantas
  veces como negocios se registren. No acepta `rol` ni `warehouse_id`: quien se
  registra es siempre `admin` de la empresa que acaba de crear, y un admin no
  lleva almacén asignado.
- **El resto del equipo lo da de alta el admin**, desde `POST /v1/users`, y
  entra en su misma empresa. No hay auto-alta de vendedores ni invitaciones.
- **Un email es una cuenta.** El email es único en toda la instalación, no por
  empresa: la misma persona no puede tener cuenta en dos negocios.
- **Lo que no se ve, no existe.** Pedir un recurso de otra empresa por su id
  devuelve `404`, no `403`. Confirmar que existe ya sería decir de más.
- **Los nombres se repiten sin problema.** Dos negocios pueden llamar
  «Central» a su almacén o «Caja» a su unidad: los únicos son por empresa.
- **Cada negocio tiene sus monedas.** Al registrarse se le siembran CUP y USD;
  a partir de ahí la tasa del USD la ajusta él y no afecta a nadie más.
- **Una empresa desactivada deja fuera a los suyos.** Sus usuarios reciben
  `403` en cualquier petición, aunque su token siga siendo válido. Solo se
  desactiva desde la base de datos: la API no lo permite, para que un admin no
  pueda cerrarse la puerta a sí mismo.

## 3. Flujos de uso

1. Alguien registra su negocio con `POST /v1/register` y recibe el token de su
   usuario administrador.
2. Con ese token crea sus almacenes (`POST /v1/warehouses`), sus unidades y su
   catálogo.
3. Da de alta a sus vendedores con `POST /v1/users`, cada uno atado a un
   almacén suyo.
4. `GET /v1/company` devuelve los datos del negocio; `PUT /v1/company` lo
   renombra.

## 4. Cómo lo usa el frontend

| Acción | Método y ruta | Permiso |
|---|---|---|
| Registrar un negocio | `POST /v1/register` | público |
| Ver mi empresa | `GET /v1/company` | `company.view` |
| Renombrar mi empresa | `PUT /v1/company` | `company.update` |

El cuerpo del registro:

```json
{
  "empresa": "Bodega La Habana",
  "name": "Ana",
  "email": "ana@almacen.test",
  "password": "secreto123",
  "password_confirmation": "secreto123"
}
```

Devuelve `201` con `{"token": "...", "user": {...}, "company": {...}}`.

## 5. Qué no hace todavía

- No hay invitaciones ni verificación de email.
- Un usuario pertenece a una sola empresa y no se puede mover a otra.
- No se puede borrar una empresa desde la API, ni hay panel para administrarlas
  todas.
- No hay planes ni módulos contratables por empresa.
