<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Servidor de licencias
    |--------------------------------------------------------------------------
    |
    | URL base del panel que emite y valida las licencias (CENEPAPOS), sin
    | barra final. En desarrollo suele ser http://localhost:3000.
    |
    */
    'servidor' => env('LICENCIA_SERVIDOR', 'http://localhost:3000'),

    /*
    |--------------------------------------------------------------------------
    | Clave pública de verificación
    |--------------------------------------------------------------------------
    |
    | Clave pública RSA en PEM que emite el servidor. Sólo sirve para VERIFICAR
    | firmas: no permite crear licencias, así que puede viajar con el código.
    | La genera `npx tsx scripts/generar-claves-licencia.ts` en el servidor.
    |
    | En el .env se guarda en una sola línea con los saltos escapados como \n.
    |
    */
    'public_key' => str_replace('\n', "\n", (string) env('LICENCIA_PUBLIC_KEY', '')),

    /*
    |--------------------------------------------------------------------------
    | Tiempo de espera de red (segundos)
    |--------------------------------------------------------------------------
    |
    | Deliberadamente corto: si el servidor de licencias no responde, el
    | sistema sigue funcionando con su periodo de gracia. Nunca debe quedarse
    | esperando mientras hay clientes en el local.
    |
    */
    'timeout' => (int) env('LICENCIA_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Rutas siempre accesibles
    |--------------------------------------------------------------------------
    |
    | Aunque la licencia esté vencida, estas rutas siguen abiertas para poder
    | activar, cerrar sesión y ver el aviso.
    |
    */
    'rutas_libres' => [
        'licencia',
        'licencia/*',
        'login',
        'logout',
    ],

];
