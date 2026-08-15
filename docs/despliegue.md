# Despliegue en producción

Guía para poner `almacen-lite` en un servidor Linux con Nginx, PHP-FPM y MySQL.
Para desarrollo local, ver el [README](../README.md).

## Lo que este proyecto necesita (y lo que no)

- **Sin worker de colas ni cron.** No hay `app/Jobs` ni tareas en `Schedule::`,
  así que no hace falta `queue:work` ni una entrada en crontab.
- **Sin Redis.** `SESSION_DRIVER`, `CACHE_STORE` y `QUEUE_CONNECTION` usan el
  driver `database`; las tablas las crean las migraciones.
- **Sin frontend real.** La única vista con `@vite` es la landing
  (`welcome.blade.php`); la API no depende de assets compilados.

## 1. Dependencias del sistema

```bash
apt update
apt install -y php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-bcmath php8.3-curl php8.3-zip php8.3-intl \
  mysql-server nginx unzip git
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

## 2. Base de datos

```sql
CREATE DATABASE almacen_lite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'almacen'@'localhost' IDENTIFIED BY 'UNA_PASSWORD_FUERTE';
GRANT ALL PRIVILEGES ON almacen_lite.* TO 'almacen'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Clonar e instalar

```bash
cd /var/www
git clone https://github.com/carlosrm96/almacen-lite.git
cd almacen-lite
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

## 4. Configurar el `.env`

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
APP_TIMEZONE=America/Havana

ALMACEN_MONEDA_BASE=CUP
ALMACEN_TASA_USD=420

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=almacen_lite
DB_USERNAME=almacen
DB_PASSWORD=UNA_PASSWORD_FUERTE

LOG_LEVEL=warning
```

Cinco valores que no son intercambiables:

| Clave | Motivo |
|---|---|
| `DB_PORT=3306` | El `3310` del `.env.example` es el MySQL de desarrollo local |
| `APP_DEBUG=false` | Con `true`, cada error expone trazas y credenciales |
| `APP_TIMEZONE=America/Havana` | Determina los cortes de día/semana/mes de las métricas |
| `ALMACEN_MONEDA_BASE=CUP` | Moneda de `sales.total` y de todas las cifras de métricas. Cambiarla **después** de tener ventas reinterpretaría importes ya guardados |
| `ALMACEN_TASA_USD=420` | Tasa con la que se siembra USD. El valor por defecto es un marcador de posición: ajústalo antes de sembrar |

## 5. Migrar y sembrar roles

```bash
php artisan migrate --force --seed
```

`DatabaseSeeder` crea **roles y permisos** (`admin`, `vendedor`) y las
**monedas** (CUP como base, USD), y es obligatorio: sin él no funcionan ni la
autorización ni los precios.

Las tasas se administran luego en la tabla `currencies` — el seeder no pisa una
moneda ya existente, así que volver a ejecutarlo no deshace un ajuste manual.

No ejecutes `DemoSeeder` en producción — son datos de prueba con contraseñas
conocidas.

## 6. Crear el primer administrador

Ningún seeder crea usuarios, así que este paso es imprescindible para poder
entrar. Hazlo desde la propia API, con `POST /v1/register`:

```bash
curl -X POST https://tu-dominio.com/v1/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Administrador","email":"admin@tu-dominio.com","password":"una-password-segura","password_confirmation":"una-password-segura"}'
```

Devuelve `201` con el token ya listo. **Es una ruta de un solo uso**: en
cuanto existe un usuario responde `403`, así que hazlo nada más desplegar y
antes de exponer la URL. A partir de ahí los usuarios se crean con
`POST /v1/users` desde la cuenta de admin.

Si prefieres no exponer ese paso por HTTP, el equivalente por consola:

```bash
php artisan tinker
```

```php
$u = App\Models\User::create([
    'name' => 'Administrador',
    'email' => 'admin@tu-dominio.com',
    'password' => 'una-password-segura',   // el cast 'hashed' la hashea sola
]);
$u->assignRole('admin');
```

## 7. Permisos de ficheros

```bash
chown -R www-data:www-data /var/www/almacen-lite
chmod -R 775 storage bootstrap/cache
```

## 8. Compilar assets (opcional)

Solo afecta a la landing `/`. Sin build, esa ruta lanza un error de manifiesto
de Vite; la API funciona igual.

```bash
apt install -y nodejs npm
npm install && npm run build
```

## 9. Cachés de producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 10. Nginx

`/etc/nginx/sites-available/almacen-lite`:

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /var/www/almacen-lite/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
ln -s /etc/nginx/sites-available/almacen-lite /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

## 11. HTTPS

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d tu-dominio.com
```

Los tokens de Sanctum viajan en la cabecera `Authorization`. Servir por HTTP
plano los expondría en claro en cada petición.

## Comprobación

```bash
curl -s -X POST https://tu-dominio.com/v1/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@tu-dominio.com","password":"una-password-segura"}'
```

Debe devolver un `token`. Úsalo como `Authorization: Bearer <token>` contra
`/v1/products` para confirmar que la autorización responde.

## Actualizaciones

```bash
cd /var/www/almacen-lite
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Regenera siempre las cachés: si cambias el `.env` sin volver a ejecutar
`config:cache`, Laravel seguirá leyendo la configuración anterior.

## Documentación de la API en el servidor

Scribe está en `require-dev`, así que con `composer install --no-dev` la ruta
`/docs` no existe en producción. Si la necesitas publicada, genera la
documentación en local con `php artisan scribe:generate` y versiona el
resultado, en lugar de instalar dependencias de desarrollo en el servidor.

Por ese mismo motivo, `config/scribe.php` empieza con una guarda
`class_exists(AuthIn::class)` que devuelve `[]` cuando Scribe no está
instalado. Laravel evalúa todos los ficheros de `config/` al arrancar, y sin
esa guarda el fichero desreferencia clases inexistentes: la instalación de
producción aborta en `package:discover` con
`Class "Knuckles\Scribe\Config\AuthIn" not found`, y ningún comando de artisan
ni petición HTTP llega a ejecutarse. No la quites al actualizar ese fichero.
