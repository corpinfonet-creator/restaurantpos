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

# Espera a que MySQL acepte conexiones. En el primer despliegue la base de
# datos puede tardar unos segundos más que la aplicación en estar lista, y sin
# esta espera `migrate` falla y (por set -e) tumba el contenedor entero: se ve
# como un fallo de despliegue cuando en realidad solo faltaba esperar.
echo "==> Esperando a la base de datos"
i=1
while [ $i -le 30 ]; do
    # Se prueba la conexión con la configuración real de Laravel, así que
    # funciona igual con DB_URL que con las cinco variables DB_* por separado.
    if php artisan db:monitor >/dev/null 2>&1; then
        echo "    conectado"
        break
    fi
    if [ $i -eq 30 ]; then
        echo "    ERROR: sin conexión a la base de datos tras 60s."
        echo ""
        echo "    Valores recibidos:"
        echo "      DB_HOST     = ${DB_HOST:-(vacío)}"
        echo "      DB_PORT     = ${DB_PORT:-(vacío)}"
        echo "      DB_DATABASE = ${DB_DATABASE:-(vacío)}"
        echo "      DB_USERNAME = ${DB_USERNAME:-(vacío)}"
        echo "      DB_PASSWORD = $([ -n "${DB_PASSWORD}" ] && echo "(definida)" || echo "(vacía)")"
        echo ""
        echo "      DB_URL      = $([ -n "${DB_URL}" ] && echo "(definida)" || echo "(vacía)")"
        echo ""
        echo "    Error que devuelve MySQL:"
        php artisan db:monitor 2>&1 | tail -5 | sed "s/^/      /"
        echo ""
        echo "    Si DB_HOST muestra algo como \${{...}} sin resolver, el"
        echo "    nombre del servicio en la referencia no coincide con el"
        echo "    nombre real de la base de datos en el canvas de Railway."
        echo ""
        echo "    Servicios visibles en la red privada del proyecto:"
        env | grep -iE "^(MYSQL|DATABASE|PG)" | cut -d= -f1 | sed "s/^/      /" || \
            echo "      (ninguna variable de base de datos en el entorno)"
        exit 1
    fi
    sleep 2
    i=$((i + 1))
done

# --force: en producción migrate pide confirmación interactiva y aquí no hay
# quien la dé. Solo aplica lo pendiente; si no hay nada, no hace nada.
echo "==> Migraciones"
php artisan migrate --force

# Las cachés se regeneran en cada arranque para que recojan las variables de
# entorno actuales de Railway (que cambian al editar las del servicio).
# Deja constancia del estado de la licencia en los logs: evita tener que
# adivinar si una pantalla de "Active su licencia" viene del código desplegado
# o de una variable de entorno que lo anula.
LIC=$(php artisan tinker --execute="echo config('licencia.activa') ? 'si' : 'no';" 2>/dev/null | tr -d "[:space:]")
if [ "$LIC" = "si" ]; then
    echo "==> Control de licencias: ACTIVO (LICENCIA_ACTIVA=${LICENCIA_ACTIVA:-no definida})"
else
    echo "==> Control de licencias: desactivado"
fi

echo "==> Cacheando configuración"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Railway asigna el puerto por la variable PORT; no es fijo.
echo "==> Servidor escuchando en el puerto ${PORT:-8080}"

# Se arranca con el Caddyfile del proyecto, que ya define la raíz en
# /app/public y el puerto a partir de $PORT. Antes se usaba `frankenphp
# php-server`, que ignoraba la configuración de la imagen y dejaba al proxy de
# Railway sin nada a lo que conectarse (502 "Application failed to respond").
exec frankenphp run --config /etc/caddy/Caddyfile
