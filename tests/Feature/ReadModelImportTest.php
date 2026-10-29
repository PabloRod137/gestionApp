<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Community;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Person;
use App\Models\Service;
use App\Support\Import\ReadModelImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsReadModel;
use Tests\TestCase;

/**
 * El importador se prueba con una base de lectura INVENTADA (mismo esquema que la del despacho),
 * nunca con datos reales de clientes.
 */
class ReadModelImportTest extends TestCase
{
    use RefreshDatabase, BuildsReadModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildReadModel();
    }

    protected function tearDown(): void
    {
        $this->dropReadModel();
        parent::tearDown();
    }

    public function test_importa_el_modelo_completo_y_avisa_de_lo_que_no_encaja(): void
    {
        $r = (new ReadModelImporter)->importar($this->path);

        $this->assertSame(3, Client::count());
        $this->assertSame(2, Service::count());
        $this->assertSame(3, Expedient::count());
        $this->assertSame(1, Community::count());
        $this->assertSame(1, Person::count());
        $this->assertSame(1, DocumentTemplate::count());
        $this->assertSame(1, ExpedientDocument::count());
        $this->assertSame(0, $r['avisos_graves']);

        // un cliente sin NIF se importa igualmente (en la fuente el 65% no lo tiene)
        $this->assertNull(Client::where('codigo', '2020-002')->first()->nif);
        // el tipo se normaliza
        $this->assertSame('autonomo', Client::where('codigo', '2020-002')->first()->tipo);
        // un expediente sin servicio es válido
        $this->assertNull(Expedient::where('codigo', '2026-002')->first()->service_id);
        // estados normalizados
        $this->assertSame('en_proceso', Expedient::where('codigo', '2026-001')->first()->estado);
        $this->assertSame('abierto', Expedient::where('codigo', '2026-002')->first()->estado);
        $this->assertSame('finalizado', Expedient::where('codigo', '2026-003')->first()->estado);
        // IVA como porcentaje (la fuente lo guarda como 0.21)
        $this->assertEquals(21, Service::where('codigo', 'BUROFAX')->first()->iva);
    }

    public function test_los_requisitos_sin_normalizar_no_se_dan_por_opcionales(): void
    {
        (new ReadModelImporter)->importar($this->path);

        $pendiente = Service::where('codigo', 'BAJA_AUT')->first()->requirements()->first();
        $this->assertNull($pendiente->obligatorio);
        $this->assertFalse($pendiente->normalizado);
        $this->assertTrue((bool) Service::where('codigo', 'BUROFAX')->first()->requirements()->first()->obligatorio);
    }

    public function test_importar_dos_veces_no_duplica(): void
    {
        $importer = new ReadModelImporter;
        $importer->importar($this->path);
        $importer->importar($this->path);

        $this->assertSame(3, Client::count());
        $this->assertSame(3, Expedient::count());
        $this->assertSame(1, Person::count());
        $this->assertSame(1, ExpedientDocument::count());
    }

    public function test_el_documento_enlazado_a_sharepoint_se_conserva_como_enlace_externo(): void
    {
        (new ReadModelImporter)->importar($this->path);

        $doc = ExpedientDocument::first();
        $this->assertSame('https://sp.example/doc1', $doc->url_externa);
        $this->assertSame('PRESUPUESTO', $doc->tipo_documento);
        $this->assertSame(Expedient::where('codigo', '2026-001')->first()->id, $doc->expedient_id);
    }
}
