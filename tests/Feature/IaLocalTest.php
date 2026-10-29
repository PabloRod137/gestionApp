<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\Expedient;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Razonar con IA": modelo LOCAL (Ollama), bajo demanda, limitado al cliente del expediente y siempre como propuesta
 * que una persona confirma. Las pruebas simulan Ollama; no hay ningún modelo real.
 */
class IaLocalTest extends TestCase
{
    use RefreshDatabase;

    private Expedient $exp;
    private Client $cliente;
    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        config(['ia.enabled' => true, 'ia.ollama.url' => 'http://127.0.0.1:11434', 'ia.ollama.model' => 'modelo-de-prueba', 'ia.carpetas_raiz' => null]);

        $this->cliente = Client::create(['codigo' => 'C-1', 'razon_social' => 'Cliente Uno SL', 'tipo' => 'empresa', 'email' => 'uno@example.test']);
        $otro = Client::create(['codigo' => 'C-2', 'razon_social' => 'Cliente Secreto Dos SL', 'tipo' => 'empresa', 'nif' => 'B99999999']);
        $service = Service::create(['codigo' => 'S', 'nombre' => 'Servicio uno', 'precio_base' => 0, 'tipo' => 'sin_cita']);
        $this->exp = Expedient::create(['codigo' => '2026-001', 'client_id' => $this->cliente->id, 'service_id' => $service->id, 'titulo' => 'Asunto uno', 'estado' => 'abierto']);
        Expedient::create(['codigo' => '2026-002', 'client_id' => $otro->id, 'service_id' => $service->id, 'titulo' => 'Asunto del otro cliente', 'estado' => 'abierto']);
        $this->trabajador = User::factory()->create();
        $this->trabajador->assignRole('worker');
    }

    private function ollama(array $respuesta): void
    {
        Http::fake(['127.0.0.1:11434/*' => Http::response(['message' => ['content' => json_encode($respuesta)]])]);
    }

    private function preguntar(string $q = '¿Cuál es el NIF del cliente?')
    {
        return $this->actingAs($this->trabajador)->post("/panel/expedientes/{$this->exp->id}/razonar", ['question' => $q]);
    }

    public function test_apagada_no_llama_a_ningun_modelo(): void
    {
        config(['ia.enabled' => false]);
        Http::fake();

        $this->preguntar()->assertSessionHasErrors('ia');
        Http::assertNothingSent();
    }

    public function test_sin_servicio_responde_sin_usar_el_modelo(): void
    {
        $this->exp->update(['service_id' => null]);
        Http::fake();

        $this->preguntar()->assertSessionHas('ia_resultado');
        Http::assertNothingSent();
        $this->assertSame('NINGUNO', session('ia_resultado')['modelo']);
        $this->assertStringContainsString('servicio', mb_strtolower(session('ia_resultado')['resultado']['dato_propuesta']));
    }

    public function test_el_modelo_solo_recibe_datos_del_cliente_del_expediente(): void
    {
        $this->ollama(['dato_propuesta' => 'NO LOCALIZADO', 'fuente' => '', 'documento' => '', 'ruta' => '', 'expediente_origen' => '', 'justificacion' => 'Sin evidencia', 'estado_validacion' => 'PENDIENTE_VALIDACION']);

        $this->preguntar()->assertSessionHas('ia_resultado');

        Http::assertSent(function (Request $r) {
            $cuerpo = json_encode($r->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($cuerpo, 'Cliente Uno SL') && str_contains($cuerpo, '2026-001')
                && ! str_contains($cuerpo, 'Cliente Secreto Dos') && ! str_contains($cuerpo, 'B99999999') && ! str_contains($cuerpo, 'otro cliente')
                && $r['model'] === 'modelo-de-prueba' && $r['options']['temperature'] === 0 && $r['stream'] === false;
        });
    }

    public function test_la_respuesta_se_muestra_como_pendiente_de_validacion(): void
    {
        $this->ollama(['dato_propuesta' => 'B12345678', 'fuente' => 'Escritura', 'documento' => 'escritura.pdf', 'ruta' => '', 'expediente_origen' => '2026-001', 'justificacion' => 'Consta en la escritura', 'estado_validacion' => 'VALIDADO']);

        $this->preguntar();
        $r = session('ia_resultado');

        $this->assertSame('B12345678', $r['resultado']['dato_propuesta']);
        // aunque el modelo diga otra cosa, siempre queda pendiente de que una persona lo confirme
        $this->assertSame('PENDIENTE_VALIDACION', $r['resultado']['estado_validacion']);
        $this->assertSame('LOCAL_OLLAMA', $r['ruta']);
    }

    public function test_una_respuesta_que_no_es_json_da_un_error_claro(): void
    {
        Http::fake(['127.0.0.1:11434/*' => Http::response(['message' => ['content' => 'esto no es json']])]);

        $this->preguntar()->assertSessionHasErrors('ia');
        $this->assertNull(session('ia_resultado'));
    }

    public function test_si_el_modelo_local_no_responde_se_explica(): void
    {
        Http::fake(fn () => throw new ConnectionException('sin conexión'));

        $this->preguntar()->assertSessionHasErrors('ia');
        $this->assertStringContainsString('no responde', session('errors')->first('ia'));
    }

    public function test_la_pregunta_es_obligatoria_y_tiene_limite(): void
    {
        Http::fake();

        $this->preguntar('')->assertSessionHasErrors('question');
        $this->preguntar(str_repeat('a', 1201))->assertSessionHasErrors('question');
        Http::assertNothingSent();
    }

    public function test_cada_uso_queda_registrado_sin_guardar_la_pregunta(): void
    {
        $this->ollama(['dato_propuesta' => 'NO LOCALIZADO', 'estado_validacion' => 'PENDIENTE_VALIDACION']);

        $this->preguntar('Pregunta con un dato confidencial 12345');

        $a = CaseActivity::where('action', 'RAZONAMIENTO_IA')->firstOrFail();
        $this->assertSame($this->trabajador->id, $a->user_id);
        $this->assertStringNotContainsString('confidencial', (string) $a->detail);
        $this->assertStringContainsString('modelo-de-prueba', (string) $a->detail);
    }

    public function test_un_cliente_no_puede_usar_la_ia(): void
    {
        Http::fake();
        $c = User::factory()->create();
        $c->assignRole('client');

        $this->actingAs($c)->post("/panel/expedientes/{$this->exp->id}/razonar", ['question' => 'x'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_incorporar_un_dato_exige_confirmar_y_valida_el_formato(): void
    {
        $url = "/panel/expedientes/{$this->exp->id}/incorporar-dato";
        $this->actingAs($this->trabajador);

        $this->post($url, ['campo' => 'nif', 'valor' => 'B12345674'])->assertSessionHasErrors('confirmado');
        $this->post($url, ['campo' => 'nif', 'valor' => 'no-es-un-nif', 'confirmado' => 1])->assertSessionHasErrors('valor');
        $this->post($url, ['campo' => 'nombre', 'valor' => 'x', 'confirmado' => 1])->assertSessionHasErrors('campo');
        $this->assertNull($this->cliente->refresh()->nif);

        $this->post($url, ['campo' => 'nif', 'valor' => 'b12345674', 'confirmado' => 1])->assertSessionHasNoErrors();
        $this->assertSame('B12345674', $this->cliente->refresh()->nif);
        $this->assertTrue(CaseActivity::where('action', 'DATO_INCORPORADO_IA')->exists());
    }

    public function test_no_se_pisa_un_dato_que_ya_existe_sin_confirmarlo_expresamente(): void
    {
        $url = "/panel/expedientes/{$this->exp->id}/incorporar-dato";
        $this->actingAs($this->trabajador);

        $this->post($url, ['campo' => 'email', 'valor' => 'nuevo@example.test', 'confirmado' => 1])->assertSessionHasErrors('valor');
        $this->assertSame('uno@example.test', $this->cliente->refresh()->email);

        $this->post($url, ['campo' => 'email', 'valor' => 'nuevo@example.test', 'confirmado' => 1, 'reemplazar' => 1])->assertSessionHasNoErrors();
        $this->assertSame('nuevo@example.test', $this->cliente->refresh()->email);
    }

    public function test_mi_jornada_ofrece_preguntar_a_la_ia_solo_si_esta_activa(): void
    {
        $this->actingAs($this->trabajador)->get('/panel/dashboard')->assertOk()->assertSee('Preguntar a la IA')->assertSee('Analizar ahora');

        config(['ia.enabled' => false]);
        $this->actingAs($this->trabajador)->get('/panel/dashboard')->assertOk()->assertSee('Preguntar a la IA')->assertDontSee('Analizar ahora');
    }

    public function test_desde_mi_jornada_se_pregunta_por_un_expediente_y_se_vuelve_a_la_portada(): void
    {
        $this->ollama(['dato_propuesta' => 'B12345674', 'fuente' => 'Escritura', 'documento' => 'e.pdf', 'ruta' => '', 'expediente_origen' => '2026-001', 'justificacion' => 'Consta', 'estado_validacion' => 'VALIDADO']);

        $this->actingAs($this->trabajador)->post('/panel/ia/preguntar', ['expediente' => '2026-001', 'question' => '¿NIF?'])
            ->assertRedirect(route('panel.dashboard'))->assertSessionHas('ia_resultado');

        $r = session('ia_resultado');
        $this->assertSame($this->exp->id, $r['expediente_id']);
        $this->assertSame('PENDIENTE_VALIDACION', $r['resultado']['estado_validacion']);
        $this->assertTrue(CaseActivity::where('action', 'RAZONAMIENTO_IA')->where('expedient_id', $this->exp->id)->exists());
        // sigue limitado a ese cliente
        Http::assertSent(fn (Request $q) => ! str_contains(json_encode($q->data(), JSON_UNESCAPED_UNICODE), 'Cliente Secreto Dos'));
    }

    public function test_el_resultado_en_mi_jornada_permite_incorporar_el_dato_a_la_ficha_correcta(): void
    {
        $this->ollama(['dato_propuesta' => 'B12345674', 'fuente' => 'Escritura', 'documento' => 'e.pdf', 'ruta' => '', 'expediente_origen' => '2026-001', 'justificacion' => 'Consta', 'estado_validacion' => 'VALIDADO']);

        $this->actingAs($this->trabajador)->post('/panel/ia/preguntar', ['expediente' => '2026-001', 'question' => '¿NIF?']);

        $this->actingAs($this->trabajador)->get('/panel/dashboard')->assertOk()
            ->assertSee('Pendiente de validación')->assertSee('B12345674')->assertSee('Cliente Uno SL')
            ->assertSee("/panel/expedientes/{$this->exp->id}/incorporar-dato", false);
    }

    public function test_un_expediente_que_no_existe_no_llama_al_modelo(): void
    {
        Http::fake();

        $this->actingAs($this->trabajador)->post('/panel/ia/preguntar', ['expediente' => '9999-999', 'question' => '¿NIF?'])->assertSessionHasErrors('expediente');
        $this->actingAs($this->trabajador)->post('/panel/ia/preguntar', ['question' => '¿NIF?'])->assertSessionHasErrors('expediente');
        Http::assertNothingSent();
    }

    public function test_un_cliente_no_puede_preguntar_desde_mi_jornada(): void
    {
        Http::fake();
        $c = User::factory()->create();
        $c->assignRole('client');

        $this->actingAs($c)->post('/panel/ia/preguntar', ['expediente' => '2026-001', 'question' => 'x'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_la_pantalla_del_expediente_ofrece_la_ia_solo_si_esta_activa(): void
    {
        $this->actingAs($this->trabajador)->get("/panel/expedientes/{$this->exp->id}")->assertOk()->assertSee('Razonar con IA');

        config(['ia.enabled' => false]);
        $this->actingAs($this->trabajador)->get("/panel/expedientes/{$this->exp->id}")->assertOk()->assertDontSee('ANALIZAR AHORA');
    }
}
