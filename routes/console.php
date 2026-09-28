<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Licencia
|--------------------------------------------------------------------------
|
| Revalida a diario de madrugada, cuando el local está cerrado. Mantiene
| fresco el token firmado para que el sistema aguante una caída de red
| durante los días de gracia.
|
| Requiere que el programador de Laravel esté corriendo. En Windows se
| configura una tarea programada que ejecute cada minuto:
|     php artisan schedule:run
|
*/
// Revalidación diaria desactivada junto con el control de licencias: sin
// comprobación no hay nada que revalidar, y el comando intentaría contactar
// cada madrugada un servidor de licencias que no está configurado.
//
// Se restaura al rediseñar el licenciamiento para multiempresa:
//
// Schedule::command('licencia:revalidar')
//     ->dailyAt('03:30')
//     ->withoutOverlapping();
