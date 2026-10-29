<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\Proposal;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pantalla del expediente: sugerir servicio por el asunto (palabras clave o IA local), asignarlo y proponer las actuaciones. */
class ActuacionesAutomaticasTest extends TestCase
{
    use RefreshDatabase;

    private Expedient $exp;
    private Service $cert;
    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(RolesSeeder::class);
        config(['ia.enabled' => false, 'ia.ollama.url' => 'http://127.0.0.1:11434', 'ia.ollama.model' => 'modelo-de-prueba', 'recetas.modelo_presupuesto' => 'PRESUP']);
        Storage::put('p/cert.docx', 'x');
        Storage::put('p/presup.docx', 'x');
        $plt = DocumentTemplate::create(['codigo' => 'CERT', 'nombre' => 'Certificado de deuda', 'tipo_documento' => 'CERTIFICADO', 'archivo' => 'p/cert.docx']);
        DocumentTemplate::create(['codigo' => 'PRESUP', 'nombre' => 'Presupuesto', 'tipo_documento' => 'ECONOMICO', 'archivo' => 'p/presup.docx']);
        $this->cert = Service::create(['codigo' => 'CERT_DEUDA', 'nombre' => 'Certificado de deuda', 'precio_base' => 30, 'iva' => 21, 'tipo' => 'sin_cita', 'facturable' => true, 'genera_documento' => true, 'tipo_servicio' => 'Facturable', 'palabras_clave' => 'certificado, deuda, corriente']);
        $this->cert->templates()->attach($plt->id);
        Service::create(['codigo' => 'MOD_303', 'nombre' => 'IVA trimestral', 'precio_base' => 60, 'tipo' => 'sin_cita', 'palabras_clave' => '303, iva']);
        $client = Client::create(['codigo' => 'C-1', 'razon_social' => 'Comunidad Demo', 'tipo' => 'comunidad']);
        $this->exp = Expedient::create(['codigo' => '2026-100', 'client_id' => $client->id, 'titulo' => 'CERTIFICADO AL CORRIENTE', 'estado' => 'abierto']);
        $this->worker = User::factory()->create();
        $this->worker->assignRole('worker');
    }

    private function ollama(array $r): void
    {
        Http::fake(['127.0.0.1:11434/*' => Http::response(['message' => ['content' => json_encode($r)]])]);
    }

    public function test_sin_servicio_la_pantalla_ofrece_sugerir_y_con_servicio_ofrece_proponer(): void
    {
        $this->actingAs($this->worker)->get("/panel/expedientes/{$this->exp->id}")->assertOk()->assertSee('Sugerir servicio');

        $this->exp->update(['service_id' => $this->cert->id]);
        $this->actingAs($this->worker)->get("/panel/expedientes/{$this->exp->id}")->assertOk()->assertSee('Proponer actuaciones')->assertSee('Presupuesto: Certificado de deuda');
    }

    public function test_sugerir_servicio_usa_las_palabras_clave_sin_llamar_al_modelo(): void
    {
        Http::fake();

        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/sugerir-servicio")->assertSessionHas('servicio_sugerido');

        $s = session('servicio_sugerido');
        $this->assertSame($this->cert->id, $s['candidatos'][0]['id']);
        $this->assertNull($s['ia']);
        Http::assertNothingSent();
    }

    public function test_con_la_ia_activada_y_pedida_el_modelo_elige_solo_entre_los_candidatos_y_sin_datos_del_cliente(): void
    {
        config(['ia.enabled' => true]);
        $this->ollama(['codigo_servicio' => 'CERT_DEUDA', 'motivo' => 'El asunto pide un certificado']);

        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/sugerir-servicio", ['usar_ia' => 1])->assertSessionHas('servicio_sugerido');

        $this->assertSame($this->cert->id, session('servicio_sugerido')['ia']['id']);
        Http::assertSent(function (Request $r) {
            $cuerpo = json_encode($r->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($cuerpo, 'CERTIFICADO AL CORRIENTE') && ! str_contains($cuerpo, 'Comunidad Demo') && $r['options']['temperature'] === 0;
        });
        $this->assertTrue(CaseActivity::where('action', 'SERVICIO_SUGERIDO_IA')->exists());
    }

    public function test_si_la_ia_inventa_un_codigo_que_no_estaba_en_la_lista_se_descarta(): void
    {
        config(['ia.enabled' => true]);
        $this->ollama(['codigo_servicio' => 'SERVICIO_INVENTADO', 'motivo' => 'x']);

        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/sugerir-servicio", ['usar_ia' => 1])->assertSessionHas('servicio_sugerido');

        $this->assertNull(session('servicio_sugerido')['ia']);
    }

    public function test_si_la_ia_no_responde_se_siguen_mostrando_las_sugerencias_por_palabras_clave(): void
    {
        config(['ia.enabled' => true]);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('sin conexión'));

        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/sugerir-servicio", ['usar_ia' => 1])->assertSessionHas('servicio_sugerido');

        $s = session('servicio_sugerido');
        $this->assertNull($s['ia']);
        $this->assertSame($this->cert->id, $s['candidatos'][0]['id']);
        $this->assertStringContainsString('no responde', $s['aviso_ia']);
    }

    public function test_asignar_el_servicio_lo_guarda_y_deja_constancia(): void
    {
        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/asignar-servicio", ['service_id' => $this->cert->id, 'origen' => 'sugerencia'])->assertSessionHas('success');

        $this->assertSame($this->cert->id, $this->exp->refresh()->service_id);
        $this->assertTrue(CaseActivity::where('action', 'SERVICIO_ASIGNADO')->where('user_id', $this->worker->id)->exists());
    }

    public function test_no_se_cambia_un_servicio_ya_asignado_sin_confirmarlo(): void
    {
        $otro = Service::where('codigo', 'MOD_303')->first();
        $this->exp->update(['service_id' => $otro->id]);

        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/asignar-servicio", ['service_id' => $this->cert->id])->assertSessionHasErrors('service_id');
        $this->assertSame($otro->id, $this->exp->refresh()->service_id);

        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/asignar-servicio", ['service_id' => $this->cert->id, 'cambiar' => 1])->assertSessionHas('success');
        $this->assertSame($this->cert->id, $this->exp->refresh()->service_id);
    }

    public function test_proponer_actuaciones_crea_las_propuestas_pendientes(): void
    {
        $this->exp->update(['service_id' => $this->cert->id]);

        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/proponer-actuaciones")->assertSessionHas('success');

        $this->assertSame(2, Proposal::count());
        $this->assertSame(['proposed'], Proposal::pluck('status')->unique()->values()->all());
        $this->assertTrue(CaseActivity::where('action', 'PROPUESTA_CREADA')->exists());
    }

    public function test_proponer_sin_servicio_explica_que_falta(): void
    {
        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/proponer-actuaciones")->assertSessionHasErrors('workflow');
        $this->assertSame(0, Proposal::count());
    }

    public function test_un_cliente_no_puede_usar_estas_acciones(): void
    {
        Http::fake();
        $c = User::factory()->create();
        $c->assignRole('client');
        foreach (['sugerir-servicio', 'asignar-servicio', 'proponer-actuaciones'] as $accion) {
            $this->actingAs($c)->post("/panel/expedientes/{$this->exp->id}/{$accion}", ['service_id' => $this->cert->id])->assertForbidden();
        }
        Http::assertNothingSent();
    }
}
