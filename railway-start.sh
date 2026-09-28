#!/bin/sh
# Arranque en Railway. Se ejecuta en cada despliegue y en cada reinicio del
# contenedor, así que todo lo de aquí debe poder repetirse sin romper nada.
set -e

echo "==> Preparando aplicación"

# Las carpetas de subidas viven en el volumen persistente montado en
# storage/app/public. La primera vez que se monta está vacío, así que se
# crean aquí; en arranques posteriores mkdir -p no hace nada.
mkdir -p storage/app/public/products \
         storage/app/public/categories \
         storage/app/public/settings \
         storage/app/public/tables \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         bootstrap/cache

# El contenedor se reconstruye entero en cada despliegue y el enlace
# public/storage se pierde, porque public/ no está en el volumen. Sin
# rehacerlo, las imágenes de productos, categorías y mesas dan 404.
# --force: sobrescribe un enlace anterior en vez de fallar.
php artisan storage:link --force

# --force: en producción migrate pide confirmación interactiva y aquí no hay
# quien la dé. Solo aplica lo pendiente; si no hay nada, no hace nada.
echo "==> Migraciones"
php artisan migrate --force

# Las cachés se regeneran en cada arranque para que recojan las variables de
# entorno actuales de Railway (que cambian al editar las del servicio).
echo "==> Cacheando configuración"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Railway asigna el puerto por la variable PORT; no es fijo.
echo "==> Servidor escuchando en el puerto ${PORT:-8080}"

# FrankenPHP sirve desde public/, la raíz correcta de Laravel: el resto del
# proyecto (.env incluido) queda fuera del alcance del servidor web.
exec frankenphp php-server \
    --root public/ \
    --listen "0.0.0.0:${PORT:-8080}"
