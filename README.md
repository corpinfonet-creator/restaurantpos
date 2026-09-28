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
