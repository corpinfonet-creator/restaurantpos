<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LicenciaService;

/**
 * Pantallas de licencia: activación, aviso de bloqueo y estado.
 *
 * Deben ser accesibles aunque la licencia esté vencida (ver
 * config/licencia.php → rutas_libres), porque si no el cliente no podría
 * renovar desde el propio sistema.
 */
class LicenciaController extends Controller
{
    public function __construct(private LicenciaService $licencia)
    {
    }

    /** Pantalla de bloqueo / activación. */
    public function aviso()
    {
        $estado = $this->licencia->estado();

        // Si la licencia está bien, no tiene sentido quedarse aquí.
        if ($estado['activa'] && $estado['motivo'] === 'ok') {
            return redirect()->route('dashboard');
        }

        return view('licencia.aviso', [
            'estado'      => $estado,
            'instalacion' => $this->licencia->instalacionId(),
        ]);
    }

    /** Procesa el código introducido por el usuario. */
    public function activar(Request $request)
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'min:4', 'max:128'],
        ]);

        [$ok, $mensaje] = $this->licencia->activar(trim($datos['codigo']));

        if (!$ok) {
            return back()->withInput()->with('licencia_error', $mensaje);
        }

        return redirect()->route('dashboard')->with('licencia_ok', $mensaje);
    }

    /** Fuerza una revalidación manual, útil cuando el cliente acaba de pagar. */
    public function revalidar()
    {
        [$ok, $mensaje] = $this->licencia->revalidar();

        return back()->with($ok ? 'licencia_ok' : 'licencia_error', $mensaje);
    }
}
