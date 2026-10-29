<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CrearUsuario extends Command
{
    protected $signature = 'despacho:crear-usuario {email} {--nombre= : Nombre completo} {--rol=admin : admin o worker} {--password= : Si se omite, se pide por teclado}';

    protected $description = 'Crea un usuario del personal (admin o worker) sin usar los datos de ejemplo';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $rol = (string) $this->option('rol');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('El correo no es válido.');

            return self::FAILURE;
        }
        if (! in_array($rol, ['admin', 'worker'], true)) {
            $this->error('El rol debe ser admin o worker.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('Ya existe un usuario con ese correo.');

            return self::FAILURE;
        }
        $password = (string) ($this->option('password') ?: $this->secret('Contraseña (mínimo 12 caracteres)'));
        if (mb_strlen($password) < 12) {
            $this->error('La contraseña debe tener al menos 12 caracteres.');

            return self::FAILURE;
        }
        $u = User::create(['name' => $this->option('nombre') ?: $email, 'email' => $email, 'password' => Hash::make($password)]);
        $u->assignRole($rol);
        $this->info("Usuario {$email} creado como {$rol}.");

        return self::SUCCESS;
    }
}
