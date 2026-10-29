<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CommunicationDraft;
use App\Models\DocumentRequirement;
use App\Models\Expedient;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Comunicaciones sin documento (pedir documentación, recordatorios...): también quedan como solicitud de BORRADOR.
 * Nunca se envía nada.
 */
class ComunicacionesLibresTest extends TestCase
{
    use RefreshDatabase;

    private Expedient $exp;
    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        config(['services.microsoft' => ['enabled' => true, 'tenant' => 't', 'client_id' => 'c', 'client_secret' => 's', 'mailbox' => 'despacho@example.test']]);

        $client = Client::create(['codigo' => 'C-1', 'razon_social' => 'Cliente Uno', 'tipo' => 'empresa', 'email' => 'uno@example.test']);
        $service = Service::create(['codigo' => 'S', 'nombre' => 'Servicio', 'precio_base' => 0, 'tipo' => 'sin_cita']);
        DocumentRequirement::create(['service_id' => $service->id, 'codigo' => 'DNI', 'descripcion' => 'DNI del solicitante', 'obligatorio' => true, 'normalizado' => true, 'orden' => 1]);
        DocumentRequirement::create(['service_id' => $service->id, 'codigo' => 'OPT', 'descripcion' => 'Documento opcional', 'obligatorio' => false, 'normalizado' => true, 'orden' => 2]);
        $this->exp = Expedient::create(['codigo' => '2026-001', 'client_id' => $client->id, 'service_id' => $service->id, 'titulo' => 'Asunto', 'estado' => 'abierto']);
        $this->trabajador = User::factory()->create();
        $this->trabajador->assignRole('worker');
    }

    private function datos(array $over = []): array
    {
        return array_merge(['recipient' => 'cliente@example.test', 'subject' => 'Documentación pendiente', 'body' => 'Necesitamos su DNI.', 'tipo' => 'SOLICITUD_DOCUMENTACION', 'confirmado' => 1], $over);
    }

    private function enviar(array $over = [])
    {
        return $this->actingAs($this->trabajador)->post("/panel/expedientes/{$this->exp->id}/comunicaciones", $this->datos($over));
    }

    public function test_deja_una_solicitud_sin_adjunto_y_sin_permiso_de_envio(): void
    {
        $this->enviar()->assertSessionHas('success');

        $d = CommunicationDraft::firstOrFail();
        $this->assertNull($d->proposal_id);
        $this->assertSame($this->exp->id, $d->expedient_id);
        $this->assertSame([], $d->manifest['attachments']);
        $this->assertFalse($d->manifest['send_permitted']);
        $this->assertSame('pendiente_adaptador', $d->status);
        $this->assertSame($this->trabajador->id, $d->requested_by);
    }

    public function test_exige_un_unico_correo_valido_asunto_mensaje_y_confirmar(): void
    {
        foreach ([['recipient' => 'sin-arroba'], ['recipient' => 'a@b.com, c@d.com'], ['subject' => ''], ['body' => ''], ['confirmado' => 0], ['tipo' => 'INVENTADO']] as $malo) {
            $this->enviar($malo)->assertSessionHasErrors();
        }
        $this->assertSame(0, CommunicationDraft::count());
    }

    public function test_repetir_la_misma_comunicacion_no_la_duplica(): void
    {
        $this->enviar();
        $this->enviar()->assertSessionHas('success');

        $this->assertSame(1, CommunicationDraft::count());
    }

    public function test_un_cliente_no_puede_preparar_comunicaciones(): void
    {
        $c = User::factory()->create();
        $c->assignRole('client');

        $this->actingAs($c)->post("/panel/expedientes/{$this->exp->id}/comunicaciones", $this->datos())->assertForbidden();
    }

    public function test_el_borrador_sin_adjunto_se_crea_en_el_correo_y_se_verifica_sin_adjuntos(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            '*/mailFolders/drafts/messages*' => Http::response(['value' => []]),
            '*/messages/AAA*' => Http::response(['id' => 'AAA', 'isDraft' => true, 'subject' => 'Documentación pendiente', 'toRecipients' => [['emailAddress' => ['address' => 'cliente@example.test']]], 'attachments' => []]),
            '*/users/*/messages' => Http::response(['id' => 'AAA', 'webLink' => 'https://outlook.example/AAA', 'isDraft' => true], 201),
        ]);
        $this->enviar();

        $this->artisan('despacho:borradores')->assertSuccessful();

        $this->assertSame('borrador_creado', CommunicationDraft::firstOrFail()->status);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/attachments'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/send'));
    }

    public function test_el_formulario_de_documentacion_pendiente_trae_los_requisitos_obligatorios(): void
    {
        $this->actingAs($this->trabajador)->get("/panel/expedientes/{$this->exp->id}?comunicacion=documentacion")
            ->assertOk()->assertSee('- DNI del solicitante', false)->assertDontSee('- Documento opcional', false)->assertSee('uno@example.test');
    }

    public function test_la_lista_de_comunicaciones_muestra_las_que_no_tienen_propuesta(): void
    {
        $this->enviar();

        $this->actingAs($this->trabajador)->get('/panel/comunicaciones')->assertOk()->assertSee('Documentación pendiente')->assertSee('2026-001');
    }
}
