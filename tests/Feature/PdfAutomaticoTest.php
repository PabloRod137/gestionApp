<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Proposal;
use App\Models\Service;
use App\Models\User;
use App\Support\Pdf\LibreOfficeConversor;
use App\Support\Pdf\PdfException;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\FakeConversorPdf;
use Tests\TestCase;

/** Al generar un documento se crean a la vez el Word y su PDF: nadie tiene que convertirlo a mano. */
class PdfAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    private Expedient $exp;
    private DocumentTemplate $plantilla;
    private User $worker;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(RolesSeeder::class);
        $client = Client::create(['codigo' => 'C-1', 'razon_social' => 'Cliente Uno SL', 'tipo' => 'empresa', 'email' => 'uno@example.test', 'nif' => 'B00000001', 'telefono' => '600000001', 'direccion' => 'Calle Uno 1']);
        $service = Service::create(['codigo' => 'S', 'nombre' => 'Servicio', 'precio_base' => 10, 'tipo' => 'sin_cita', 'genera_documento' => true]);
        $this->exp = Expedient::create(['codigo' => '2026-001', 'client_id' => $client->id, 'service_id' => $service->id, 'titulo' => 'Asunto', 'estado' => 'abierto']);
        $w = new PhpWord;
        $w->addSection()->addText('Estimado ${cliente_nombre}');
        $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        IOFactory::createWriter($w, 'Word2007')->save($tmp);
        Storage::put('plantillas/P.docx', file_get_contents($tmp));
        $this->plantilla = DocumentTemplate::create(['codigo' => 'P', 'nombre' => 'Certificado', 'tipo_documento' => 'CERTIFICADO', 'archivo' => 'plantillas/P.docx']);
        $service->templates()->attach($this->plantilla->id);
        $this->worker = $this->usuario('worker');
        $this->admin = $this->usuario('admin');
    }

    private function usuario(string $rol): User
    {
        $u = User::factory()->create();
        $u->assignRole($rol);

        return $u;
    }

    private function propuestaAprobada(): Proposal
    {
        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/propuestas", ['title' => 'Certificado', 'purpose' => 'Emitir', 'document_template_id' => $this->plantilla->id])->assertSessionHasNoErrors();
        $p = Proposal::firstOrFail();
        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/aprobar", ['version' => $p->version]);

        return $p->refresh();
    }

    private function generar(Proposal $p)
    {
        return $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/generar", ['version' => $p->version, 'forzar' => 1]);
    }

    public function test_al_generar_una_propuesta_se_crean_el_word_y_el_pdf_con_sus_huellas(): void
    {
        $p = $this->propuestaAprobada();

        $this->generar($p)->assertSessionHas('success')->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('generated', $p->status);
        $this->assertNotNull($p->generated_document_id);
        $this->assertNotNull($p->pdf_document_id);
        $pdf = $p->pdfDocument;
        $this->assertSame('application/pdf', $pdf->tipo_mime);
        $this->assertStringEndsWith('.pdf', $pdf->nombre);
        $this->assertSame(hash('sha256', Storage::get($pdf->ruta)), $p->pdf_sha256);
        $this->assertSame('PDF_AUTOMATICO', $pdf->origen);
        $this->assertNotNull($p->document_key);
    }

    public function test_el_pdf_se_hace_a_partir_del_word_recien_generado(): void
    {
        $p = $this->propuestaAprobada();

        $this->generar($p);

        $this->assertCount(1, FakeConversorPdf::$recibidos);
        $this->assertSame(Storage::get($p->refresh()->generatedDocument->ruta), FakeConversorPdf::$recibidos[0]);
    }

    public function test_se_puede_aprobar_el_documento_sin_subir_ningun_pdf(): void
    {
        $p = $this->propuestaAprobada();
        $this->generar($p);
        $p->refresh();

        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/revisar", ['version' => $p->version, 'confirmado' => 1])->assertSessionHas('success');

        $this->assertSame('document_approved', $p->refresh()->status);
    }

    public function test_si_la_conversion_falla_el_word_se_genera_y_se_avisa_de_que_hay_que_subir_el_pdf(): void
    {
        FakeConversorPdf::$fallar = true;
        $p = $this->propuestaAprobada();

        $r = $this->generar($p);

        $r->assertSessionHas('success');
        $this->assertStringContainsString('PDF', session('success'));
        $p->refresh();
        $this->assertSame('generated', $p->status);
        $this->assertNotNull($p->generated_document_id);
        $this->assertNull($p->pdf_document_id);
        $this->assertTrue(CaseActivity::where('action', 'PDF_NO_GENERADO')->where('proposal_id', $p->id)->exists());
        // sin PDF no se puede aprobar: hay que subirlo a mano, como antes
        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/revisar", ['version' => $p->version, 'confirmado' => 1])->assertSessionHasErrors();
    }

    public function test_un_pdf_corregido_a_mano_sigue_pudiendo_sustituir_al_automatico(): void
    {
        $p = $this->propuestaAprobada();
        $this->generar($p);
        $p->refresh();
        $ruta = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj\n<< >>\nendobj\ntrailer\n<< >>\n%%EOF\n");

        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/pdf", ['version' => $p->version, 'pdf' => new UploadedFile($ruta, 'corregido.pdf', 'application/pdf', null, true)])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('PDF_REVISADO', $p->pdfDocument->origen);
        $this->assertSame(1, ExpedientDocument::where('tipo_mime', 'application/pdf')->count());
    }

    public function test_la_pantalla_ofrece_el_word_y_el_pdf_sin_pedir_que_se_convierta(): void
    {
        $p = $this->propuestaAprobada();
        $this->generar($p);

        $this->actingAs($this->worker)->get("/panel/propuestas/{$p->id}")->assertOk()
            ->assertSee('Descargar Word')->assertSee('Descargar PDF')
            ->assertDontSee('expórtalo a PDF desde Word');
    }

    public function test_si_falta_el_pdf_la_pantalla_lo_explica(): void
    {
        FakeConversorPdf::$fallar = true;
        $p = $this->propuestaAprobada();
        $this->generar($p);

        $this->actingAs($this->worker)->get("/panel/propuestas/{$p->id}")->assertOk()->assertSee('No se pudo crear el PDF automáticamente');
    }

    public function test_generar_directamente_desde_el_expediente_tambien_deja_word_y_pdf(): void
    {
        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/generar", ['document_template_id' => $this->plantilla->id, 'forzar' => 1])->assertSessionHas('success');

        $docs = ExpedientDocument::where('expedient_id', $this->exp->id)->get();
        $this->assertCount(2, $docs);
        $this->assertEqualsCanonicalizing(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $docs->pluck('tipo_mime')->all());
        $this->assertStringContainsString('Word y PDF', session('success'));
    }

    public function test_si_la_conversion_directa_falla_queda_el_word_y_se_avisa(): void
    {
        FakeConversorPdf::$fallar = true;

        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/generar", ['document_template_id' => $this->plantilla->id, 'forzar' => 1])->assertSessionHas('success');

        $this->assertSame(1, ExpedientDocument::where('expedient_id', $this->exp->id)->count());
        $this->assertStringContainsString('PDF', session('success'));
    }

    public function test_sin_libreoffice_instalado_el_error_es_claro(): void
    {
        config(['documentos.libreoffice' => '/no/existe/soffice']);

        $this->expectException(PdfException::class);
        $this->expectExceptionMessage('LibreOffice');
        (new LibreOfficeConversor)->convertir('contenido');
    }

    public function test_con_libreoffice_real_un_word_se_convierte_en_un_pdf_valido(): void
    {
        $conversor = new LibreOfficeConversor;
        if (! $conversor->disponible()) {
            $this->markTestSkipped('LibreOffice no está instalado en esta máquina.');
        }
        $w = new PhpWord;
        $w->addSection()->addText('Hola Mundo desde una prueba');
        $tmp = tempnam(sys_get_temp_dir(), 'doc').'.docx';
        IOFactory::createWriter($w, 'Word2007')->save($tmp);

        $pdf = $conversor->convertir(file_get_contents($tmp), 'prueba');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', substr($pdf, -1024));
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_libreoffice_se_lanza_con_un_entorno_minimo_sin_las_claves_de_la_aplicacion(): void
    {
        putenv('LG_CLAVE_DE_PRUEBA=secreto-inventado');
        putenv('STRIPE_SECRET_KEY=sk_test_inventada');
        try {
            $metodo = new \ReflectionMethod(LibreOfficeConversor::class, 'entornoMinimo');
            $entorno = $metodo->invoke(new LibreOfficeConversor, '/tmp/carpeta-de-prueba');
        } finally {
            putenv('LG_CLAVE_DE_PRUEBA');
            putenv('STRIPE_SECRET_KEY');
        }

        $this->assertSame('/tmp/carpeta-de-prueba', $entorno['HOME']);
        $this->assertArrayNotHasKey('LG_CLAVE_DE_PRUEBA', $entorno);
        $this->assertArrayNotHasKey('STRIPE_SECRET_KEY', $entorno);
        $this->assertNotEmpty(array_intersect_key($entorno, array_flip(['PATH', 'Path'])), 'Sin PATH LibreOffice no arranca');
    }

    public function test_con_libreoffice_real_la_conversion_funciona_aunque_el_entorno_este_lleno_de_variables_de_la_aplicacion(): void
    {
        $conversor = new LibreOfficeConversor;
        if (! $conversor->disponible()) {
            $this->markTestSkipped('LibreOffice no está instalado en esta máquina.');
        }
        foreach (['APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'AWS_SECRET_ACCESS_KEY', 'REDIS_PASSWORD', 'WWWUSER'] as $n) {
            putenv($n.'=valor-inventado-para-llenar-el-entorno');
        }
        try {
            $w = new PhpWord;
            $w->addSection()->addText('Prueba con el entorno lleno');
            $tmp = tempnam(sys_get_temp_dir(), 'doc').'.docx';
            IOFactory::createWriter($w, 'Word2007')->save($tmp);

            $pdf = $conversor->convertir(file_get_contents($tmp), 'prueba');
        } finally {
            foreach (['APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'AWS_SECRET_ACCESS_KEY', 'REDIS_PASSWORD', 'WWWUSER'] as $n) {
                putenv($n);
            }
        }

        $this->assertTrue(LibreOfficeConversor::esPdf($pdf));
    }
}
