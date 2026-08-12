# almacen-lite

API REST de gestión de almacenes: inventario por almacén, ventas con descuento
automático de stock, transferencias, métricas y auditoría.

Copia reducida de `almacen-backend` (WMS+ERP completo): 6 módulos y 11 tablas
frente a 18 módulos y 94 migraciones. Sin multi-empresa, sin ubicaciones, sin
lotes ni ERP.

## Puesta en marcha

```bash
composer install
cp .env.example .env && php artisan key:generate
# crear el esquema `almacen_lite` en MySQL (127.0.0.1:3310)
php artisan migrate --seed   # esquema + roles y permisos
php artisan db:seed --class="Database\Seeders\DemoSeeder"   # datos de demostración
php artisan serve
```

Usuarios que crea el `DemoSeeder`, ambos con contraseña `secreto123`:

| Email | Rol |
|---|---|
| `admin@almacen.test` | admin |
| `vendedor@almacen.test` | vendedor (Almacén Central) |

## Uso

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/v1/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@almacen.test","password":"secreto123"}' | jq -r .token)

curl -s http://localhost:8000/v1/products -H "Authorization: Bearer $TOKEN"
```

## Comandos

```bash
php artisan test              # toda la suite (SQLite en memoria)
php artisan test --compact    # salida breve
vendor/bin/pint               # formateo
php artisan scribe:generate   # regenerar la doc de la API en /docs
```

## Documentación

- **Diseño:** [`docs/superpowers/specs/2026-08-08-almacen-lite-design.md`](docs/superpowers/specs/2026-08-08-almacen-lite-design.md)
- **Plan de implementación:** [`docs/superpowers/plans/2026-08-08-almacen-lite.md`](docs/superpowers/plans/2026-08-08-almacen-lite.md)
- **Guía funcional por módulo:** [`docs/funcional/README.md`](docs/funcional/README.md)
- **Despliegue en producción:** [`docs/despliegue.md`](docs/despliegue.md)
- **Referencia de endpoints:** `http://localhost:8000/docs` (Scribe)

## Roles

| | admin | vendedor |
|---|---|---|
| Usuarios, almacenes, unidades, productos, transferencias | ✔ | ✘ |
| Vender | ✔ | Solo en su almacén |
| Métricas | Los tres periodos, global y por almacén | Solo `weekly` y solo su almacén |
| Ganancia, top productos, comparativa, inventario | ✔ | ✘ |
| Auditoría | ✔ | ✘ |
