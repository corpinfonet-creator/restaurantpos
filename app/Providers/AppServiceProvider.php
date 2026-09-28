<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Setting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Una sola instancia por petición. El middleware de licencia y los
        // controladores la reciben por inyección; sin esto Laravel construía
        // una instancia nueva cada vez y cada una repetía las mismas consultas
        // a `settings` (token y UUID de instalación).
        $this->app->singleton(\App\Services\LicenciaService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // El símbolo de moneda está disponible como $currency en cualquier vista.
        //
        // Se resuelve de forma diferida (View::share con un closure no sirve, así
        // que se usa un composer sobre '*'): antes esto corría en cada petición,
        // incluida /kitchen/poll y las respuestas JSON, que no renderizan ninguna
        // vista. Peor aún, llamaba a Schema::hasTable('settings'), que interroga
        // el esquema de la base de datos —una consulta nada barata— cada vez.
        //
        // Ahora solo se toca la base de datos cuando de verdad se va a pintar una
        // vista, y el valor llega de la caché de Setting.
        View::composer('*', function ($view) {
            static $currency = null;

            if ($currency === null) {
                try {
                    $currency = Setting::get('currency_symbol', '$');
                } catch (\Throwable $e) {
                    // Base de datos no disponible o migraciones sin correr todavía.
                    $currency = '$';
                }
            }

            $view->with('currency', $currency);
        });
    }
}
