<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Corre el seeder.
     * Genera los roles necesarios para el control de accesos de la aplicación.
     */
    public function run(): void
    {
        // Limpiar caché de permisos de Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear roles si no existen
        Role::findOrCreate('admin');
        Role::findOrCreate('worker');
        Role::findOrCreate('client');
    }
}
