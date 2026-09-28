#!/bin/sh
# Programador de tareas de Laravel para Railway.
#
# Railway no ofrece cron del sistema, así que el planificador se ejecuta como
# un bucle en su propio servicio: se despliega el MISMO repositorio con este
# script como comando de arranque.
#
# Lo que hay en juego: routes/console.php programa `licencia:revalidar` a las
# 03:30. Sin este proceso corriendo, el token firmado nunca se refresca y el
# sistema acabaría bloqueándose al agotarse el periodo de gracia.
set -e

echo "==> Programador de tareas iniciado"

while true; do
    # schedule:run comprueba qué toca ejecutar en este minuto. Debe llamarse
    # cada minuto; es Laravel quien decide si hay algo que hacer.
    php artisan schedule:run --no-interaction >> /dev/stdout 2>&1 || \
        echo "  (schedule:run devolvió error; se reintenta en el siguiente minuto)"
    sleep 60
done
