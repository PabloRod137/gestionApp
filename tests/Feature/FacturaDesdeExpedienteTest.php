<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Expedient;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Preparar la factura" desde las actuaciones del expediente: el formulario llega rellenado con el servicio y su tarifa. */
class FacturaDesdeExpedienteTest extends TestCase
{
    use RefreshDatabase;

    private Expedient $exp;
    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $client = Client::create(['codigo' => 'C-1', 'razon_social' => 'Comunidad Demo', 'tipo' => 'comunidad']);
        $s = Service::create(['codigo' => 'CERT_DEUDA', 'nombre' => 'Certificado de deuda', 'precio_base' => 30, 'iva' => 10, 'tipo' => 'sin_cita', 'facturable' => true]);
        $this->exp = Expedient::create(['codigo' => '2026-100', 'client_id' => $client->id, 'service_id' => $s->id, 'titulo' => 'CERTIFICADO', 'estado' => 'finalizado']);
        $this->worker = User::factory()->create();
        $this->worker->assignRole('worker');
    }

    public function test_el_formulario_llega_con_cliente_expediente_concepto_importe_e_iva(): void
    {
        $r = $this->actingAs($this->worker)->get('/panel/facturas/create?expediente='.$this->exp->id)->assertOk();

        $r->assertSee('Certificado de deuda');
        $r->assertSee('value="30.00"', false);
        $r->assertSee('value="10"', false);
        $r->assertSeeInOrder(['value="'.$this->exp->client_id.'" selected', 'Comunidad Demo'], false);
        // el expediente ya finalizado también aparece, para poder facturarlo
        $r->assertSeeInOrder(['value="'.$this->exp->id.'" selected', 'CERTIFICADO'], false);
    }

    public function test_sin_expediente_el_formulario_sigue_en_blanco(): void
    {
        $this->actingAs($this->worker)->get('/panel/facturas/create')->assertOk()->assertDontSee('Certificado de deuda');
    }
}
