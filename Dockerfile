# Imagen de producción para Railway.
#
# Se usa FrankenPHP (Caddy + PHP embebido) en vez de `php artisan serve`:
# el servidor de desarrollo de PHP es monoproceso y atiende una petición a la
# vez. Este sistema tiene el monitor de cocina consultando cada 6 segundos por
# pantalla, más las terminales del POS, así que necesita concurrencia real.
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

# Composer, desde su imagen oficial.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Las dependencias se instalan antes de copiar el código para aprovechar la
# caché de capas de Docker: si no cambian composer.json/lock, este paso se
# reutiliza y el build es mucho más rápido.
COPY composer.json composer.lock ./
RUN composer install \
      --no-dev \
      --optimize-autoloader \
      --no-interaction \
      --prefer-dist \
      --no-scripts

# Assets: se compilan aquí y se copian ya construidos. En el contenedor final
# no queda Node ni node_modules.
COPY package.json package-lock.json ./
RUN apt-get update \
 && apt-get install -y --no-install-recommends nodejs npm \
 && npm ci \
 && rm -rf /var/lib/apt/lists/*

COPY . .
RUN npm run build \
 && rm -rf node_modules \
 && apt-get purge -y nodejs npm \
 && apt-get autoremove -y

# Se completa la instalación de Composer. package:discover construye el
# manifiesto de paquetes que Laravel necesita para registrar los proveedores
# de servicios; sin él la aplicación arranca sin sus dependencias.
#
# Se ejecuta en el build, donde todavía no hay .env ni base de datos, así que
# se le pasa APP_KEY de forma temporal: package:discover arranca el framework
# y sin clave lanzaría una excepción. La clave real llega en tiempo de
# ejecución desde las variables de Railway.
RUN APP_KEY=base64:$(head -c 32 /dev/urandom | base64) \
    composer dump-autoload --optimize --no-dev --no-interaction

# OPcache: compila el PHP una vez y lo guarda en memoria. En producción es la
# diferencia entre reinterpretar cada archivo en cada petición o no hacerlo.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.max_accelerated_files=10000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Laravel necesita escribir en storage/ y bootstrap/cache.
RUN chmod -R 775 storage bootstrap/cache

COPY railway-start.sh /usr/local/bin/railway-start.sh
RUN chmod +x /usr/local/bin/railway-start.sh

CMD ["railway-start.sh"]
