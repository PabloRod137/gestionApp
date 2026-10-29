<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\Proposal;
use App\Models\Service;
use App\Models\User;
use App\Support\SituacionExpediente;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Tengo / Falta / Puedo generar / Siguiente actuación": la situación de un expediente de un vistazo. */
class ExpedienteCockpitTest extends TestCase
{
    use RefreshDatabase;

    private Client $cliente;
    private Service $servicio;
    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->cliente = Client::create(['codigo' => 'C-1', 'razon_social' => 'Cliente Uno', 'tipo' => 'empresa', 'email' => 'uno@example.test']);
        $this->servicio = Service::create(['codigo' => 'S', 'nombre' => 'Servicio', 'precio_base' => 0, 'tipo' => 'sin_cita', 'genera_documento' => true]);
        $this->trabajador = User::factory()->create();
        $this->trabajador->assignRole('worker');
    }

    private function exp(array $over = []): Expedient
    {
        return Expedient::create(array_merge(['codigo' => '2026-001', 'client_id' => $this->cliente->id, 'service_id' => $this->servicio->id, 'titulo' => 'Asunto', 'estado' => 'abierto'], $over));
    }

    private function plantilla(string $tipo, bool $conArchivo = true): DocumentTemplate
    {
        $t = DocumentTemplate::create(['codigo' => 'T-'.$tipo, 'nombre' => 'Modelo '.$tipo, 'tipo_documento' => $tipo, 'archivo' => $conArchivo ? 'plantillas/x.docx' : null]);
        $this->servicio->templates()->attach($t->id);

        return $t;
    }

    public function test_la_siguiente_actuacion_sigue_el_estado_del_expediente(): void
    {
        $sin = $this->exp(['service_id' => null]);
        $this->assertStringContainsString('servicio', mb_strtolower(SituacionExpediente::para($sin)['siguiente']['titulo']));

        $e = $this->exp(['codigo' => '2026-002']);
        $this->assertStringContainsString('modelo', mb_strtolower(SituacionExpediente::para($e)['siguiente']['titulo'])); // el servicio genera documento pero no hay modelo

        $this->plantilla('CERTIFICADO');
        $this->assertStringContainsString('propuesta', mb_strtolower(SituacionExpediente::para($e->fresh())['siguiente']['titulo']));

        $p = Proposal::create(['expedient_id' => $e->id, 'title' => 'x', 'purpose' => 'y', 'status' => 'proposed']);
        $this->assertStringContainsString('aprobar', mb_strtolower(SituacionExpediente::para($e->fresh())['siguiente']['titulo']));
        $p->update(['status' => 'approved']);
        $this->assertStringContainsString('generar', mb_strtolower(SituacionExpediente::para($e->fresh())['siguiente']['titulo']));
        $p->update(['status' => 'generated']);
        $this->assertStringContainsString('pdf', mb_strtolower(SituacionExpediente::para($e->fresh())['siguiente']['titulo']));
        $p->update(['status' => 'document_approved']);
        $this->assertStringContainsString('comunicación', mb_strtolower(SituacionExpediente::para($e->fresh())['siguiente']['titulo']));
    }

    public function test_falta_lo_que_no_consta_en_la_ficha(): void
    {
        $s = SituacionExpediente::para($this->exp());

        $faltan = array_column($s['falta'], 'texto');
        $this->assertContains('NIF / CIF del cliente', $faltan);
        $this->assertContains('Teléfono del cliente', $faltan);
        $this->assertNotContains('Correo del cliente', $faltan);
    }

    public function test_puedo_generar_lista_los_modelos_con_archivo(): void
    {
        $this->plantilla('CERTIFICADO');
        $this->plantilla('RECLAMACION', false);

        $nombres = array_column(SituacionExpediente::para($this->exp())['generar'], 'texto');

        $this->assertContains('Modelo CERTIFICADO', $nombres);
        $this->assertNotContains('Modelo RECLAMACION', $nombres);
    }

    public function test_los_botones_de_accion_se_activan_segun_los_modelos_del_servicio(): void
    {
        $this->plantilla('RECLAMACION');
        $acciones = collect(SituacionExpediente::para($this->exp())['acciones'])->keyBy('clave');

        $this->assertTrue($acciones['reclamar']['activa']);
        $this->assertFalse($acciones['certificado']['activa']);
        $this->assertStringContainsString('modelo', mb_strtolower($acciones['certificado']['nota']));
        $this->assertTrue($acciones['comunicacion']['activa']);
        $this->assertFalse($acciones['informe']['activa']); // el informe mensual todavía no está conectado
    }

    public function test_la_ficha_muestra_la_situacion_y_las_acciones(): void
    {
        $e = $this->exp();

        $this->actingAs($this->trabajador)->get("/panel/expedientes/{$e->id}")->assertOk()
            ->assertSee('Tengo')->assertSee('Falta')->assertSee('Puedo generar')->assertSee('Siguiente actuación')
            ->assertSee('Reclamar deuda')->assertSee('Preparar comunicación');
    }

    public function test_el_expediente_activo_se_puede_limpiar(): void
    {
        $e = $this->exp();
        $this->actingAs($this->trabajador)->get("/panel/expedientes/{$e->id}")->assertOk();
        $this->get('/panel/dashboard')->assertSee('Expediente activo')->assertSee('Limpiar');

        $this->post('/panel/expediente-activo/limpiar')->assertRedirect();

        $this->get('/panel/dashboard')->assertOk()->assertDontSee('Expediente activo');
    }

    public function test_el_expediente_activo_se_recuerda_en_la_barra_superior(): void
    {
        $e = $this->exp();
        $this->actingAs($this->trabajador)->get("/panel/expedientes/{$e->id}")->assertOk();

        $this->get('/panel/dashboard')->assertOk()->assertSee('Expediente activo')->assertSee('2026-001');
    }
}
