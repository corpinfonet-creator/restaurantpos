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
    | Comprobación de licencia activada
    |--------------------------------------------------------------------------
    |
    | Desactivada por defecto. El control de licencias se rediseñará junto con
    | el soporte multiempresa, donde la unidad a licenciar deja de ser la
    | instalación y pasa a ser cada empresa: un único sistema atenderá a
    | varios clientes, cada uno con su propia vigencia.
    |
    | Hasta entonces el sistema opera sin restricción. El resto del mecanismo
    | (verificación de firma RSA, periodo de gracia, revalidación diaria) se
    | conserva intacto y vuelve a funcionar poniendo LICENCIA_ACTIVA=true.
    |
    */
    'activa' => filter_var(env('LICENCIA_ACTIVA', false), FILTER_VALIDATE_BOOLEAN),

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
