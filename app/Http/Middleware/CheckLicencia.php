<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\LicenciaService;

/**
 * Comprueba que la licencia permite operar.
 *
 * No hace peticiones de red: sólo verifica la firma del token guardado. La
 * revalidación contra el servidor la hace el comando `licencia:revalidar`,
 * programado a diario. Así una caída de internet no ralentiza ni bloquea el
 * servicio en pleno turno.
 *
 * Se aplica con el alias 'licencia', igual que 'role' (ver bootstrap/app.php).
 */
class CheckLicencia
{
    public function __construct(private LicenciaService $licencia)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // El control de licencias está desactivado por defecto (ver
        // config/licencia.php): se rediseñará junto con el soporte
        // multiempresa, donde lo que se licencia es cada empresa y no la
        // instalación. Con LICENCIA_ACTIVA=true vuelve a aplicarse todo lo
        // que hay debajo, que se conserva intacto.
        if (!config('licencia.activa', false)) {
            return $next($request);
        }

        // Las rutas de activación y salida deben seguir accesibles: si no, un
        // sistema con licencia vencida no podría ni renovarla.
        if ($request->is(config('licencia.rutas_libres', []))) {
            return $next($request);
        }

        $estado = $this->licencia->estado();

        if ($estado['activa']) {
            // El preaviso viaja en la sesión para que el layout lo muestre sin
            // que cada controlador tenga que ocuparse.
            if (!empty($estado['avisar'])) {
                session()->flash('licencia_aviso', $estado['mensaje'] ?: $this->textoPreaviso($estado));
            }

            return $next($request);
        }

        // Peticiones AJAX: se responde en JSON, no con una redirección que el
        // JavaScript no sabría interpretar.
        if ($request->expectsJson()) {
            return response()->json([
                'error'   => 'licencia_inactiva',
                'mensaje' => $estado['mensaje'],
            ], 402); // 402 Payment Required
        }

        return redirect()->route('licencia.aviso');
    }

    private function textoPreaviso(array $estado): string
    {
        $dias = $estado['dias'];

        if ($dias === null) {
            return 'Su licencia requiere atención.';
        }

        if ($dias <= 0) {
            return 'Su licencia vence hoy. Renuévela para no interrumpir el servicio.';
        }

        return "Su licencia vence en {$dias} día(s). Renuévela para no interrumpir el servicio.";
    }
}
