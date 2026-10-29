<?php

namespace Tests\Feature;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\CommunicationDraft;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Proposal;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Circuito de trabajo del despacho: propuesta -> aprobacion -> documento -> revision -> comunicacion preparada.
 * Nunca se envia nada: solo se deja preparada la solicitud de borrador.
 */
class ProposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Expedient $exp;
    private DocumentTemplate $plantilla;
    private User $admin;
    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(RolesSeeder::class);
        Setting::setValue('company_name', 'Despacho de prueba');

        $client = Client::create(['codigo' => 'C-1', 'razon_social' => 'Cliente Uno SL', 'nif' => 'B00000001', 'tipo' => 'empresa', 'email' => 'uno@example.test', 'direccion' => 'Calle Uno 1']);
        $service = Service::create(['codigo' => 'BUROFAX', 'nombre' => 'Burofax', 'precio_base' => 90, 'tipo' => 'sin_cita', 'genera_documento' => true]);
        $this->exp = Expedient::create(['codigo' => '2026-001', 'client_id' => $client->id, 'service_id' => $service->id, 'titulo' => 'Reclamación', 'estado' => 'abierto']);

        $word = new PhpWord;
        $word->addSection()->addText('Estimado ${cliente_nombre}. Importe ${importe}. Pie: ${despacho_nombre}, NIF ${despacho_nif}, ${despacho_direccion}, ${despacho_email}');
        $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($tmp);
        Storage::put('plantillas/PLT.docx', file_get_contents($tmp));
        $this->plantilla = DocumentTemplate::create(['codigo' => 'PLT', 'nombre' => 'Burofax', 'tipo_documento' => 'RECLAMACION', 'archivo' => 'plantillas/PLT.docx']);
        $service->templates()->attach($this->plantilla->id);
        Setting::setValue('company_nif', 'B22222222');
        Setting::setValue('company_address', 'Calle Despacho 2');
        Setting::setValue('company_email', 'despacho@example.test');

        $this->admin = $this->usuario('admin');
        $this->worker = $this->usuario('worker');
    }

    private function usuario(string $rol): User
    {
        $u = User::factory()->create();
        $u->assignRole($rol);

        return $u;
    }

    private function pdf(): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        return new UploadedFile($ruta, 'revisado.pdf', 'application/pdf', null, true);
    }

    private function propuesta(): Proposal
    {
        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/propuestas", [
            'title' => 'Burofax a Cliente', 'purpose' => 'Reclamar la deuda', 'document_template_id' => $this->plantilla->id,
        ])->assertSessionHasNoErrors();

        return Proposal::firstOrFail();
    }

    /** Lleva la propuesta hasta el documento generado con su PDF. */
    private function hastaDocumento(): Proposal
    {
        $p = $this->propuesta();
        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/aprobar", ['version' => $p->version]);
        $p->refresh();
        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/generar", ['version' => $p->version, 'extra' => ['importe' => '500 euros']])->assertSessionHasNoErrors();
        $p->refresh();
        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/pdf", ['version' => $p->version, 'pdf' => $this->pdf()])->assertSessionHasNoErrors();

        return $p->refresh();
    }

    public function test_el_personal_crea_una_propuesta_y_queda_registrada_la_actividad(): void
    {
        $p = $this->propuesta();

        $this->assertSame('proposed', $p->status);
        $this->assertSame(1, $p->version);
        $this->assertSame($this->worker->id, $p->created_by);
        $this->assertSame(1, $p->revisions()->count());
        $this->assertSame('PROPUESTA_CREADA', CaseActivity::where('expedient_id', $this->exp->id)->value('action'));
    }

    public function test_un_expediente_sin_servicio_tambien_admite_propuesta(): void
    {
        $this->exp->update(['service_id' => null]);

        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/propuestas", ['title' => 'x', 'purpose' => 'y', 'document_template_id' => $this->plantilla->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Proposal::count());
    }

    public function test_se_puede_proponer_un_modelo_que_no_esta_asignado_al_servicio(): void
    {
        $otra = DocumentTemplate::create(['codigo' => 'OTRA', 'nombre' => 'Otra']);

        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/propuestas", ['title' => 'x', 'purpose' => 'y', 'document_template_id' => $otra->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($otra->id, Proposal::firstOrFail()->document_template_id);
    }

    public function test_un_modelo_que_no_existe_se_rechaza(): void
    {
        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/propuestas", ['title' => 'x', 'purpose' => 'y', 'document_template_id' => 99999])
            ->assertSessionHasErrors('document_template_id');
        $this->assertSame(0, Proposal::count());
    }

    public function test_se_genera_con_datos_escritos_delante_del_cliente_y_se_guardan_en_la_ficha(): void
    {
        $this->exp->client->update(['telefono' => null]);
        $word = new PhpWord;
        $word->addSection()->addText('Tel ${cliente_telefono}');
        $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($tmp);
        Storage::put('plantillas/PLT.docx', file_get_contents($tmp));
        $p = $this->propuesta();
        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/aprobar", ['version' => $p->version]);
        $p->refresh();

        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/generar", ['version' => $p->version, 'forzar' => 1, 'guardar_ficha' => 1, 'extra' => ['cliente_telefono' => '600 111 222']])
            ->assertSessionHas('success');

        $p->refresh();
        $this->assertSame('generated', $p->status);
        $this->assertSame('600 111 222', $this->exp->client->refresh()->telefono);
        file_put_contents($tmp, Storage::get($p->generatedDocument->ruta));
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $this->assertStringContainsString('Tel 600 111 222', strip_tags($zip->getFromName('word/document.xml')));
        $this->assertSame('DATOS_FICHA_COMPLETADOS', CaseActivity::where('expedient_id', $this->exp->id)->where('action', 'DATOS_FICHA_COMPLETADOS')->value('action'));
    }

    public function test_corregir_con_una_version_vieja_no_pisa_el_trabajo_de_otro(): void
    {
        $p = $this->propuesta();
        $this->actingAs($this->worker)->patch("/panel/propuestas/{$p->id}", ['version' => 1, 'title' => 'Nuevo título', 'purpose' => 'Otra finalidad'])->assertSessionHasNoErrors();

        $this->actingAs($this->worker)->patch("/panel/propuestas/{$p->id}", ['version' => 1, 'title' => 'Pisado', 'purpose' => 'x'])->assertSessionHasErrors('workflow');
        $this->assertSame('Nuevo título', $p->refresh()->title);
        $this->assertSame(2, $p->version);
        $this->assertSame(2, $p->revisions()->count());
    }

    public function test_cualquier_persona_del_equipo_aprueba_y_un_cliente_no(): void
    {
        $p = $this->propuesta();

        $this->actingAs($this->usuario('client'))->post("/panel/propuestas/{$p->id}/aprobar", ['version' => 1])->assertForbidden();
        $this->assertSame('proposed', $p->refresh()->status);

        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/aprobar", ['version' => 1])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame('approved', $p->status);
        $this->assertSame($this->worker->id, $p->approved_by);
    }

    public function test_no_se_genera_un_documento_sin_aprobar_la_propuesta(): void
    {
        $p = $this->propuesta();

        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/generar", ['version' => 1, 'extra' => ['importe' => '1']])->assertSessionHasErrors('workflow');
        $this->assertSame(0, ExpedientDocument::count());
    }

    public function test_al_generar_se_piden_los_datos_que_no_estan_guardados(): void
    {
        $p = $this->propuesta();
        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/aprobar", ['version' => 1]);

        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/generar", ['version' => 2])->assertSessionHas('pedir', ['importe']);
        $this->assertSame('approved', $p->refresh()->status);
    }

    public function test_el_documento_generado_guarda_su_huella_y_pasa_a_revision(): void
    {
        $p = $this->hastaDocumento();

        $this->assertSame('generated', $p->status);
        $this->assertSame(hash('sha256', Storage::get($p->generatedDocument->ruta)), $p->document_sha256);
        $this->assertSame(hash('sha256', Storage::get($p->pdfDocument->ruta)), $p->pdf_sha256);
        $this->assertSame('PDF_REVISADO', $p->pdfDocument->origen);
    }

    public function test_un_archivo_que_no_es_pdf_se_rechaza(): void
    {
        $p = $this->propuesta();
        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/aprobar", ['version' => 1]);
        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/generar", ['version' => 2, 'extra' => ['importe' => '1']]);
        $falso = UploadedFile::fake()->createWithContent('malo.pdf', 'esto no es un pdf');

        $automatico = $p->refresh()->pdf_sha256;

        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/pdf", ['version' => 3, 'pdf' => $falso])->assertSessionHasErrors('workflow');
        // el PDF creado automáticamente sigue siendo el vigente
        $this->assertSame($automatico, $p->refresh()->pdf_sha256);
    }

    public function test_la_revision_del_documento_exige_confirmar_y_la_hace_el_equipo(): void
    {
        $p = $this->hastaDocumento();

        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/revisar", ['version' => $p->version])->assertSessionHasErrors('workflow');
        $this->actingAs($this->usuario('client'))->post("/panel/propuestas/{$p->id}/revisar", ['version' => $p->version, 'confirmado' => 1])->assertForbidden();
        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/revisar", ['version' => $p->version, 'confirmado' => 1])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('document_approved', $p->status);
        $this->assertSame($p->document_key, $p->approved_document_key);
        $this->assertSame($this->worker->id, $p->document_approved_by);
    }

    public function test_si_los_datos_cambian_tras_generar_hay_que_volver_a_generar(): void
    {
        $p = $this->hastaDocumento();
        $this->exp->client->update(['direccion' => 'Otra dirección distinta']);

        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/revisar", ['version' => $p->version, 'confirmado' => 1])->assertSessionHasErrors('workflow');
        $this->assertSame('generated', $p->refresh()->status);
    }

    public function test_si_un_adjunto_cambia_no_se_puede_aprobar(): void
    {
        $p = $this->hastaDocumento();
        Storage::put($p->pdfDocument->ruta, "%PDF-1.4\nOTRO CONTENIDO\n%%EOF\n");

        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/revisar", ['version' => $p->version, 'confirmado' => 1])->assertSessionHasErrors('workflow');
    }

    private function aprobada(): Proposal
    {
        $p = $this->hastaDocumento();
        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/revisar", ['version' => $p->version, 'confirmado' => 1]);

        return $p->refresh();
    }

    private function comunicacion(array $over = []): array
    {
        return array_merge(['recipient' => 'cliente@example.test', 'subject' => 'Documento', 'body' => 'Adjuntamos el documento.', 'confirmado' => 1], $over);
    }

    public function test_la_comunicacion_deja_una_solicitud_con_el_pdf_y_sin_permiso_de_envio(): void
    {
        $p = $this->aprobada();

        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/comunicacion", $this->comunicacion(['version' => $p->version]))->assertSessionHasNoErrors();

        $p->refresh();
        $d = CommunicationDraft::firstOrFail();
        $this->assertSame('communication_requested', $p->status);
        $this->assertFalse($d->manifest['send_permitted']);
        $this->assertSame('CREATE_OR_UPDATE_OUTLOOK_DRAFT', $d->manifest['command']);
        $this->assertSame([$p->pdf_sha256], array_column($d->manifest['attachments'], 'sha256'));
        $this->assertSame('pendiente_adaptador', $d->status);
    }

    public function test_la_comunicacion_exige_un_unico_correo_valido(): void
    {
        $p = $this->aprobada();

        foreach (['sin-arroba', 'a@b.com, c@d.com', ''] as $malo) {
            $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/comunicacion", $this->comunicacion(['version' => $p->version, 'recipient' => $malo]))->assertSessionHasErrors();
        }
        $this->assertSame(0, CommunicationDraft::count());
    }

    public function test_repetir_la_misma_solicitud_no_duplica_y_cambiarla_no_se_permite(): void
    {
        $p = $this->aprobada();
        $datos = $this->comunicacion(['version' => $p->version]);
        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/comunicacion", $datos);
        $p->refresh();

        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/comunicacion", array_merge($datos, ['version' => $p->version]))->assertSessionHasNoErrors();
        $this->assertSame(1, CommunicationDraft::count());

        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/comunicacion", array_merge($datos, ['version' => $p->version, 'subject' => 'Distinto']))->assertSessionHasErrors('workflow');
        $this->assertSame(1, CommunicationDraft::count());
    }

    public function test_se_rechaza_con_motivo_y_no_despues_de_preparar_la_comunicacion(): void
    {
        $p = $this->propuesta();
        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/rechazar", ['version' => 1, 'reason' => ''])->assertSessionHasErrors();
        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/rechazar", ['version' => 1, 'reason' => 'Ya no procede'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $p->refresh()->status);

        $this->exp->proposals()->delete();
        $q = $this->aprobada2();
        $this->actingAs($this->admin)->post("/panel/propuestas/{$q->id}/rechazar", ['version' => $q->version, 'reason' => 'tarde'])->assertSessionHasErrors('workflow');
    }

    private function aprobada2(): Proposal
    {
        $this->actingAs($this->worker)->post("/panel/expedientes/{$this->exp->id}/propuestas", ['title' => 'Otra', 'purpose' => 'p', 'document_template_id' => $this->plantilla->id]);
        $p = Proposal::latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/aprobar", ['version' => 1]);
        $p->refresh();
        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/generar", ['version' => $p->version, 'extra' => ['importe' => '1']]);
        $p->refresh();
        $this->actingAs($this->worker)->post("/panel/propuestas/{$p->id}/pdf", ['version' => $p->version, 'pdf' => $this->pdf()]);
        $p->refresh();
        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/revisar", ['version' => $p->version, 'confirmado' => 1]);
        $p->refresh();
        $this->actingAs($this->admin)->post("/panel/propuestas/{$p->id}/comunicacion", $this->comunicacion(['version' => $p->version]));

        return $p->refresh();
    }

    public function test_un_cliente_no_entra_en_las_propuestas(): void
    {
        $this->actingAs($this->usuario('client'))->get('/panel/propuestas')->assertForbidden();
    }

    public function test_las_pantallas_del_circuito_cargan_en_cada_paso(): void
    {
        $this->withoutExceptionHandling();
        $p = $this->propuesta();
        $this->actingAs($this->admin);
        foreach (['/panel/propuestas', "/panel/propuestas/{$p->id}", '/panel/comunicaciones', "/panel/expedientes/{$this->exp->id}", '/panel/dashboard'] as $url) {
            $this->get($url)->assertOk();
        }
        $q = $this->aprobada2();
        foreach (["/panel/propuestas/{$q->id}", '/panel/comunicaciones', '/panel/propuestas?estado=communication_requested'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_mi_jornada_muestra_lo_que_espera_una_decision_de_la_direccion(): void
    {
        $this->propuesta();

        $this->actingAs($this->admin)->get('/panel/dashboard')->assertOk()->assertSee('Mi jornada')->assertSee('Burofax a Cliente');
    }

    public function test_mi_jornada_avisa_de_la_calidad_de_los_datos(): void
    {
        Client::create(['codigo' => 'C-2', 'razon_social' => 'Sin NIF', 'tipo' => 'empresa']);
        Expedient::create(['codigo' => '2026-002', 'client_id' => Client::first()->id, 'titulo' => 'Sin servicio', 'estado' => 'abierto']);

        $this->actingAs($this->admin)->get('/panel/dashboard')->assertOk()->assertSee('sin servicio')->assertSee('sin NIF');
    }
}
