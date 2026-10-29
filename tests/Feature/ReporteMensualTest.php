<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Community;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Proposal;
use App\Models\ReporteMensual as Reporte;
use App\Models\Service;
use App\Models\User;
use App\Support\Comunidades\ReporteMensual;
use App\Support\GeneradorDocumentos;
use App\Support\SituacionExpediente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Reporte mensual de comunidades: cifras y textos confirmados por una persona que rellenan el modelo Word. */
class ReporteMensualTest extends TestCase
{
    use RefreshDatabase;

    private User $worker;
    private Client $cliente;
    private Community $comunidad;
    private Expedient $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00', 'Europe/Madrid'));
        $this->seed(RolesSeeder::class);
        $this->worker = User::factory()->create();
        $this->worker->assignRole('worker');
        $this->cliente = Client::create(['codigo' => 'COM-1', 'razon_social' => 'Comunidad Ejemplo 1', 'tipo' => 'comunidad']);
        $this->comunidad = Community::create(['client_id' => $this->cliente->id, 'nombre' => 'Comunidad Ejemplo 1']);
        $servicio = Service::create(['codigo' => 'ADM_COM_BAS', 'nombre' => 'Administración comunidad mensual - Básica', 'precio_base' => 100, 'tipo' => 'sin_cita', 'facturable' => true]);
        $this->expediente = Expedient::create(['codigo' => '2026-300', 'client_id' => $this->cliente->id, 'service_id' => $servicio->id, 'titulo' => 'ADMINISTRACIÓN', 'estado' => 'abierto']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_el_periodo_por_defecto_es_el_mes_anterior_y_se_escribe_en_castellano(): void
    {
        $this->assertSame('2026-09', ReporteMensual::periodoPorDefecto());
        $this->assertSame('septiembre de 2026', ReporteMensual::etiqueta('2026-09'));
        $this->assertSame('2025-12', ReporteMensual::periodoPorDefecto(Carbon::parse('2026-01-15')));
        $this->assertTrue(ReporteMensual::periodoValido('2026-09'));
        $this->assertFalse(ReporteMensual::periodoValido('2026-13'));
    }

    public function test_los_importes_se_entienden_en_formato_espanol_o_normal(): void
    {
        foreach (['1.234,56' => 1234.56, '1234,56' => 1234.56, '1234.56' => 1234.56, '1.234.567' => 1234567.0, ' 0,5 € ' => 0.5, '-20,00' => -20.0] as $texto => $n) {
            $this->assertSame($n, ReporteMensual::numero($texto), $texto);
        }
        $this->assertNull(ReporteMensual::numero(''));
        $this->assertNull(ReporteMensual::numero('mucho'));
        $this->assertSame('1.234,50', ReporteMensual::moneda(1234.5));
    }

    public function test_sin_reporte_guardado_las_cifras_quedan_vacias_y_el_periodo_lo_pone_el_programa(): void
    {
        $v = ReporteMensual::valores($this->expediente);

        $this->assertSame('septiembre de 2026', $v['periodo']);
        $this->assertNull($v['ingresos']);
        $this->assertNull($v['incidencias']);
    }

    public function test_un_expediente_que_no_es_de_comunidad_no_recibe_valores_del_reporte(): void
    {
        $otro = Client::create(['codigo' => 'P-1', 'razon_social' => 'Persona', 'tipo' => 'particular']);
        $e = Expedient::create(['codigo' => '2026-301', 'client_id' => $otro->id, 'titulo' => 'X', 'estado' => 'abierto']);

        $this->assertSame(array_fill_keys(array_keys(ReporteMensual::CAMPOS), null), ReporteMensual::valores($e));
    }

    public function test_guardar_el_reporte_rellena_las_marcas_del_modelo(): void
    {
        $r = $this->actingAs($this->worker)->post("/panel/comunidades/{$this->comunidad->id}/reporte", [
            'periodo' => '2026-09', 'ingresos' => '4.200,00', 'gastos' => '3.150,25', 'saldo' => '1.049,75', 'cuotas_pendientes' => '380,00',
            'incidencias' => 'Reparación del portero automático.', 'proximas_actuaciones' => 'Junta ordinaria en noviembre.',
        ]);

        $r->assertRedirect(route('panel.comunidades.reporte', [$this->comunidad, 'periodo' => '2026-09']));
        $v = app(GeneradorDocumentos::class)->valores($this->expediente->fresh());
        $this->assertSame(['septiembre de 2026', '4.200,00', '3.150,25', '1.049,75', '380,00'], [$v['periodo'], $v['ingresos'], $v['gastos'], $v['saldo'], $v['cuotas_pendientes']]);
        $this->assertSame('Reparación del portero automático.', $v['incidencias']);
        $this->assertSame(1, Reporte::count());
    }

    public function test_guardar_otra_vez_el_mismo_mes_actualiza_y_no_duplica(): void
    {
        $datos = ['periodo' => '2026-09', 'ingresos' => '100', 'gastos' => '40'];
        $this->actingAs($this->worker)->post("/panel/comunidades/{$this->comunidad->id}/reporte", $datos);
        $this->actingAs($this->worker)->post("/panel/comunidades/{$this->comunidad->id}/reporte", ['ingresos' => '250'] + $datos);

        $this->assertSame(1, Reporte::count());
        $this->assertSame('250.00', Reporte::first()->ingresos);
    }

    public function test_un_importe_ilegible_o_un_periodo_malo_no_se_guardan(): void
    {
        $this->actingAs($this->worker)->post("/panel/comunidades/{$this->comunidad->id}/reporte", ['periodo' => '2026-09', 'ingresos' => 'mucho'])->assertSessionHasErrors('ingresos');
        $this->actingAs($this->worker)->post("/panel/comunidades/{$this->comunidad->id}/reporte", ['periodo' => '2026-99'])->assertSessionHasErrors('periodo');
        $this->assertSame(0, Reporte::count());
    }

    public function test_el_formulario_propone_textos_con_lo_registrado_y_el_saldo_anterior(): void
    {
        $doc = ExpedientDocument::create(['expedient_id' => $this->expediente->id, 'client_id' => $this->cliente->id, 'nombre' => 'Acta de la junta.pdf', 'ruta' => 'x/acta.pdf', 'tipo_mime' => 'application/pdf']);
        $doc->forceFill(['created_at' => '2026-09-10 09:00:00'])->saveQuietly();
        Appointment::create(['client_id' => $this->cliente->id, 'service_id' => $this->expediente->service_id, 'fecha' => '2026-11-05', 'hora' => '10:00:00', 'estado' => 'confirmada', 'notas' => 'Junta ordinaria']);
        Reporte::create(['community_id' => $this->comunidad->id, 'periodo' => '2026-08', 'saldo' => 500]);

        $s = ReporteMensual::sugerencias($this->comunidad, '2026-09');
        $this->assertStringContainsString('10/09/2026: documento «Acta de la junta.pdf»', $s['incidencias']);
        $this->assertStringContainsString('05/11/2026: cita — Junta ordinaria', $s['proximas_actuaciones']);
        $this->assertSame(500.0, $s['saldo_previsto']);

        $this->actingAs($this->worker)->get("/panel/comunidades/{$this->comunidad->id}/reporte")->assertOk()
            ->assertSee('Reporte mensual')->assertSee('Acta de la junta.pdf')->assertSee('500,00');
    }

    public function test_preparar_crea_la_propuesta_en_el_expediente_de_administracion(): void
    {
        DocumentTemplate::create(['codigo' => ReporteMensual::MODELO, 'nombre' => 'Reporte mensual', 'tipo_documento' => 'COMUNICACION', 'activo' => true]);
        $this->actingAs($this->worker)->post("/panel/comunidades/{$this->comunidad->id}/reporte", ['periodo' => '2026-09', 'ingresos' => '10']);

        $r = $this->actingAs($this->worker)->post("/panel/comunidades/{$this->comunidad->id}/reporte/preparar", ['periodo' => '2026-09']);

        $r->assertRedirect(route('panel.expedientes.show', $this->expediente->id));
        $p = Proposal::firstOrFail();
        $this->assertSame($this->expediente->id, $p->expedient_id);
        $this->assertSame('Reporte mensual septiembre de 2026', $p->title);
    }

    public function test_sin_expediente_de_administracion_o_sin_modelo_avisa_y_no_crea_nada(): void
    {
        $this->actingAs($this->worker)->post("/panel/comunidades/{$this->comunidad->id}/reporte/preparar", ['periodo' => '2026-09'])->assertSessionHas('error');
        $this->assertSame(0, Proposal::count());
    }

    public function test_la_accion_informe_mensual_de_la_ficha_se_activa_solo_en_comunidades(): void
    {
        $acciones = collect(SituacionExpediente::para($this->expediente)['acciones'])->firstWhere('clave', 'informe');
        $this->assertTrue($acciones['activa']);
        $this->assertSame(route('panel.comunidades.reporte', $this->comunidad->id), $acciones['href']);

        $otro = Client::create(['codigo' => 'P-2', 'razon_social' => 'Persona 2', 'tipo' => 'particular']);
        $e = Expedient::create(['codigo' => '2026-302', 'client_id' => $otro->id, 'titulo' => 'X', 'estado' => 'abierto']);
        $this->assertFalse(collect(SituacionExpediente::para($e)['acciones'])->firstWhere('clave', 'informe')['activa']);
    }

    public function test_la_ficha_de_la_comunidad_enlaza_al_reporte(): void
    {
        $this->actingAs($this->worker)->get("/panel/comunidades/{$this->comunidad->id}")->assertOk()->assertSee('Reporte mensual');
    }
}
