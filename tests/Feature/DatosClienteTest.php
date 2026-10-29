<?php

namespace Tests\Feature;

use App\Chat\ChatCliente;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Expedient;
use App\Models\Service;
use App\Models\SyncEvent;
use App\Models\User;
use App\Support\Sync\ExportadorSharePoint;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «Mis datos»: el cliente completa los huecos de su ficha; nunca pisa lo que ya consta (datos inventados). */
class DatosClienteTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private Client $clienteAna;
    private string $salida;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->salida = sys_get_temp_dir().'/datos'.uniqid();
        config(['sync.carpeta_salida' => $this->salida, 'services.power_automate.api_token' => 'token-de-prueba']);
        $this->ana = User::factory()->create(['name' => 'Ana Ejemplo']);
        $this->ana->assignRole('client');
        // ficha con solo el nombre y el teléfono: faltan NIF, correo y dirección
        $this->clienteAna = Client::create(['codigo' => 'C-ANA', 'razon_social' => 'Ana Ejemplo', 'tipo' => 'particular', 'telefono' => '600 111 222', 'user_id' => $this->ana->id]);
    }

    private function guardar(array $datos, ?User $como = null)
    {
        return $this->actingAs($como ?? $this->ana)->post('/cliente/mis-datos', $datos);
    }

    public function test_el_aviso_sale_en_todas_las_pantallas_mientras_falten_datos_y_desaparece_al_completar(): void
    {
        $this->actingAs($this->ana)->get('/cliente/dashboard')->assertOk()
            ->assertSee('nos faltan algunos datos')->assertSee('NIF / CIF')->assertSee('Correo electrónico')->assertSee('Dirección')
            ->assertDontSee('Teléfono</strong>', false);
        $this->actingAs($this->ana)->get('/cliente/expedientes')->assertOk()->assertSee('Completar mis datos');

        $this->clienteAna->update(['nif' => '12345678Z', 'email' => 'ana@ejemplo.test', 'direccion' => 'Calle Falsa 1, Santander']);
        $this->ana = $this->ana->fresh(); // el usuario de la prueba guarda la ficha anterior en memoria

        $this->actingAs($this->ana)->get('/cliente/dashboard')->assertOk()->assertDontSee('nos faltan algunos datos');
    }

    public function test_el_formulario_pide_solo_lo_que_falta_y_muestra_lo_que_ya_consta(): void
    {
        $this->actingAs($this->ana)->get('/cliente/mis-datos')->assertOk()
            ->assertSee('name="nif"', false)->assertSee('name="email"', false)->assertSee('name="direccion"', false)
            ->assertDontSee('name="telefono"', false)->assertSee('600 111 222')
            ->assertDontSee('nos faltan algunos datos');
    }

    public function test_guardar_rellena_los_huecos_deja_auditoria_y_prepara_el_envio_a_sharepoint(): void
    {
        $this->guardar(['nif' => '12345678z', 'email' => 'ANA@Ejemplo.test', 'direccion' => 'Calle Falsa 1, 39001 Santander'])
            ->assertRedirect('/cliente/mis-datos')->assertSessionHas('success');

        $c = $this->clienteAna->fresh();
        $this->assertSame('12345678Z', $c->nif);
        $this->assertSame('ana@ejemplo.test', $c->email);
        $this->assertSame('Calle Falsa 1, 39001 Santander', $c->direccion);

        $log = AuditLog::where('accion', 'datos_aportados_por_cliente')->firstOrFail();
        $this->assertSame($this->ana->id, $log->user_id);
        $this->assertEqualsCanonicalizing(['nif', 'email', 'direccion'], $log->datos_nuevos['campos']);
        $this->assertStringNotContainsString('12345678', json_encode($log->toArray()));

        $e = SyncEvent::firstOrFail();
        $this->assertSame($c->id, $e->client_id);
        $this->assertNull($e->expedient_id);
        $this->assertSame('pendiente', $e->estado);
        $this->assertSame('12345678Z', $e->datos['nif']);
        $this->assertArrayNotHasKey('telefono', $e->datos);
    }

    public function test_un_dato_que_ya_consta_nunca_se_cambia_aunque_se_envie_a_mano(): void
    {
        $this->guardar(['telefono' => '699 000 000', 'razon_social' => 'Otro Nombre', 'tipo' => 'empresa', 'user_id' => 999, 'email' => 'ana@ejemplo.test']);

        $c = $this->clienteAna->fresh();
        $this->assertSame('600 111 222', $c->telefono);
        $this->assertSame('Ana Ejemplo', $c->razon_social);
        $this->assertSame('particular', $c->tipo);
        $this->assertSame($this->ana->id, $c->user_id);
        $this->assertSame('ana@ejemplo.test', $c->email);
    }

    public function test_un_dato_no_valido_se_devuelve_con_su_explicacion_y_no_se_guarda_nada(): void
    {
        $this->guardar(['nif' => '123', 'email' => 'no-es-un-correo', 'direccion' => 'Calle Falsa 1, Santander'])->assertRedirect();

        $c = $this->clienteAna->fresh();
        $this->assertNull($c->nif);
        $this->assertNull($c->email);
        $this->assertNull($c->direccion);
        $this->assertSame(0, SyncEvent::count());
        $this->actingAs($this->ana)->get('/cliente/mis-datos')->assertSee('Escribe un NIF, NIE o CIF válido')->assertSee('Escribe un correo electrónico válido')->assertSee('Calle Falsa 1, Santander');
    }

    public function test_se_pueden_completar_solo_algunos_datos_y_volver_mas_tarde(): void
    {
        $this->guardar(['nif' => '12345678Z', 'email' => '', 'direccion' => ''])->assertSessionHas('success');
        $this->assertSame('12345678Z', $this->clienteAna->fresh()->nif);
        $this->assertNull($this->clienteAna->fresh()->email);

        $this->guardar(['email' => 'ana@ejemplo.test'])->assertSessionHas('success');
        $this->assertSame('ana@ejemplo.test', $this->clienteAna->fresh()->email);
        $this->assertSame(2, SyncEvent::count());
    }

    public function test_enviar_el_formulario_vacio_no_guarda_ni_genera_eventos(): void
    {
        $this->guardar(['nif' => '', 'email' => '', 'direccion' => ''])->assertSessionHas('error');

        $this->assertSame(0, SyncEvent::count());
        $this->assertSame(0, AuditLog::where('accion', 'datos_aportados_por_cliente')->count());
    }

    public function test_un_nif_que_ya_consta_en_otra_ficha_no_se_guarda_y_no_se_revela_de_quien_es(): void
    {
        Client::create(['codigo' => 'C-BETO', 'razon_social' => 'Beto Ajeno', 'tipo' => 'particular', 'nif' => '87654321X']);

        $r = $this->guardar(['nif' => '87654321X', 'email' => 'ana@ejemplo.test']);

        $this->assertNull($this->clienteAna->fresh()->nif);
        $this->assertSame('ana@ejemplo.test', $this->clienteAna->fresh()->email);
        $r->assertSessionHas('error');
        $this->assertStringNotContainsString('Beto', (string) session('error'));
        $this->assertStringContainsString('Escribe a tu asesor', (string) session('error'));
        $this->assertArrayNotHasKey('nif', SyncEvent::firstOrFail()->datos);
    }

    public function test_cada_cliente_solo_completa_su_propia_ficha(): void
    {
        $beto = User::factory()->create(['name' => 'Beto Ajeno']);
        $beto->assignRole('client');
        $fichaBeto = Client::create(['codigo' => 'C-BETO', 'razon_social' => 'Beto Ajeno', 'tipo' => 'particular', 'user_id' => $beto->id]);

        $this->guardar(['email' => 'ana@ejemplo.test', 'client_id' => $fichaBeto->id], $this->ana);

        $this->assertSame('ana@ejemplo.test', $this->clienteAna->fresh()->email);
        $this->assertNull($fichaBeto->fresh()->email);
    }

    public function test_hay_que_ser_cliente_para_usar_el_formulario(): void
    {
        $this->get('/cliente/mis-datos')->assertRedirect('/login');
        $worker = User::factory()->create();
        $worker->assignRole('worker');
        $this->actingAs($worker)->get('/cliente/mis-datos')->assertForbidden();
        $this->actingAs($worker)->post('/cliente/mis-datos', ['email' => 'x@y.test'])->assertForbidden();
    }

    public function test_exportar_deja_los_datos_de_clientes_en_un_archivo_aparte_con_solo_los_campos_completados(): void
    {
        $this->guardar(['nif' => '12345678Z', 'email' => 'ana@ejemplo.test']);
        $servicio = Service::create(['codigo' => 'S', 'nombre' => 'Servicio', 'precio_base' => 0, 'tipo' => 'sin_cita']);
        $exp = Expedient::create(['codigo' => '2026-001', 'client_id' => $this->clienteAna->id, 'service_id' => $servicio->id, 'titulo' => 'Asunto', 'estado' => 'abierto']);
        \App\Models\CaseActivity::registrar($exp, 'PROPUESTA_CREADA', 'x', $this->ana);

        $r = app(ExportadorSharePoint::class)->exportar();

        $this->assertSame(1, $r['clientes']);
        $this->assertSame(1, $r['eventos']);
        $json = json_decode(file_get_contents($r['json_clientes']), true);
        $this->assertSame('C-ANA', $json['clientes'][0]['Codigo_Cliente']);
        $this->assertSame('12345678Z', $json['clientes'][0]['NIF']);
        $this->assertSame('ana@ejemplo.test', $json['clientes'][0]['Email']);
        $this->assertSame('', $json['clientes'][0]['Direccion']);
        $this->assertSame('', $json['clientes'][0]['Telefono']);
        $csv = file_get_contents($r['csv_clientes']);
        $this->assertStringStartsWith("\xEF\xBB\xBFID_Evento,Codigo_Cliente,NIF,Email,Telefono,Direccion", $csv);
        // la actividad de expedientes sigue en su archivo y sin datos personales del cliente
        $this->assertStringNotContainsString('12345678Z', file_get_contents($r['json']));
        $this->assertSame(0, SyncEvent::where('estado', 'pendiente')->count());
    }

    public function test_la_api_entrega_los_datos_de_clientes_y_se_confirman_igual_que_el_resto(): void
    {
        $this->guardar(['nif' => '12345678Z']);
        $id = SyncEvent::firstOrFail()->evento_id;

        $r = $this->withToken('token-de-prueba')->getJson('/api/v1/power-automate/estados')->assertOk();
        $this->assertSame([], $r->json('eventos'));
        $this->assertSame($id, $r->json('clientes.0.ID_Evento'));
        $this->assertSame('12345678Z', $r->json('clientes.0.NIF'));

        $this->withToken('token-de-prueba')->postJson('/api/v1/power-automate/estados/confirmar', ['ids' => [$id]])->assertOk()->assertJson(['confirmados' => 1]);
        $this->assertSame('confirmado', SyncEvent::firstOrFail()->estado);
    }

    public function test_los_datos_completados_al_generar_un_documento_tambien_salen_hacia_sharepoint(): void
    {
        $servicio = Service::create(['codigo' => 'S', 'nombre' => 'Servicio', 'precio_base' => 0, 'tipo' => 'sin_cita']);
        $exp = Expedient::create(['codigo' => '2026-001', 'client_id' => $this->clienteAna->id, 'service_id' => $servicio->id, 'titulo' => 'Asunto', 'estado' => 'abierto']);
        $worker = User::factory()->create(['name' => 'Asesora Prueba']);
        $worker->assignRole('worker');

        $marcas = app(\App\Support\GeneradorDocumentos::class)->guardarEnFicha($exp, ['cliente_nif' => '12345678Z', 'cliente_telefono' => '699 999 999'], $worker);

        $this->assertSame(['cliente_nif'], $marcas); // el teléfono ya constaba: no se pisa
        $e = SyncEvent::firstOrFail();
        $this->assertSame('Asesora Prueba', $e->actor);
        $this->assertSame(['nif' => '12345678Z'], $e->datos);
    }

    public function test_el_asistente_remite_al_formulario_cuando_faltan_datos(): void
    {
        $r = app(ChatCliente::class)->handle($this->clienteAna, $this->ana, ['text' => 'quiero completar mis datos']);

        $this->assertSame('datos', $r['intent']);
        $this->assertStringContainsString('NIF / CIF', $r['messages'][0]['text']);
        $this->assertSame('/cliente/mis-datos', $r['messages'][0]['cards'][0]['href']);
    }

    public function test_la_pantalla_de_sincronizacion_muestra_los_eventos_de_cliente_sin_romperse(): void
    {
        $this->guardar(['nif' => '12345678Z']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/panel/sincronizacion')->assertOk()->assertSee('Datos del cliente completados')->assertSee('Ana Ejemplo');
        $this->actingAs($admin)->post('/panel/sincronizacion/exportar')->assertSessionHas('success');
    }
}
