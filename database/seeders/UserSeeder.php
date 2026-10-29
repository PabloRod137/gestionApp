<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use \Database\Seeders\Concerns\SoloDatosDeEjemplo;

    /**
     * Corre el seeder.
     * Inserta los usuarios iniciales del sistema y les asigna roles.
     */
    public function run(): void
    {
        $this->exigirDatosDeEjemplo();

        // 1. Crear el usuario Administrador
        $adminUser = User::create([
            'name' => 'Administrador Nortia',
            'email' => 'admin@admin.es',
            'password' => Hash::make('Administrador1*'),
        ]);
        $adminUser->assignRole('admin');

        // 2. Crear dos Trabajadores/Asesores del despacho
        $worker1 = User::create([
            'name' => 'Francisco Pérez (Asesor Fiscal)',
            'email' => 'worker1@nortia.com',
            'password' => Hash::make('password'),
        ]);
        $worker1->assignRole('worker');

        $worker2 = User::create([
            'name' => 'María Santander (Abogada Civil/Compliance)',
            'email' => 'worker2@nortia.com',
            'password' => Hash::make('password'),
        ]);
        $worker2->assignRole('worker');

        // 3. Crear 5 Clientes de ejemplo con perfil fiscal asociado
        $clientesDemo = [
            [
                'name' => 'Construcciones Cantabria S.A.',
                'email' => 'cliente@cliente.es',
                'password' => 'Cliente1*',
                'nif' => 'A39123456',
                'razon_social' => 'Construcciones Cantabria S.A.',
                'tipo' => 'empresa',
                'telefono' => '942112233',
                'direccion' => 'Av. de la Reina Victoria 12, Santander',
            ],
            [
                'name' => 'Juan Gómez S.L. (Pescadería)',
                'email' => 'cliente2@nortia.com',
                'nif' => 'B39888999',
                'razon_social' => 'Pescaderías Gómez S.L.',
                'tipo' => 'empresa',
                'telefono' => '942445566',
                'direccion' => 'Calle San Francisco 5, Santander',
            ],
            [
                'name' => 'Carlos Martínez (Autónomo Carpintería)',
                'email' => 'cliente3@nortia.com',
                'nif' => '13765432X',
                'razon_social' => 'Carlos Martínez Estévez',
                'tipo' => 'autónomo',
                'telefono' => '600112233',
                'direccion' => 'Barrio La Sierra s/n, Torrelavega',
            ],
            [
                'name' => 'Laura Díaz (Particular)',
                'email' => 'cliente4@nortia.com',
                'nif' => '72111222Y',
                'razon_social' => 'Laura Díaz Muñoz',
                'tipo' => 'particular',
                'telefono' => '655998877',
                'direccion' => 'Calle Burgos 18, 3º Izq, Santander',
            ],
            [
                'name' => 'Comunidad Propietarios Paseo Pereda 10',
                'email' => 'cliente5@nortia.com',
                'nif' => 'H39001002',
                'razon_social' => 'Comunidad de Propietarios Paseo Pereda 10',
                'tipo' => 'empresa', // Comunidades tributan como entidades sin personalidad jurídica pero se gestionan similar
                'telefono' => '942550011',
                'direccion' => 'Paseo de Pereda 10, Santander',
            ]
        ];

        foreach ($clientesDemo as $c) {
            // Crear el usuario del cliente para el portal
            $user = User::create([
                'name' => $c['name'],
                'email' => $c['email'],
                'password' => Hash::make($c['password'] ?? 'password'),
            ]);
            $user->assignRole('client');

            // Crear los datos fiscales del cliente vinculados al usuario
            Client::create([
                'user_id' => $user->id,
                'nif' => $c['nif'],
                'razon_social' => $c['razon_social'],
                'tipo' => $c['tipo'],
                'telefono' => $c['telefono'],
                'direccion' => $c['direccion'],
                'notas' => 'Cliente creado automáticamente por el seeder de demostración.',
            ]);
        }
    }
}
