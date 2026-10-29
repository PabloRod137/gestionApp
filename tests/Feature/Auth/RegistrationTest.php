<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El alta de usuarios la hace el despacho (cada cliente se da de alta desde el panel):
 * no hay registro abierto al publico.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_hay_pantalla_de_registro(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_nadie_puede_crearse_una_cuenta_por_su_cuenta(): void
    {
        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'password-largo-123',
            'password_confirmation' => 'password-largo-123',
        ])->assertStatus(404);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_el_acceso_no_ofrece_crear_cuenta(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Crear una cuenta');
    }
}
