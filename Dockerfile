# Imagen de producción para Railway.
#
# Se usa FrankenPHP (Caddy + PHP embebido) en vez de `php artisan serve`:
# el servidor de desarrollo de PHP es monoproceso y atiende una petición a la
# vez. Este sistema tiene el monitor de cocina consultando cada 6 segundos por
# pantalla, más las terminales del POS, así que necesita concurrencia real.

# -----------------------------------------------------------------------------
# Etapa 1: compilación de assets
#
# En su propia etapa y con la imagen oficial de Node: Vite 7 necesita Node 20+,
# y el paquete `nodejs` de Debian suele traer una versión más antigua. Además,
# nada de Node llega a la imagen final: solo se copia public/build.
# -----------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /build

# Las dependencias primero, para que Docker reutilice esta capa mientras no
# cambien package.json ni el lock.
COPY package.json package-lock.json ./
RUN npm ci

# Vite necesita su configuración y las fuentes para compilar.
COPY vite.config.js ./
COPY resources/ ./resources/

RUN npm run build


# -----------------------------------------------------------------------------
# Etapa 2: imagen de ejecución
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.2

# Extensiones que Laravel y este proyecto necesitan.
# gd: el mapa de mesas convierte las imágenes subidas a WebP.
# intl: formato de fechas y números por localización.
RUN install-php-extensions \
    pdo_mysql \
    gd \
    intl \
    zip \
    opcache \
    bcmath

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Las dependencias PHP antes que el código, por la caché de capas: si no
# cambian composer.json/lock, este paso se reutiliza entre builds.
# --no-scripts: los scripts de Laravel necesitan el código completo, que
# todavía no está copiado; se ejecutan más abajo.
COPY composer.json composer.lock ./
RUN composer install \
      --no-dev \
      --optimize-autoloader \
      --no-interaction \
      --prefer-dist \
      --no-scripts

# El código de la aplicación. .dockerignore deja fuera vendor/, node_modules/,
# .env y los volcados de base de datos.
COPY . .

# Los assets ya compilados en la etapa anterior.
COPY --from=assets /build/public/build ./public/build

# package:discover construye el manifiesto de paquetes que Laravel necesita
# para registrar los proveedores de servicios; sin él la aplicación arranca
# sin sus dependencias.
#
# Se ejecuta durante el build, donde todavía no hay .env ni base de datos, así
# que se le pasa una APP_KEY temporal: el comando arranca el framework y sin
# clave lanzaría una excepción. La clave real llega en tiempo de ejecución
# desde las variables de Railway.
RUN APP_KEY=base64:$(head -c 32 /dev/urandom | base64) \
    composer dump-autoload --optimize --no-dev --no-interaction

# OPcache: compila el PHP una vez y lo mantiene en memoria. En producción es
# la diferencia entre reinterpretar cada archivo en cada petición o no hacerlo.
# validate_timestamps=0 es seguro aquí porque el código nunca cambia dentro de
# un contenedor ya construido.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.max_accelerated_files=10000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# El Caddyfile del proyecto sustituye al de la imagen: define la raíz en
# /app/public y escucha en el puerto que Railway inyecta por $PORT.
COPY Caddyfile /etc/caddy/Caddyfile

# Laravel necesita escribir en storage/ y bootstrap/cache.
RUN chmod -R 775 storage bootstrap/cache \
 && chmod +x railway-start.sh railway-scheduler.sh

CMD ["./railway-start.sh"]
