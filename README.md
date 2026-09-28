# Sistema Restaurante (POS)

Punto de venta para restaurantes: toma de pedidos por mesa, monitor de cocina,
caja, inventario, reservas y reportes. Construido con Laravel 12.

## Módulos

- **POS** — mapa de mesas por zonas, pedido por mesa, división de cuenta,
  traslado de mesa, descuentos y propina, cobro con cálculo de vuelto.
- **Cocina (KDS)** — tablero en tiempo real por polling; cada plato avanza
  pendiente → cocinando → servido, con opción de deshacer.
- **Caja y ventas** — ticket, reporte diario, registro de gastos.
- **Inventario** — stock por producto, kardex de movimientos, receta por plato
  (descuenta insumos al vender).
- **Clientes** — consulta de DNI/RUC contra api.json.pe.
- **Reservas** — agenda con asignación de mesa.
- **Reportes** — ventas por día, hora, categoría, producto y mozo.
- **Usuarios** — tres roles: `admin`, `cashier`, `waiter`.

## Requisitos

- PHP 8.2 o superior
- MySQL 5.7+ / MariaDB
- Composer y Node.js 18+
- Extensiones PHP: `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`,
  `ctype`, `json`, `bcmath`, `fileinfo`

## Instalación

```bash
git clone https://github.com/corpinfonet-creator/restaurantpos.git
cd restaurantpos

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configura la base de datos en `.env` y luego:

```bash
php artisan migrate

# Obligatorio: crea public/storage -> storage/app/public.
# Sin este enlace las imágenes de productos, categorías y mesas no se ven.
php artisan storage:link
```

Para desarrollo hacen falta dos procesos a la vez:

```bash
php artisan serve    # aplicación en http://127.0.0.1:8000
npm run dev          # assets (Vite) en http://localhost:5173
```

## Despliegue

En producción los assets se compilan; no corre Node en el servidor:

```bash
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Usa `.env.production.example` como plantilla del `.env` del servidor: trae
`APP_DEBUG=false`, caché en ficheros, logs rotativos y cookies seguras.

### Notas para hosting compartido (cPanel)

El document root es `public_html`, no la carpeta `public/` de Laravel. Deja el
proyecto **fuera** de `public_html` y copia dentro solo el contenido de
`public/`, reapuntando en `index.php` las rutas a `vendor/autoload.php`,
`bootstrap/app.php` y `storage/framework/maintenance.php`.

El enlace de storage hay que crearlo a mano, porque `php artisan storage:link`
lo crearía en `public/`, que allí no se sirve:

```bash
ln -s /home/usuario/restaurantpos/storage/app/public \
      /home/usuario/public_html/storage
```

Y dale permisos de escritura a Laravel:

```bash
chmod -R 775 storage bootstrap/cache
```

Después de `config:cache`, editar el `.env` no surte efecto hasta que corras
`php artisan config:clear`.

## Seguridad

- El `.env` nunca se versiona ni se deja accesible por web.
- Los volcados de base de datos (`*.sql`) están excluidos del repositorio:
  contienen datos de clientes y hashes de contraseñas.
- `APP_DEBUG=false` en producción — con `true`, cualquier error muestra la
  configuración y las credenciales al visitante.
- Las rutas están segmentadas en tres zonas por rol: operativa (mozo, cajero,
  admin), financiera (cajero, admin) y administrativa (solo admin).

## Licencia del sistema

La operación está protegida por un token firmado con RSA que se verifica
localmente, sin llamadas de red por petición. La revalidación contra el
servidor es un trabajo diario programado, con periodo de gracia: un fallo de
conexión no deja al restaurante sin poder cobrar.

## Despliegue en Railway

El repositorio trae lo necesario: `Dockerfile`, `railway.json`,
`railway-start.sh` y `railway-scheduler.sh`. Railway detecta el Dockerfile y
construye desde ahí.

### 1. Crear el proyecto

En Railway: **New Project → Deploy from GitHub repo** y elige este
repositorio. El primer build fallará hasta que existan las variables y la
base de datos; es lo esperado.

### 2. Añadir MySQL

**New → Database → MySQL**, en el mismo proyecto. Railway expone sus
credenciales como variables que se referencian desde el servicio de la app.

### 3. Variables de entorno

Copia el contenido de `.env.railway.example` en la pestaña **Variables** del
servicio. Dos que hay que rellenar a mano:

- `APP_KEY` — genérala en local con `php artisan key:generate --show` y pega
  el valor completo, incluido el prefijo `base64:`.
- `APP_URL` — el dominio que Railway asigna, con `https://` y sin barra final.

### 4. Volumen para las imágenes

**Importante.** El disco del contenedor se borra en cada despliegue. Sin un
volumen, las fotos de productos, categorías y mesas desaparecen con cada
`git push`.

En el servicio: **Settings → Volumes → New Volume**, con punto de montaje:

```
/app/storage/app/public
```

El script de arranque recrea el enlace `public/storage` en cada despliegue,
porque `public/` no forma parte del volumen.

### 5. Programador de tareas

La licencia se revalida a diario (03:30) mediante el planificador de Laravel.
Railway no tiene cron, así que se despliega un **segundo servicio desde el
mismo repositorio** con el comando de arranque:

```
railway-scheduler.sh
```

Necesita las mismas variables de entorno que la app. Sin este servicio el
token firmado nunca se refresca y el sistema acaba bloqueándose cuando se
agota el periodo de gracia.

### 6. Primer usuario

La base arranca vacía. Con `railway run` desde el proyecto enlazado, o desde
la consola del servicio:

```bash
php artisan admin:crear --email=admin@tudominio.com
```

Pide la contraseña de forma oculta, para que no quede en el historial del
terminal. Si se deja vacía genera una segura y la muestra una sola vez.

También admite pasarlo todo de una vez, útil en scripts:

```bash
php artisan admin:crear --email=admin@tudominio.com --password=ClaveSegura --name="Administrador"
```

### Notas

- El healthcheck apunta a `/up`, que responde sin autenticación ni licencia.
- Los logs van a `stderr` y se leen en la consola de Railway; no se escriben
  en archivos, que se perderían en cada despliegue.
- `bootstrap/app.php` confía en las cabeceras del proxy (`trustProxies`). Sin
  eso, con `SESSION_SECURE_COOKIE=true` nadie podría iniciar sesión: Laravel
  no detectaría que la conexión es HTTPS.
- Se usa FrankenPHP y no `php artisan serve`, que es monoproceso y atiende una
  petición a la vez. El monitor de cocina consulta cada 6 segundos por
  pantalla, así que hace falta concurrencia real.
