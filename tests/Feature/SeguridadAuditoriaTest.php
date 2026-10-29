<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Puntos de la auditoría: datos de ejemplo con contraseñas conocidas y cambio de sesión por enlace. */
class SeguridadAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private function enProduccion(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);
    }

    public function test_en_produccion_los_datos_de_ejemplo_no_se_siembran_sin_permiso_expreso(): void
    {
        $this->seed(RolesSeeder::class);
        $this->enProduccion();
        config(['app.allow_sample_data' => false]);

        foreach ([DatabaseSeeder::class, UserSeeder::class, DemoDataSeeder::class] as $seeder) {
            try {
                app($seeder)->run();
                $this->fail("{$seeder} no debe ejecutarse en producción");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('ALLOW_SAMPLE_DATA', $e->getMessage());
            }
        }
        $this->assertSame(0, User::count());
    }

    public function test_en_produccion_con_permiso_expreso_si_se_siembra(): void
    {
        $this->seed(RolesSeeder::class);
        $this->enProduccion();
        config(['app.allow_sample_data' => true]);

        app(UserSeeder::class)->run();
        $this->assertGreaterThan(0, User::count());
    }

    public function test_fuera_de_produccion_se_siembra_sin_trabas(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertGreaterThan(0, User::count());
    }

    private function conCuentaDeCliente(): Client
    {
        $this->seed(RolesSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('client');

        return Client::create(['codigo' => 'C-1', 'razon_social' => 'Cliente Uno', 'tipo' => 'empresa', 'user_id' => $u->id]);
    }

    public function test_suplantar_a_un_cliente_ya_no_funciona_con_un_simple_enlace(): void
    {
        $cliente = $this->conCuentaDeCliente();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get("/panel/clientes/{$cliente->id}/impersonate")->assertStatus(405);
    }

    public function test_el_personal_puede_suplantar_con_un_formulario_y_un_cliente_no(): void
    {
        $cliente = $this->conCuentaDeCliente();
        $worker = User::factory()->create();
        $worker->assignRole('worker');

        $this->actingAs($worker)->post("/panel/clientes/{$cliente->id}/impersonate")->assertRedirect(route('cliente.dashboard'));

        $otro = User::factory()->create();
        $otro->assignRole('client');
        auth()->logout();
        $this->actingAs($otro)->post("/panel/clientes/{$cliente->id}/impersonate")->assertForbidden();
    }

    public function test_los_botones_de_suplantar_son_formularios_con_proteccion_csrf(): void
    {
        $cliente = $this->conCuentaDeCliente();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get("/panel/clientes/{$cliente->id}")->assertOk()
            ->assertSee('method="POST" action="'.route('panel.clientes.impersonate', $cliente->id).'"', false);
    }
}
