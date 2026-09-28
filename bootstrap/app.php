<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // 0. Proxy inverso (Railway, y en general cualquier PaaS)
        //
        // La aplicación no recibe el tráfico directamente: delante hay un
        // balanceador que termina el TLS y reenvía por HTTP. Sin confiar en
        // sus cabeceras X-Forwarded-*, Laravel cree que la conexión es
        // insegura y genera URLs con http://, además de impedir el login
        // cuando SESSION_SECURE_COOKIE está activo (la cookie se marca como
        // 'secure' pero Laravel no ve HTTPS, así que nunca se envía).
        //
        // '*' es lo correcto aquí: la IP del proxy de Railway no es fija ni
        // conocida de antemano, y el contenedor solo es accesible a través
        // de él.
        $middleware->trustProxies(at: '*');

        // 1. Middleware Globales o Web (Aquí va la Zona Horaria)
        $middleware->web(append: [
            \App\Http\Middleware\SetTimezone::class,
        ]);

        // 2. Alias para usar en las rutas (SOLUCIÓN AL ERROR)
        // Esto le dice a Laravel: "Cuando veas 'role', usa este archivo CheckRole"
        $middleware->alias([
            'role'     => \App\Http\Middleware\CheckRole::class,
            'licencia' => \App\Http\Middleware\CheckLicencia::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();