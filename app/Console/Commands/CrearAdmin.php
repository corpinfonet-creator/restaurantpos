<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Crea la cuenta de administrador de una instalación nueva.
 *
 * Existe para no tener que hacerlo con `tinker` desde la consola del
 * servidor, donde es fácil equivocarse y donde la contraseña queda escrita
 * en el historial del terminal. Aquí se pide de forma oculta, o se genera
 * una segura si no se indica ninguna.
 */
class CrearAdmin extends Command
{
    protected $signature = 'admin:crear
                            {--email= : Correo de la cuenta}
                            {--name=Administrador : Nombre que se mostrará}
                            {--password= : Contraseña; si se omite, se pide o se genera}';

    protected $description = 'Crea un usuario administrador';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Correo del administrador');

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'unique:users,email'],
        ], [
            'email.unique' => 'Ya existe un usuario con ese correo.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // La contraseña se pide oculta para que no quede en el historial del
        // terminal ni en los registros del servidor.
        $password = $this->option('password');
        $generada = false;

        if (!$password) {
            $password = $this->secret('Contraseña (dejar vacío para generar una segura)');

            if (!$password) {
                $password = Str::password(16);
                $generada = true;
            }
        }

        // Se exige una contraseña razonable: esta cuenta puede cobrar, aplicar
        // descuentos y reiniciar el sistema.
        $reglas = Validator::make(['password' => $password], [
            'password' => ['required', Password::min(8)],
        ]);

        if ($reglas->fails()) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $user = User::create([
            'name'     => $this->option('name'),
            'email'    => $email,
            'password' => Hash::make($password),
            'role'     => 'admin',
        ]);

        $this->newLine();
        $this->info('Administrador creado.');
        $this->line("  Nombre: {$user->name}");
        $this->line("  Correo: {$user->email}");

        // La contraseña solo se muestra cuando la ha generado el comando: si
        // la escribió la persona, ya la conoce y no hay razón para volver a
        // imprimirla donde pueda quedar registrada.
        if ($generada) {
            $this->newLine();
            $this->warn('  Contraseña generada: ' . $password);
            $this->line('  Anótela ahora: no vuelve a mostrarse.');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
