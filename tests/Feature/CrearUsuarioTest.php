<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** despacho:crear-usuario: alta de personal sin pasar por los seeders de ejemplo. */
class CrearUsuarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_crea_un_administrador_con_la_contrasena_indicada(): void
    {
        $this->artisan('despacho:crear-usuario', ['email' => 'admin@dominio-ejemplo.es', '--nombre' => 'Admin', '--rol' => 'admin', '--password' => 'UnaClaveLarga-2026!'])->assertSuccessful();

        $u = User::where('email', 'admin@dominio-ejemplo.es')->firstOrFail();
        $this->assertTrue($u->hasRole('admin'));
        $this->assertTrue(Hash::check('UnaClaveLarga-2026!', $u->password));
    }

    public function test_solo_admite_los_roles_del_personal(): void
    {
        $this->artisan('despacho:crear-usuario', ['email' => 'x@dominio-real.es', '--rol' => 'client', '--password' => 'UnaClaveLarga-2026!'])->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_rechaza_contrasenas_cortas_y_correos_invalidos(): void
    {
        $this->artisan('despacho:crear-usuario', ['email' => 'x@dominio-real.es', '--password' => 'corta'])->assertFailed();
        $this->artisan('despacho:crear-usuario', ['email' => 'no-es-un-correo', '--password' => 'UnaClaveLarga-2026!'])->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_no_duplica_un_correo_existente(): void
    {
        $this->artisan('despacho:crear-usuario', ['email' => 'a@dominio-real.es', '--password' => 'UnaClaveLarga-2026!'])->assertSuccessful();
        $this->artisan('despacho:crear-usuario', ['email' => 'a@dominio-real.es', '--password' => 'OtraClaveLarga-2026!'])->assertFailed();

        $this->assertSame(1, User::count());
    }
}
