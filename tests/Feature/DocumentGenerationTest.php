<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
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

/** Generacion de documentos a partir de plantillas Word del despacho, con datos inventados. */
class DocumentGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Expedient $expediente;
    private DocumentTemplate $plantilla;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(RolesSeeder::class);

        $client = Client::create(['codigo' => '2020-009', 'nif' => 'B00000009', 'razon_social' => 'Cliente Nueve SL', 'tipo' => 'empresa', 'email' => 'nueve@example.test']);
        $service = Service::create(['codigo' => 'BUROFAX', 'nombre' => 'Burofax', 'precio_base' => 90, 'tipo' => 'sin_cita', 'genera_documento' => true]);
        $this->expediente = Expedient::create(['codigo' => '2026-009', 'client_id' => $client->id, 'service_id' => $service->id, 'titulo' => 'Reclamación de deuda', 'estado' => 'abierto']);
        $this->plantilla = DocumentTemplate::create(['codigo' => 'PLT_BUROFAX', 'nombre' => 'Burofax', 'tipo_documento' => 'RECLAMACION']);
        $service->templates()->attach($this->plantilla->id);
        Setting::setValue('company_name', 'Despacho de prueba');
    }

    private function docx(array $lineas): UploadedFile
    {
        $word = new PhpWord;
        $section = $word->addSection();
        foreach ($lineas as $linea) {
            $section->addText($linea);
        }
        $ruta = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($ruta);

        return new UploadedFile($ruta, 'modelo.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    private function subirPlantilla(array $lineas): void
    {
        $this->actingAs($this->usuario('admin'))
            ->post("/panel/plantillas/{$this->plantilla->id}/archivo", ['archivo' => $this->docx($lineas)])
            ->assertSessionHasNoErrors();
    }

    private function usuario(string $rol): User
    {
        $u = User::factory()->create();
        $u->assignRole($rol);

        return $u;
    }

    /** Texto tal y como lo mostraria Word (se lee el XML y se decodifican las entidades una sola vez). */
    private function textoDe(string $rutaStorage): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gen').'.docx';
        file_put_contents($tmp, Storage::get($rutaStorage));
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $xml = str_replace('</w:p>', "\n", $zip->getFromName('word/document.xml'));
        $zip->close();

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public function test_el_administrador_sube_la_plantilla_y_se_ven_sus_campos(): void
    {
        $this->subirPlantilla(['Estimado ${cliente_nombre}', 'NIF ${cliente_nif}', '${campo_inventado}']);

        $this->plantilla->refresh();
        $this->assertNotNull($this->plantilla->archivo);
        Storage::assertExists($this->plantilla->archivo);
        $this->get('/panel/plantillas')->assertOk()->assertSee('cliente_nombre')->assertSee('campo_inventado');
    }

    public function test_solo_se_admite_un_archivo_word(): void
    {
        $this->actingAs($this->usuario('admin'))
            ->post("/panel/plantillas/{$this->plantilla->id}/archivo", ['archivo' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors('archivo');
        $this->assertNull($this->plantilla->refresh()->archivo);
    }

    public function test_un_trabajador_no_puede_subir_plantillas(): void
    {
        $this->actingAs($this->usuario('worker'))
            ->post("/panel/plantillas/{$this->plantilla->id}/archivo", ['archivo' => $this->docx(['x'])])
            ->assertForbidden();
    }

    public function test_genera_el_documento_con_los_datos_del_cliente_y_lo_guarda_en_el_expediente(): void
    {
        $this->subirPlantilla(['Estimado ${cliente_nombre}', 'Expediente ${expediente_numero}: ${expediente_titulo}', 'Firmado: ${despacho_nombre}']);

        $this->actingAs($this->usuario('worker'))
            ->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => $this->plantilla->id])
            ->assertRedirect()->assertSessionHas('success');

        $doc = ExpedientDocument::where('expedient_id', $this->expediente->id)->firstOrFail();
        $this->assertSame('generado', $doc->canal_entrada);
        $this->assertSame('interno', $doc->visibilidad);
        $texto = $this->textoDe($doc->ruta);
        $this->assertStringContainsString('Estimado Cliente Nueve SL', $texto);
        $this->assertStringContainsString('Expediente 2026-009: Reclamación de deuda', $texto);
        $this->assertStringContainsString('Firmado: Despacho de prueba', $texto);
        $this->assertStringNotContainsString('${', $texto);
    }

    public function test_si_falta_un_dato_que_la_plantilla_pide_no_genera_y_avisa(): void
    {
        $this->expediente->client->update(['telefono' => null]);
        $this->subirPlantilla(['Teléfono: ${cliente_telefono}']);
        $trabajador = $this->usuario('worker');

        $this->actingAs($trabajador)
            ->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => $this->plantilla->id])
            ->assertRedirect()->assertSessionHas('faltan', ['cliente_telefono']);
        $this->assertSame(0, ExpedientDocument::count());

        // generar igualmente, sabiendo que queda en blanco
        $this->actingAs($trabajador)
            ->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => $this->plantilla->id, 'forzar' => 1])
            ->assertSessionHas('success');
        // el Word y su PDF, creado a la vez
        $this->assertSame(2, ExpedientDocument::count());
    }

    public function test_el_dato_que_falta_se_escribe_en_el_momento_y_va_al_documento_sin_tocar_la_ficha(): void
    {
        $this->expediente->client->update(['telefono' => null]);
        $this->subirPlantilla(['Teléfono: ${cliente_telefono}']);

        $this->actingAs($this->usuario('worker'))
            ->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => $this->plantilla->id, 'forzar' => 1, 'extra' => ['cliente_telefono' => '600 111 222']])
            ->assertSessionHas('success');

        $this->assertStringContainsString('Teléfono: 600 111 222', $this->textoDe(ExpedientDocument::firstOrFail()->ruta));
        $this->assertNull($this->expediente->client->refresh()->telefono);
    }

    public function test_si_se_pide_se_guarda_en_la_ficha_sin_pisar_lo_que_ya_habia(): void
    {
        $this->expediente->client->update(['telefono' => null]);
        $this->subirPlantilla(['Tel ${cliente_telefono} Mail ${cliente_email}']);

        $this->actingAs($this->usuario('worker'))
            ->post("/panel/expedientes/{$this->expediente->id}/generar", [
                'document_template_id' => $this->plantilla->id, 'forzar' => 1, 'guardar_ficha' => 1,
                'extra' => ['cliente_telefono' => '600 111 222', 'cliente_email' => 'otro@example.test'],
            ])->assertSessionHas('success');

        $c = $this->expediente->client->refresh();
        $this->assertSame('600 111 222', $c->telefono);
        $this->assertSame('nueve@example.test', $c->email);
    }

    public function test_un_dato_en_blanco_no_borra_el_que_ya_existe_y_el_del_despacho_no_se_guarda_en_el_cliente(): void
    {
        $this->subirPlantilla(['${cliente_nombre} / ${despacho_telefono}']);

        $this->actingAs($this->usuario('worker'))
            ->post("/panel/expedientes/{$this->expediente->id}/generar", [
                'document_template_id' => $this->plantilla->id, 'forzar' => 1, 'guardar_ficha' => 1,
                'extra' => ['cliente_nombre' => '', 'despacho_telefono' => '942 000 000'],
            ])->assertSessionHas('success');

        $this->assertStringContainsString('Cliente Nueve SL / 942 000 000', $this->textoDe(ExpedientDocument::firstOrFail()->ruta));
        $this->assertSame('Cliente Nueve SL', $this->expediente->client->refresh()->razon_social);
    }

    public function test_el_formulario_ofrece_un_campo_por_cada_dato_que_falta(): void
    {
        $this->expediente->client->update(['telefono' => null]);
        $this->subirPlantilla(['Teléfono: ${cliente_telefono}']);

        $this->actingAs($this->usuario('worker'))->followingRedirects()->from("/panel/expedientes/{$this->expediente->id}")
            ->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => $this->plantilla->id])
            ->assertSee('name="extra[cliente_telefono]"', false)->assertSee('Guardar en la ficha del cliente');
    }

    public function test_el_aviso_de_datos_que_faltan_usa_nombres_que_entiende_el_personal(): void
    {
        $this->expediente->client->update(['telefono' => null]);
        $this->subirPlantilla(['Teléfono: ${cliente_telefono}']);

        $this->actingAs($this->usuario('worker'))
            ->followingRedirects()
            ->from("/panel/expedientes/{$this->expediente->id}")
            ->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => $this->plantilla->id])
            ->assertSee('Teléfono del cliente')
            ->assertSee('Generar documento');
    }

    public function test_un_campo_que_no_esta_guardado_se_pide_antes_de_generar(): void
    {
        $this->subirPlantilla(['Hola ${cliente_nombre}', 'Importe: ${importe_adeudado}']);

        $this->actingAs($this->usuario('worker'))
            ->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => $this->plantilla->id])
            ->assertSessionHas('pedir', ['importe_adeudado']);
        $this->assertSame(0, ExpedientDocument::count());
    }

    public function test_los_datos_pedidos_se_escriben_en_el_documento(): void
    {
        $this->subirPlantilla(['Hola ${cliente_nombre}', 'Importe: ${importe_adeudado}']);

        $this->actingAs($this->usuario('worker'))
            ->post("/panel/expedientes/{$this->expediente->id}/generar", [
                'document_template_id' => $this->plantilla->id,
                'extra' => ['importe_adeudado' => '1.250,00 € & gastos'],
            ])->assertSessionHas('success');

        $texto = $this->textoDe(ExpedientDocument::firstOrFail()->ruta);
        $this->assertStringContainsString('Importe: 1.250,00 € & gastos', $texto);
    }

    public function test_si_ademas_faltan_datos_guardados_los_pedidos_no_se_pierden(): void
    {
        $this->expediente->client->update(['telefono' => null]);
        $this->subirPlantilla(['Tel ${cliente_telefono}', 'Importe: ${importe_adeudado}']);

        $this->actingAs($this->usuario('worker'))
            ->post("/panel/expedientes/{$this->expediente->id}/generar", [
                'document_template_id' => $this->plantilla->id,
                'extra' => ['importe_adeudado' => '500'],
            ])->assertSessionHas('faltan', ['cliente_telefono'])->assertSessionHas('extras', ['importe_adeudado' => '500']);
    }

    public function test_el_documento_generado_se_puede_descargar(): void
    {
        $this->subirPlantilla(['Hola ${cliente_nombre}']);
        $trabajador = $this->usuario('worker');
        $this->actingAs($trabajador)->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => $this->plantilla->id]);

        $doc = ExpedientDocument::firstOrFail();
        $this->actingAs($trabajador)->get("/panel/documentos/{$doc->id}/download")->assertOk();
    }

    public function test_un_cliente_no_puede_generar_documentos(): void
    {
        $this->subirPlantilla(['x']);

        $this->actingAs($this->usuario('client'))
            ->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => $this->plantilla->id])
            ->assertForbidden();
    }

    public function test_se_puede_generar_con_un_modelo_que_no_esta_asignado_al_servicio(): void
    {
        $this->subirPlantilla(['Hola ${cliente_nombre}']);
        $this->expediente->service->templates()->detach($this->plantilla->id);

        $this->actingAs($this->usuario('worker'))
            ->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => $this->plantilla->id])
            ->assertSessionHas('success');
        // el Word y su PDF, creado a la vez
        $this->assertSame(2, ExpedientDocument::count());
    }

    public function test_un_modelo_inexistente_se_rechaza(): void
    {
        $this->actingAs($this->usuario('worker'))
            ->post("/panel/expedientes/{$this->expediente->id}/generar", ['document_template_id' => 99999])
            ->assertSessionHasErrors('document_template_id');
    }

    public function test_el_administrador_asigna_y_quita_plantillas_de_un_servicio(): void
    {
        $nueva = DocumentTemplate::create(['codigo' => 'NUEVA', 'nombre' => 'Nueva']);
        $service = $this->expediente->service;
        $admin = $this->usuario('admin');

        $this->actingAs($admin)->post("/panel/servicios/{$service->id}/plantillas", ['document_template_id' => $nueva->id])->assertRedirect();
        $this->assertTrue($service->templates()->whereKey($nueva->id)->exists());

        $this->actingAs($admin)->delete("/panel/servicios/{$service->id}/plantillas/{$nueva->id}")->assertRedirect();
        $this->assertFalse($service->templates()->whereKey($nueva->id)->exists());

        $this->actingAs($this->usuario('worker'))->post("/panel/servicios/{$service->id}/plantillas", ['document_template_id' => $nueva->id])->assertForbidden();
    }
}
