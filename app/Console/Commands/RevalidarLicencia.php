<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LicenciaService;

/**
 * Revalida la licencia contra el servidor.
 *
 * Se programa a diario (ver routes/console.php). Mantiene el token local
 * fresco, de modo que el sistema pueda seguir operando aunque después se
 * quede sin conexión durante los días de gracia.
 *
 * Devuelve 0 aunque falle la red: un fallo de conexión no es un error
 * operativo, y no debe llenar de alertas el programador de tareas.
 */
class RevalidarLicencia extends Command
{
    protected $signature = 'licencia:revalidar';

    protected $description = 'Revalida la licencia contra el servidor y renueva el token local';

    public function handle(LicenciaService $licencia): int
    {
        [$ok, $mensaje] = $licencia->revalidar();

        if ($ok) {
            $this->info($mensaje);
        } else {
            $this->warn($mensaje);
        }

        $estado = $licencia->estado();
        $this->line(sprintf(
            'Estado: %s%s',
            $estado['motivo'],
            $estado['dias'] !== null ? " ({$estado['dias']} día(s))" : ''
        ));

        return self::SUCCESS;
    }
}
