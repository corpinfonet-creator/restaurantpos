<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    // Permitimos que se puedan guardar estas columnas masivamente
    protected $fillable = ['key', 'value'];

    /** Clave única de caché para el mapa completo de ajustes. */
    public const CACHE_KEY = 'settings.all';

    /**
     * Devuelve un ajuste, leyendo la tabla una sola vez por petición.
     *
     * Antes cada consulta era su propio SELECT: el layout pedía nombre y logo,
     * el <title> repetía el nombre y el middleware de zona horaria añadía otro,
     * cinco viajes a la base de datos en cada carga para datos que cambian
     * como mucho una vez al mes. Aquí se cachea el mapa entero y se sirve de
     * memoria; update() lo invalida al guardar.
     */
    public static function get(string $key, $default = null)
    {
        return static::all_cached()[$key] ?? $default;
    }

    /** Mapa key => value de todos los ajustes, cacheado. */
    public static function all_cached(): array
    {
        return Cache::rememberForever(
            static::CACHE_KEY,
            fn () => static::pluck('value', 'key')->all()
        );
    }

    /** Se llama al guardar ajustes para que la próxima lectura vea lo nuevo. */
    public static function flushCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /**
     * Red de seguridad: cualquier escritura por Eloquent limpia la caché,
     * incluso si algún día se guarda un ajuste desde otro sitio.
     */
    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }
}
