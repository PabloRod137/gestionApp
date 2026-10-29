<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\Service;
use App\Models\SyncEvent;
use App\Models\User;
use App\Support\Sync\ExportadorSharePoint;
use App\Support\Sync\PuenteSharePoint;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Puente con SharePoint: cada avance en el panel genera un evento con su identificador, sale hacia Power Automate
 * (archivo en una carpeta o API) y se confirma cuando SharePoint lo refleja. El programa no escribe en SharePoint.
 */
class PuenteSharePointTest extends TestCase
{
    use RefreshDatabase;

    private Expedient $exp;
    private User $worker;
    private string $salida;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(RolesSeeder::class);
        $this->salida = sys_get_temp_dir().'/salida'.uniqid();
        config(['sync.carpeta_salida' => $this->salida, 'sync.avance_automatico' => true, 'services.power_automate.api_token' => 'token-de-prueba']);
        $client = Client::create(['codigo' => 'C-1', 'razon_social' => 'Cliente Uno SL', 'tipo' => 'empresa']);
        $service = Service::create(['codigo' => 'S', 'nombre' => 'Servicio', 'precio_base' => 10, 'tipo' => 'sin_cita', 'genera_documento' => true]);
        $this->exp = Expedient::create(['codigo' => '2026-001', 'client_id' => $client->id, 'service_id' => $service->id, 'titulo' => 'Asunto', 'estado' => 'abierto']);
        $this->worker = User::factory()->create(['name' => 'Trabajadora Prueba']);
        $this->worker->assignRole('worker');
    }

    private function evento(string $accion = 'DOCUMENTO_GENERADO'): SyncEvent
    {
        CaseActivity::registrar($this->exp, $accion, 'detalle', $this->worker);

        return SyncEvent::orderByDesc('id')->firstOrFail();
    }

    public function test_un_avance_importante_deja_un_evento_pendiente_con_su_identificador_y_el_expediente(): void
    {
        $e = $this->evento('PROPUESTA_CREADA');

        $this->assertSame('pendiente', $e->estado);
        $this->assertSame(sprintf('LG-%06d', $e->id), $e->evento_id);
        $this->assertSame($this->exp->id, $e->expedient_id);
        $this->assertSame('Trabajadora Prueba', $e->actor);
        $this->assertSame('Nortia', $e->origen);
        $this->assertSame('Propuesta', $e->tipo);
    }

    public function test_lo_que_no_es_un_avance_no_sale_hacia_sharepoint(): void
    {
        foreach (['RAZONAMIENTO_IA', 'DATO_INCORPORADO_IA', 'SERVICIO_SUGERIDO_IA', 'DATOS_FICHA_COMPLETADOS', 'PDF_NO_GENERADO'] as $accion) {
            CaseActivity::registrar($this->exp, $accion, 'x', $this->worker);
        }

        $this->assertSame(0, SyncEvent::count());
    }

    public function test_cada_evento_lleva_el_estado_actual_traducido_al_vocabulario_de_sharepoint(): void
    {
        config(['sync.avance_automatico' => false]);
        $this->assertSame('No iniciado', $this->evento()->estado_nuevo);
        foreach (['en_proceso' => 'En curso', 'pendiente_cliente' => 'En curso', 'finalizado' => 'Completado'] as $interno => $sharepoint) {
            $this->exp->update(['estado' => $interno]);
            $this->assertSame($sharepoint, $this->evento()->estado_nuevo, $interno);
        }
        // "cancelado" no existe en SharePoint: no se manda ningún estado
        $this->exp->update(['estado' => 'cancelado']);
        $this->assertNull($this->evento()->estado_nuevo);
    }

    public function test_cambiar_el_estado_desde_el_panel_deja_un_evento_con_estado_anterior_y_nuevo(): void
    {
        config(['sync.avance_automatico' => false]);
        $this->actingAs($this->worker)->put("/panel/expedientes/{$this->exp->id}", [
            'client_id' => $this->exp->client_id, 'service_id' => $this->exp->service_id, 'titulo' => 'Asunto', 'estado' => 'en_proceso', 'fecha_apertura' => '2026-10-01',
        ])->assertSessionHasNoErrors();

        $e = SyncEvent::firstOrFail();
        $this->assertSame('Cambio de estado', $e->actividad);
        $this->assertSame('No iniciado', $e->estado_anterior);
        $this->assertSame('En curso', $e->estado_nuevo);
        $this->assertSame('Trabajadora Prueba', $e->actor);
    }

    public function test_guardar_sin_cambiar_el_estado_no_genera_eventos(): void
    {
        $this->actingAs($this->worker)->put("/panel/expedientes/{$this->exp->id}", [
            'client_id' => $this->exp->client_id, 'service_id' => $this->exp->service_id, 'titulo' => 'Otro título', 'estado' => 'abierto', 'fecha_apertura' => '2026-10-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, SyncEvent::count());
    }

    public function test_el_primer_avance_pasa_un_expediente_no_iniciado_a_en_curso_y_no_toca_otros_estados(): void
    {
        $this->evento('PROPUESTA_APROBADA');
        $this->assertSame('en_proceso', $this->exp->refresh()->estado);
        $cambio = SyncEvent::where('actividad', 'Cambio de estado')->firstOrFail();
        $this->assertSame(['No iniciado', 'En curso'], [$cambio->estado_anterior, $cambio->estado_nuevo]);

        $this->exp->update(['estado' => 'finalizado']);
        $this->evento('DOCUMENTO_GENERADO');
        $this->assertSame('finalizado', $this->exp->refresh()->estado);
    }

    public function test_el_avance_automatico_se_puede_apagar(): void
    {
        config(['sync.avance_automatico' => false]);

        $this->evento('PROPUESTA_APROBADA');

        $this->assertSame('abierto', $this->exp->refresh()->estado);
    }

    public function test_aprobar_una_propuesta_real_mueve_el_expediente_y_deja_los_eventos(): void
    {
        $w = new PhpWord;
        $w->addSection()->addText('Hola');
        $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        IOFactory::createWriter($w, 'Word2007')->save($tmp);
        Storage::put('plantillas/P.docx', file_get_contents($tmp));
        $t = DocumentTemplate::create(['codigo' => 'P', 'nombre' => 'Modelo', 'tipo_documento' => 'CERTIFICADO', 'archivo' => 'plantillas/P.docx']);
        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/propuestas", ['title' => 'T', 'purpose' => 'F', 'document_template_id' => $t->id]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $p = \App\Models\Proposal::firstOrFail();

        $this->actingAs($admin)->post("/panel/propuestas/{$p->id}/aprobar", ['version' => $p->version]);

        $this->assertSame('en_proceso', $this->exp->refresh()->estado);
        $this->assertEqualsCanonicalizing(['Propuesta documental creada', 'Cambio de estado', 'Propuesta aprobada'], SyncEvent::pluck('actividad')->all());
    }

    public function test_exportar_escribe_un_json_y_un_csv_con_las_columnas_de_la_lista_de_actividad_y_marca_los_eventos(): void
    {
        $e = $this->evento('DOCUMENTO_APROBADO');

        $r = app(ExportadorSharePoint::class)->exportar();

        $this->assertSame(1 + SyncEvent::where('actividad', 'Cambio de estado')->count(), $r['eventos']);
        $json = json_decode(file_get_contents($r['json']), true);
        $fila = collect($json['eventos'])->firstWhere('ID_Evento', $e->evento_id);
        $this->assertSame(['ID_Evento', 'Codigo_Expediente', 'Actividad', 'Tipo_Actividad', 'Descripcion', 'Actor', 'Origen', 'FechaHora_Actividad', 'Documento_Referencia', 'Estado_Anterior', 'Estado_Nuevo'], array_keys($fila));
        $this->assertSame('2026-001', $fila['Codigo_Expediente']);
        $this->assertSame('Nortia', $fila['Origen']);

        $csv = file_get_contents($r['csv']);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ID_Evento,Codigo_Expediente,Actividad', $csv);
        $this->assertStringContainsString($e->evento_id, $csv);

        $e->refresh();
        $this->assertSame('exportado', $e->estado);
        $this->assertNotNull($e->exportado_en);
        $this->assertSame(basename($r['json']), $e->archivo);
    }

    public function test_exportar_no_repite_lo_ya_exportado_ni_deja_archivos_a_medias(): void
    {
        $this->evento();
        app(ExportadorSharePoint::class)->exportar();

        $this->assertNull(app(ExportadorSharePoint::class)->exportar());
        $this->assertSame([], glob($this->salida.'/*.tmp') ?: []);
        $this->assertCount(2, glob($this->salida.'/*'));
    }

    public function test_el_comando_exporta_y_avisa_si_no_hay_nada(): void
    {
        $this->artisan('despacho:exportar-estados')->expectsOutputToContain('No hay cambios')->assertSuccessful();

        $this->evento();
        $this->artisan('despacho:exportar-estados')->expectsOutputToContain('Exportados')->assertSuccessful();
        $this->assertNotEmpty(glob($this->salida.'/*.json'));
    }

    public function test_la_api_exige_el_token_de_power_automate(): void
    {
        $this->getJson('/api/v1/power-automate/estados')->assertStatus(401);
        $this->withToken('otro')->getJson('/api/v1/power-automate/estados')->assertStatus(401);
        $this->withToken('token-de-prueba')->getJson('/api/v1/power-automate/estados')->assertOk();
    }

    public function test_power_automate_consulta_lo_pendiente_y_lo_confirma(): void
    {
        $e = $this->evento();

        $r = $this->withToken('token-de-prueba')->getJson('/api/v1/power-automate/estados')->assertOk();
        $ids = collect($r->json('eventos'))->pluck('ID_Evento');
        $this->assertTrue($ids->contains($e->evento_id));
        $this->assertSame('exportado', $e->refresh()->estado);

        // ya entregado: no se vuelve a entregar
        $this->assertSame([], $this->withToken('token-de-prueba')->getJson('/api/v1/power-automate/estados')->json('eventos'));

        $this->withToken('token-de-prueba')->postJson('/api/v1/power-automate/estados/confirmar', ['ids' => [$e->evento_id, 'LG-999999']])
            ->assertOk()->assertJson(['confirmados' => 1]);
        $this->assertSame('confirmado', $e->refresh()->estado);
        $this->assertNotNull($e->confirmado_en);
    }

    public function test_confirmar_exige_una_lista_de_ids(): void
    {
        $this->withToken('token-de-prueba')->postJson('/api/v1/power-automate/estados/confirmar', [])->assertStatus(422);
    }

    public function test_la_lectura_del_registro_de_actividad_de_sharepoint_confirma_los_eventos(): void
    {
        $e = $this->evento();
        app(ExportadorSharePoint::class)->exportar();
        $dir = sys_get_temp_dir().'/sync'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/Registro_Actividad_Expediente.csv', "ID,Actividad,Codigo_Expediente,FechaHora_Actividad,Actor,Origen,Tipo_Actividad,Descripcion,Documento_Referencia,Planner_Task_ID,Estado_Anterior,Estado_Nuevo,ID_Evento\n"
            ."1,Documento generado,2026-001,2026-10-03,x,Nortia,Documento,,,,,En curso,{$e->evento_id}\n2,Otra cosa,2026-001,2026-10-03,x,Manual,Nota,,,,,,\n");

        app(\App\Support\Import\SincronizacionWordVivo::class)->sincronizar($dir);

        $this->assertSame('confirmado', $e->refresh()->estado);
    }

    public function test_la_pantalla_de_sincronizacion_lista_los_eventos_y_avisa_de_los_que_no_se_reflejan(): void
    {
        $nuevo = $this->evento();
        $viejo = $this->evento('PROPUESTA_CREADA');
        $viejo->forceFill(['estado' => 'exportado', 'exportado_en' => now()->subHours(5)])->save();

        $this->actingAs($this->worker)->get('/panel/sincronizacion')->assertOk()
            ->assertSee($nuevo->evento_id)->assertSee($viejo->evento_id)->assertSee('sin confirmar');
    }

    public function test_el_boton_exportar_ahora_funciona_y_un_cliente_no_puede_usarlo(): void
    {
        $this->evento();
        $this->actingAs($this->worker)->post('/panel/sincronizacion/exportar')->assertRedirect()->assertSessionHas('success');
        $this->assertNotEmpty(glob($this->salida.'/*.json'));

        $c = User::factory()->create();
        $c->assignRole('client');
        $this->actingAs($c)->post('/panel/sincronizacion/exportar')->assertForbidden();
        $this->actingAs($c)->get('/panel/sincronizacion')->assertForbidden();
    }

    public function test_mi_jornada_cuenta_los_cambios_que_esperan_a_sharepoint(): void
    {
        $this->evento();

        $this->actingAs($this->worker)->get('/panel/dashboard')->assertOk()->assertSee('pendientes de llegar a SharePoint');
    }

    public function test_el_puente_registra_su_estado_de_confirmacion_solo_hacia_adelante(): void
    {
        $e = $this->evento();
        PuenteSharePoint::confirmar([$e->evento_id]);
        PuenteSharePoint::marcarExportados(collect([$e->refresh()]), 'otro.json');

        $this->assertSame('confirmado', $e->refresh()->estado);
    }
}
