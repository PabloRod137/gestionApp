<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\Proposal;
use App\Models\Service;
use App\Models\User;
use App\Support\PlanActuaciones;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Propuestas automáticas por reglas: del servicio del expediente salen presupuesto, documento y factura. No genera ni envía nada. */
class PlanActuacionesTest extends TestCase
{
    use RefreshDatabase;

    private Expedient $exp;
    private Service $servicio;
    private DocumentTemplate $certificado;
    private DocumentTemplate $presupuesto;
    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(RolesSeeder::class);
        config(['recetas.modelo_presupuesto' => 'PRESUP']);
        Storage::put('p/cert.docx', 'x');
        Storage::put('p/presup.docx', 'x');
        $this->certificado = DocumentTemplate::create(['codigo' => 'CERT', 'nombre' => 'Certificado de deuda', 'tipo_documento' => 'CERTIFICADO', 'archivo' => 'p/cert.docx']);
        $this->presupuesto = DocumentTemplate::create(['codigo' => 'PRESUP', 'nombre' => 'Presupuesto', 'tipo_documento' => 'ECONOMICO', 'archivo' => 'p/presup.docx']);
        $this->servicio = Service::create(['codigo' => 'CERT_DEUDA', 'nombre' => 'Certificado de deuda', 'precio_base' => 30, 'iva' => 21, 'tipo' => 'sin_cita', 'facturable' => true, 'genera_documento' => true, 'tipo_servicio' => 'Facturable', 'tipo_cobro' => 'Puntual']);
        $this->servicio->templates()->attach($this->certificado->id);
        $client = Client::create(['codigo' => 'C-1', 'razon_social' => 'Comunidad Demo', 'tipo' => 'comunidad']);
        $this->exp = Expedient::create(['codigo' => '2026-100', 'client_id' => $client->id, 'service_id' => $this->servicio->id, 'titulo' => 'CERTIFICADO AL CORRIENTE', 'estado' => 'abierto']);
        $this->worker = User::factory()->create();
        $this->worker->assignRole('worker');
    }

    private function plan(): PlanActuaciones
    {
        return app(PlanActuaciones::class);
    }

    public function test_un_servicio_facturable_con_modelo_propone_presupuesto_documento_y_factura_en_orden(): void
    {
        $pasos = $this->plan()->pasos($this->exp);

        $this->assertSame(['presupuesto', 'documento', 'factura'], array_column($pasos, 'tipo'));
        $this->assertSame($this->presupuesto->id, $pasos[0]['modelo']->id);
        $this->assertSame($this->certificado->id, $pasos[1]['modelo']->id);
        $this->assertNull($pasos[2]['modelo']);
        $this->assertStringContainsString('30,00 € + 21 % IVA = 36,30 €', $pasos[0]['finalidad']);
    }

    public function test_sin_servicio_no_propone_nada(): void
    {
        $this->exp->update(['service_id' => null]);

        $this->assertSame([], $this->plan()->pasos($this->exp->refresh()));
    }

    public function test_un_servicio_incluido_en_cuota_no_presupuesta_ni_factura(): void
    {
        $this->servicio->update(['tipo_servicio' => 'Incluido en cuota', 'facturable' => false]);

        $this->assertSame(['documento'], array_column($this->plan()->pasos($this->exp->refresh()), 'tipo'));
    }

    public function test_un_modelo_sin_archivo_word_no_se_propone_y_se_explica(): void
    {
        $this->certificado->update(['archivo' => null]);

        $paso = collect($this->plan()->pasos($this->exp))->firstWhere('tipo', 'documento');

        $this->assertSame('sin_modelo', $paso['estado']);
        $this->assertNull($paso['modelo']);
    }

    public function test_crear_deja_las_propuestas_pendientes_de_aprobar_sin_generar_nada_y_sin_duplicar(): void
    {
        $n = $this->plan()->crear($this->exp, $this->worker);

        $this->assertSame(2, $n);
        $this->assertSame(2, Proposal::count());
        $this->assertSame(['proposed'], Proposal::pluck('status')->unique()->values()->all());
        $this->assertNull(Proposal::first()->generated_document_id);
        $this->assertSame($this->worker->id, Proposal::first()->created_by);

        $this->assertSame(0, $this->plan()->crear($this->exp, $this->worker));
        $this->assertSame(2, Proposal::count());
        $estados = array_column($this->plan()->pasos($this->exp), 'estado', 'tipo');
        $this->assertSame('propuesta', $estados['presupuesto']);
        $this->assertSame('propuesta', $estados['documento']);
    }

    public function test_una_propuesta_rechazada_se_puede_volver_a_proponer(): void
    {
        $this->plan()->crear($this->exp, $this->worker);
        Proposal::query()->update(['status' => 'rejected']);

        $this->assertSame(2, $this->plan()->crear($this->exp, $this->worker));
    }

    public function test_la_tarea_de_modelos_asigna_los_de_la_receta_sin_duplicar(): void
    {
        config(['recetas.modelos_por_servicio' => ['CERT_DEUDA' => ['PRESUP', 'NO_EXISTE']]]);

        $this->artisan('despacho:recetas')->assertSuccessful();
        $this->artisan('despacho:recetas')->assertSuccessful();

        $this->assertSame(2, $this->servicio->templates()->count());
    }
}
