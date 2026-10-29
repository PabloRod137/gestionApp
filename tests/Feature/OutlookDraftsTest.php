<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\CommunicationDraft;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Proposal;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Conexion con el correo de Microsoft 365: solo se crean BORRADORES. Nunca se envia nada.
 * Las pruebas usan respuestas simuladas de Microsoft Graph, no el servicio real.
 */
class OutlookDraftsTest extends TestCase
{
    use RefreshDatabase;

    private CommunicationDraft $draft;
    private string $pdf = "%PDF-1.4\nDocumento revisado\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(RolesSeeder::class);
        config(['services.microsoft' => ['enabled' => true, 'tenant' => 'tenant-x', 'client_id' => 'cid', 'client_secret' => 'secreto', 'mailbox' => 'despacho@example.test']]);

        $client = Client::create(['codigo' => 'C-1', 'razon_social' => 'Cliente Uno', 'tipo' => 'empresa']);
        $service = Service::create(['codigo' => 'S', 'nombre' => 'Servicio', 'precio_base' => 0, 'tipo' => 'sin_cita']);
        $exp = Expedient::create(['codigo' => '2026-001', 'client_id' => $client->id, 'service_id' => $service->id, 'titulo' => 'Asunto', 'estado' => 'abierto']);
        Storage::put('expedientes/1/revisado.pdf', $this->pdf);
        $doc = ExpedientDocument::create(['expedient_id' => $exp->id, 'client_id' => $client->id, 'nombre' => 'Documento (PDF revisado).pdf', 'ruta' => 'expedientes/1/revisado.pdf', 'tipo_mime' => 'application/pdf', 'canal_entrada' => 'generado']);
        $p = Proposal::create(['expedient_id' => $exp->id, 'title' => 'Propuesta', 'purpose' => 'x', 'status' => 'communication_requested', 'pdf_document_id' => $doc->id, 'pdf_sha256' => hash('sha256', $this->pdf)]);
        $this->draft = CommunicationDraft::create([
            'proposal_id' => $p->id, 'idempotency_key' => str_repeat('a', 64), 'recipient' => 'cliente@example.test', 'subject' => 'Asunto', 'body' => 'Cuerpo del mensaje',
            'attachment_document_id' => $doc->id, 'status' => 'pendiente_adaptador',
            'manifest' => ['attachments' => [['kind' => 'pdf', 'name' => $doc->nombre, 'sha256' => hash('sha256', $this->pdf)]], 'send_permitted' => false],
        ]);
    }

    /** Respuestas simuladas de Graph; $over permite cambiar una. */
    private function graph(array $over = []): void
    {
        $base = [
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token-de-prueba', 'expires_in' => 3600]),
            '*/mailFolders/drafts/messages*' => Http::response(['value' => []]),
            '*/messages/AAA/attachments/BBB' => Http::response(['id' => 'BBB', 'name' => 'Documento (PDF revisado).pdf', 'contentBytes' => base64_encode($this->pdf)]),
            '*/messages/AAA/attachments' => Http::response(['id' => 'BBB'], 201),
            '*/messages/AAA*' => Http::response(['id' => 'AAA', 'isDraft' => true, 'subject' => 'Asunto', 'toRecipients' => [['emailAddress' => ['address' => 'cliente@example.test']]],
                'attachments' => [['id' => 'BBB', 'name' => 'Documento (PDF revisado).pdf']]]),
            '*/users/*/messages' => Http::response(['id' => 'AAA', 'webLink' => 'https://outlook.example/draft/AAA', 'isDraft' => true], 201),
        ];
        Http::fake(array_merge($base, $over));
    }

    private function peticiones(): array
    {
        return Http::recorded()->map(fn ($par) => $par[0]->method().' '.$par[0]->url())->all();
    }

    public function test_crea_el_borrador_con_el_pdf_lo_verifica_y_no_envia_nada(): void
    {
        $this->graph();

        $this->artisan('despacho:borradores')->assertSuccessful();

        $d = $this->draft->refresh();
        $this->assertSame('borrador_creado', $d->status);
        $this->assertSame('AAA', $d->remote_id);
        $this->assertNotNull($d->remote_verified_at);
        $this->assertSame('https://outlook.example/draft/AAA', $d->remote_web_link);
        foreach ($this->peticiones() as $p) {
            $this->assertStringNotContainsString('/send', $p, 'El programa nunca debe enviar el correo');
        }
        $this->assertTrue(CaseActivity::where('action', 'BORRADOR_CREADO')->exists());
    }

    public function test_el_borrador_se_pide_con_el_destinatario_el_asunto_y_el_pdf_exactos(): void
    {
        $this->graph();
        $this->artisan('despacho:borradores');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/users/despacho%40example.test/messages')
            && $r['subject'] === 'Asunto' && $r['toRecipients'][0]['emailAddress']['address'] === 'cliente@example.test');
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/messages/AAA/attachments')
            && base64_decode($r['contentBytes']) === $this->pdf && $r['name'] === 'Documento (PDF revisado).pdf');
    }

    public function test_si_ya_existe_el_borrador_no_se_crea_otro(): void
    {
        $this->graph(['*/mailFolders/drafts/messages*' => Http::response(['value' => [['id' => 'AAA', 'webLink' => 'https://outlook.example/draft/AAA']]])]);

        $this->artisan('despacho:borradores');

        $this->assertSame('borrador_creado', $this->draft->refresh()->status);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/messages'));
    }

    public function test_un_fallo_de_microsoft_deja_la_solicitud_en_error_y_se_puede_reintentar(): void
    {
        // la primera creación falla y la segunda funciona
        $this->graph(['*/users/*/messages' => Http::sequence()
            ->push(['error' => ['message' => 'fallo']], 500)
            ->push(['id' => 'AAA', 'webLink' => 'https://outlook.example/draft/AAA', 'isDraft' => true], 201)]);
        $this->artisan('despacho:borradores');

        $d = $this->draft->refresh();
        $this->assertSame('error', $d->status);
        $this->assertSame(1, $d->attempts);
        $this->assertNotEmpty($d->last_error);
        $this->assertStringNotContainsString('secreto', $d->last_error);

        $this->artisan('despacho:borradores');
        $this->assertSame('borrador_creado', $this->draft->refresh()->status);
    }

    public function test_si_el_adjunto_no_coincide_no_se_da_por_creado(): void
    {
        $this->graph(['*/messages/AAA/attachments/BBB' => Http::response(['id' => 'BBB', 'name' => 'x.pdf', 'contentBytes' => base64_encode('otro contenido')])]);

        $this->artisan('despacho:borradores');

        $d = $this->draft->refresh();
        $this->assertSame('error', $d->status);
        $this->assertNull($d->remote_verified_at);
    }

    public function test_si_el_pdf_cambio_despues_de_aprobarlo_no_se_manda_a_microsoft(): void
    {
        $this->graph();
        Storage::put('expedientes/1/revisado.pdf', "%PDF-1.4\nOTRO\n%%EOF\n");

        $this->artisan('despacho:borradores');

        $this->assertSame('error', $this->draft->refresh()->status);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'graph.microsoft.com'));
    }

    public function test_sin_configurar_no_hace_nada_y_lo_explica(): void
    {
        config(['services.microsoft' => ['enabled' => false, 'tenant' => null, 'client_id' => null, 'client_secret' => null, 'mailbox' => null]]);
        Http::fake();

        $this->artisan('despacho:borradores')->expectsOutputToContain('no está configurad')->assertSuccessful();

        $this->assertSame('pendiente_adaptador', $this->draft->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_el_equipo_puede_crear_el_borrador_desde_la_pantalla_y_un_cliente_no(): void
    {
        $this->graph();
        $cliente = User::factory()->create();
        $cliente->assignRole('client');
        $this->actingAs($cliente)->post("/panel/comunicaciones/{$this->draft->id}/crear")->assertForbidden();

        $trabajador = User::factory()->create();
        $trabajador->assignRole('worker');
        $this->actingAs($trabajador)->post("/panel/comunicaciones/{$this->draft->id}/crear")->assertSessionHas('success');

        $this->assertSame('borrador_creado', $this->draft->refresh()->status);
        $this->actingAs($trabajador)->get('/panel/comunicaciones')->assertOk()->assertSee('Abrir borrador en Outlook');
    }
}
