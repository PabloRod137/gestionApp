<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use \Database\Seeders\Concerns\SoloDatosDeEjemplo;

    /**
     * Siembra de la base de datos principal de la aplicación.
     */
    public function run(): void
    {
        $this->exigirDatosDeEjemplo();

        // Llamar a los seeders en el orden de dependencia correcto
        $this->call([
            RolesSeeder::class, // 1. Crea los roles (admin, worker, client)
            UserSeeder::class,  // 2. Crea los usuarios y perfiles fiscales de cliente
            ServiceSeeder::class, // 3. Inserta el catálogo de servicios de Nortia
            BlogCategorySeeder::class, // 4. Genera las categorías del blog
            DemoDataSeeder::class, // 5. Inserta datos de expedientes, facturas, citas, posts, etc.
        ]);
    }
}
