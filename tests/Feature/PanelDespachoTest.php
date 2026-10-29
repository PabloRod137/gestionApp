<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Community;
use App\Models\Expedient;
use App\Models\Service;
use App\Models\User;
use App\Support\Import\ReadModelImporter;
use App\Support\PropuestaDocumental;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsReadModel;
use Tests\TestCase;

/** Pantallas del panel alimentadas con los datos del despacho (base de lectura inventada). */
class PanelDespachoTest extends TestCase
{
    use RefreshDatabase, BuildsReadModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->buildReadModel();
        (new ReadModelImporter)->importar($this->path);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        $this->dropReadModel();
        parent::tearDown();
    }

    public function test_las_pantallas_con_datos_del_despacho_cargan(): void
    {
        $service = Service::where('codigo', 'BUROFAX')->first();
        $community = Community::first();
        $exp = Expedient::where('codigo', '2026-001')->first();
        $sinServicio = Expedient::where('codigo', '2026-002')->first();

        foreach ([
            '/panel/servicios', "/panel/servicios/{$service->id}", '/panel/comunidades', "/panel/comunidades/{$community->id}",
            '/panel/plantillas', '/panel/expedientes', "/panel/expedientes/{$exp->id}", "/panel/expedientes/{$sinServicio->id}",
            '/panel/clientes', '/panel/clientes/'.Client::first()->id,
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_se_encuentra_un_expediente_por_numero_o_por_cliente(): void
    {
        $this->get('/panel/expedientes?search=2026-001')->assertOk()->assertSee('Reclamación')->assertDontSee('Sin servicio');
        $this->get('/panel/expedientes?search=Pepe')->assertOk()->assertSee('Sin servicio')->assertDontSee('Reclamación');
    }

    public function test_la_propuesta_separa_lo_que_aporta_el_cliente_de_lo_que_genera_el_despacho(): void
    {
        $p = PropuestaDocumental::para(Expedient::where('codigo', '2026-001')->first());

        $this->assertSame('propuesta', $p['estado']);
        $this->assertSame(['Factura o contrato'], $p['aportar']->pluck('descripcion')->all());
        $this->assertTrue($p['generar']->isEmpty()); // la relacion servicio→modelo no esta en origen
    }

    public function test_un_servicio_sin_requisitos_normalizados_muestra_el_texto_a_revisar_y_no_lo_da_por_opcional(): void
    {
        $exp = Expedient::where('codigo', '2026-003')->first();
        $p = PropuestaDocumental::para($exp);

        $this->assertTrue($p['aportar']->isEmpty());
        $this->assertSame('DNI, fecha de cese', $p['revisar']);
        $this->get("/panel/expedientes/{$exp->id}")->assertSee('revisar a mano');
    }

    public function test_un_expediente_sin_servicio_no_inventa_documentacion(): void
    {
        $exp = Expedient::where('codigo', '2026-002')->first();

        $this->assertSame('sin_servicio', PropuestaDocumental::para($exp)['estado']);
        $this->get("/panel/expedientes/{$exp->id}")->assertSee('no tiene servicio asignado');
    }

    public function test_el_documento_de_sharepoint_enlaza_fuera_y_no_a_la_descarga_local(): void
    {
        $exp = Expedient::where('codigo', '2026-001')->first();

        $this->get("/panel/expedientes/{$exp->id}")->assertSee('https://sp.example/doc1', false);
    }
}
