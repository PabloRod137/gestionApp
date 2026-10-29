<?php

namespace Tests\Feature;

use App\Jobs\ProcesarDocumentoIndexado;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DocumentoIndexado;
use App\Models\Expedient;
use App\Models\Hallazgo;
use App\Models\Service;
use App\Models\Setting;
use App\Models\SyncEvent;
use App\Models\User;
use App\Support\Documental\Bandeja;
use App\Support\Documental\CarpetaCliente;
use App\Support\Documental\Conocimiento;
use App\Support\Documental\ExtraccionException;
use App\Support\Documental\Extractor;
use App\Support\Documental\Inventario;
use App\Support\Documental\Ocr;
use App\Support\Documental\Procesador;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use ZipArchive;

/** Conocimiento documental local: inventario, lectura en local, propuestas y decisiones humanas. Documentos inventados. */
class IndexacionDocumentalTest extends TestCase
{
    use RefreshDatabase;

    private string $raiz;
    private Client $ana;
    private Client $beto;
    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'raiz-'.bin2hex(random_bytes(5));
        mkdir($this->raiz.'/01_CLIENTES/ANA_PEREZ/00_FACTURAS', 0777, true);
        mkdir($this->raiz.'/01_CLIENTES/BETO_RUIZ', 0777, true);
        config(['documental.raiz' => $this->raiz]);
        Setting::setValue('company_nif', 'B12345674');
        Setting::setValue('company_email', 'despacho@correo.test');
        $this->seed(RolesSeeder::class);
        $this->worker = User::factory()->create();
        $this->worker->assignRole('worker');
        $this->ana = Client::create(['codigo' => 'ANA', 'razon_social' => 'Ana Pérez', 'tipo' => 'particular', 'ruta_sharepoint' => '/Documentos compartidos/01_CLIENTES/ANA_PEREZ/00_FACTURAS']);
        $this->beto = Client::create(['codigo' => 'BETO', 'razon_social' => 'Beto Ruiz', 'tipo' => 'particular', 'ruta_sharepoint' => '/Documentos compartidos/01_CLIENTES/BETO_RUIZ']);
    }

    protected function tearDown(): void
    {
        $this->borrar($this->raiz);
        parent::tearDown();
    }

    private function borrar(string $d): void
    {
        foreach (glob($d.'/{,.}*', GLOB_BRACE) ?: [] as $f) {
            if (in_array(basename($f), ['.', '..'], true)) {
                continue;
            }
            is_dir($f) ? $this->borrar($f) : @unlink($f);
        }
        @rmdir($d);
    }

    private function ruta(string $cliente, string $archivo): string
    {
        return $this->raiz."/01_CLIENTES/$cliente/$archivo";
    }

    private function txt(string $cliente, string $nombre, string $texto): string
    {
        file_put_contents($p = $this->ruta($cliente, $nombre), $texto);

        return $p;
    }

    // ── Carpeta de cada cliente ──────────────────────────────────────────────────────────

    public function test_la_carpeta_del_cliente_es_la_del_cliente_aunque_su_ruta_apunte_a_una_subcarpeta(): void
    {
        $this->assertSame(realpath($this->raiz.'/01_CLIENTES/ANA_PEREZ'), CarpetaCliente::de($this->ana));
        $this->assertSame(realpath($this->raiz.'/01_CLIENTES/BETO_RUIZ'), CarpetaCliente::de($this->beto));
    }

    public function test_rutas_peligrosas_o_inexistentes_no_resuelven(): void
    {
        foreach (['/Documentos compartidos/01_CLIENTES/../../etc', '/Documentos compartidos/01_CLIENTES', '/otra/cosa/ANA', '/Documentos compartidos/01_CLIENTES/NO_EXISTE', ''] as $ruta) {
            $this->assertNull(CarpetaCliente::de(new Client(['ruta_sharepoint' => $ruta])), $ruta);
        }
        $this->assertNull(CarpetaCliente::de($this->ana, '/no/existe'));
    }

    // ── Inventario ───────────────────────────────────────────────────────────────────────

    public function test_el_inventario_da_de_alta_lo_nuevo_omite_lo_no_procesable_y_no_mezcla_clientes(): void
    {
        $this->txt('ANA_PEREZ', 'nota.txt', 'hola');
        file_put_contents($this->ruta('ANA_PEREZ', '00_FACTURAS/factura.txt'), 'factura');
        file_put_contents($this->ruta('ANA_PEREZ', 'antiguo.doc'), 'x');
        file_put_contents($this->ruta('ANA_PEREZ', '~$temporal.docx'), 'x');
        file_put_contents($this->ruta('ANA_PEREZ', '.oculto.txt'), 'x');
        $this->txt('BETO_RUIZ', 'beto.txt', 'otro');

        $r = (new Inventario())->inventariar(null, false);

        $this->assertSame(2, $r['clientes']);
        $this->assertSame(3, $r['nuevos']);
        $this->assertSame(1, $r['omitidos']);
        $this->assertSame(['00_FACTURAS/factura.txt', 'nota.txt'], DocumentoIndexado::where('client_id', $this->ana->id)->where('estado', 'pendiente')->orderBy('ruta')->get()->map(fn ($d) => str_replace('01_CLIENTES/ANA_PEREZ/', '', $d->ruta))->all());
        $this->assertSame('Word antiguo (.doc): no hay un conversor seguro acreditado', DocumentoIndexado::where('extension', 'doc')->first()->motivo);
        $this->assertSame(1, DocumentoIndexado::where('client_id', $this->beto->id)->count());
        $this->assertSame(0, DocumentoIndexado::where('nombre', 'like', '~$%')->orWhere('nombre', 'like', '.%')->count());
    }

    public function test_una_segunda_pasada_no_duplica_detecta_cambios_y_desaparecidos(): void
    {
        $p = $this->txt('ANA_PEREZ', 'nota.txt', 'uno');
        $this->txt('ANA_PEREZ', 'otra.txt', 'dos');
        $inv = new Inventario();
        $inv->inventariar(null, false);

        $this->assertSame(0, $inv->inventariar(null, false)['nuevos']);
        $this->assertSame(2, DocumentoIndexado::count());

        file_put_contents($p, 'cambiado');
        touch($p, time() + 10);
        unlink($this->ruta('ANA_PEREZ', 'otra.txt'));
        $r = $inv->inventariar(null, false);

        $this->assertSame([1, 1], [$r['modificados'], $r['desaparecidos']]);
        $this->assertSame('pendiente', DocumentoIndexado::where('nombre', 'nota.txt')->first()->estado);
        $this->assertSame('desaparecido', DocumentoIndexado::where('nombre', 'otra.txt')->first()->estado);
    }

    public function test_un_archivo_demasiado_grande_se_omite_sin_leerlo(): void
    {
        config(['documental.max_bytes' => 10]);
        $this->txt('ANA_PEREZ', 'grande.txt', str_repeat('x', 500));

        (new Inventario())->inventariar(null, false);

        $d = DocumentoIndexado::firstOrFail();
        $this->assertSame(['omitido', 'Archivo demasiado grande para procesarlo.', null], [$d->estado, $d->motivo, $d->sha256]);
    }

    public function test_un_enlace_que_sale_de_la_carpeta_del_cliente_no_se_sigue(): void
    {
        $this->txt('BETO_RUIZ', 'secreto.txt', 'datos de otro cliente');
        if (! @symlink($this->ruta('BETO_RUIZ', 'secreto.txt'), $this->ruta('ANA_PEREZ', 'enlace.txt'))) {
            $this->markTestSkipped('No se pueden crear enlaces simbólicos en este equipo.');
        }

        (new Inventario())->inventariar(null, false);

        $this->assertSame(0, DocumentoIndexado::where('client_id', $this->ana->id)->count());
    }

    public function test_la_cola_recibe_lo_pendiente_en_la_cola_local(): void
    {
        Queue::fake();
        $this->txt('ANA_PEREZ', 'a.txt', 'a');
        $this->txt('BETO_RUIZ', 'b.txt', 'b');

        $r = (new Inventario())->inventariar();

        $this->assertSame(2, $r['encolados']);
        Queue::assertPushedOn('documentos', ProcesarDocumentoIndexado::class);
        Queue::assertPushed(ProcesarDocumentoIndexado::class, 2);
    }

    // ── Lectura y propuestas ─────────────────────────────────────────────────────────────

    private function procesar(string $nombre): DocumentoIndexado
    {
        (new Inventario())->inventariar(null, false);

        return app(Procesador::class)->procesar(DocumentoIndexado::where('nombre', $nombre)->firstOrFail());
    }

    public function test_con_la_ficha_vacia_se_proponen_nif_correo_y_telefono_ignorando_los_del_despacho(): void
    {
        $this->txt('ANA_PEREZ', 'contrato.txt', "Contrato.\nDNI de la contratante: 89890001K\nCorreo: ana.perez@ejemplo.test o despacho@correo.test\nTeléfono: 666 123 456\nDespacho NIF B12345674");

        $d = $this->procesar('contrato.txt');

        $this->assertSame('analizado', $d->estado);
        $h = Hallazgo::where('documento_id', $d->id)->get()->keyBy('campo');
        $this->assertSame(['89890001K', 'ana.perez@ejemplo.test', '666 123 456'], [$h['nif']->valor, $h['email']->valor, $h['telefono']->valor]);
        $this->assertSame(['propuesta', 70, false, 'pendiente'], [$h['nif']->tipo, $h['nif']->confianza, $h['nif']->posible_tercero, $h['nif']->estado]);
        $this->assertSame($d->sha256, $h['nif']->sha256);
        $this->assertSame(1, $h['nif']->pagina);
        $this->assertStringContainsString('89890001K', $h['nif']->fragmento);
        $this->assertNull($this->ana->fresh()->nif, 'Nada pasa a la ficha sin que una persona lo acepte');
    }

    public function test_varios_candidatos_distintos_se_marcan_como_posible_tercero_con_menos_confianza(): void
    {
        $this->txt('ANA_PEREZ', 'escritura.txt', 'Compareció 89890001K y también 12345678Z.');

        $this->procesar('escritura.txt');

        $nifs = Hallazgo::where('campo', 'nif')->get();
        $this->assertCount(2, $nifs);
        foreach ($nifs as $h) {
            $this->assertTrue($h->posible_tercero);
            $this->assertSame(35, $h->confianza);
        }
    }

    public function test_un_nif_con_letra_incorrecta_no_se_propone(): void
    {
        $this->txt('ANA_PEREZ', 'raro.txt', 'DNI 12345678A');

        $this->procesar('raro.txt');

        $this->assertSame(0, Hallazgo::count());
    }

    public function test_si_la_ficha_coincide_no_hay_propuesta_y_si_difiere_hay_contradiccion(): void
    {
        $this->ana->update(['nif' => '89890001K', 'email' => 'vieja@ejemplo.test']);
        $this->txt('ANA_PEREZ', 'datos.txt', 'NIF 89890001K. Correo nuevo: nueva@ejemplo.test');

        $this->procesar('datos.txt');

        $this->assertSame(0, Hallazgo::where('campo', 'nif')->count());
        $c = Hallazgo::where('campo', 'email')->firstOrFail();
        $this->assertSame(['contradiccion', 'nueva@ejemplo.test', 'vieja@ejemplo.test'], [$c->tipo, $c->valor, $c->valor_actual]);
    }

    public function test_si_el_dato_de_la_ficha_aparece_en_el_documento_no_hay_contradiccion(): void
    {
        $this->ana->update(['email' => 'ana@ejemplo.test']);
        $this->txt('ANA_PEREZ', 'cc.txt', 'Con copia a ana@ejemplo.test y a su hermano hermano@ejemplo.test');

        $this->procesar('cc.txt');

        $this->assertSame(0, Hallazgo::count());
    }

    public function test_reprocesar_no_duplica_hallazgos_y_un_archivo_modificado_deja_obsoletos_los_anteriores(): void
    {
        $p = $this->txt('ANA_PEREZ', 'datos.txt', 'Correo: ana@ejemplo.test');
        $d = $this->procesar('datos.txt');
        $this->assertSame(1, Hallazgo::count());

        file_put_contents($p, 'Correo: otra@ejemplo.test');
        touch($p, time() + 10);
        (new Inventario())->inventariar(null, false);
        app(Procesador::class)->procesar($d->fresh());

        $this->assertSame(['obsoleto', 'pendiente'], Hallazgo::orderBy('id')->pluck('estado')->all());
        $this->assertSame(2, Hallazgo::count());
    }

    public function test_un_archivo_que_desaparece_o_sale_de_la_carpeta_antes_de_procesarse_no_se_lee(): void
    {
        $p = $this->txt('ANA_PEREZ', 'efimero.txt', 'x');
        (new Inventario())->inventariar(null, false);
        unlink($p);

        $d = app(Procesador::class)->procesar(DocumentoIndexado::firstOrFail());

        $this->assertSame('desaparecido', $d->estado);
    }

    public function test_un_documento_nunca_genera_hallazgos_para_otro_cliente(): void
    {
        $this->txt('ANA_PEREZ', 'ana.txt', 'Correo: ana@ejemplo.test');
        $this->txt('BETO_RUIZ', 'beto.txt', 'Correo: beto@ejemplo.test');

        $this->procesar('ana.txt');
        app(Procesador::class)->procesar(DocumentoIndexado::where('nombre', 'beto.txt')->first());

        $this->assertSame(['ana@ejemplo.test'], Hallazgo::where('client_id', $this->ana->id)->pluck('valor')->all());
        $this->assertSame(['beto@ejemplo.test'], Hallazgo::where('client_id', $this->beto->id)->pluck('valor')->all());
    }

    // ── Lectores ─────────────────────────────────────────────────────────────────────────

    public function test_lee_word_excel_pdf_y_csv_antiguo(): void
    {
        $zip = new ZipArchive();
        $zip->open($docx = $this->ruta('ANA_PEREZ', 'carta.docx'), ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>Correo: word@ejemplo.test</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $hoja = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja->getActiveSheet()->setCellValue('A1', 'Teléfono')->setCellValue('B1', '655 111 222');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($hoja))->save($xlsx = $this->ruta('ANA_PEREZ', 'datos.xlsx'));

        file_put_contents($pdf = $this->ruta('ANA_PEREZ', 'acta.pdf'), Pdf::loadHTML('<p>NIF 89890001K de la compareciente</p>')->output());
        file_put_contents($csv = $this->ruta('ANA_PEREZ', 'antiguo.csv'), mb_convert_encoding("Nombre;Correo\nAña;ana@ejemplo.test\n", 'Windows-1252', 'UTF-8'));

        $e = new Extractor(new Ocr());
        $this->assertStringContainsString('word@ejemplo.test', $e->extraer($docx, 'docx')['paginas'][0]);
        $this->assertStringContainsString('655 111 222', $e->extraer($xlsx, 'xlsx')['paginas'][0]);
        $this->assertSame('pdf', $e->extraer($pdf, 'pdf')['metodo']);
        $this->assertStringContainsString('89890001K', implode(' ', $e->extraer($pdf, 'pdf')['paginas']));
        $this->assertStringContainsString('Añ', $e->extraer($csv, 'csv')['paginas'][0]);
    }

    public function test_una_imagen_o_un_pdf_escaneado_sin_ocr_se_omite_con_su_motivo(): void
    {
        $this->bindOcr(false);
        file_put_contents($png = $this->ruta('ANA_PEREZ', 'foto.png'), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='));
        file_put_contents($this->ruta('ANA_PEREZ', 'escaneo.pdf'), Pdf::loadHTML('<html><body></body></html>')->output());
        (new Inventario())->inventariar(null, false);

        $a = app(Procesador::class)->procesar(DocumentoIndexado::where('nombre', 'foto.png')->first());
        $b = app(Procesador::class)->procesar(DocumentoIndexado::where('nombre', 'escaneo.pdf')->first());

        $this->assertSame(['omitido', 'omitido'], [$a->estado, $b->estado]);
        $this->assertStringContainsString('falta instalar el OCR', $a->motivo);
        $this->assertStringContainsString('falta instalar el OCR', $b->motivo);
    }

    public function test_con_ocr_disponible_se_lee_la_imagen_y_el_pdf_escaneado(): void
    {
        $this->bindOcr(true);
        file_put_contents($this->ruta('ANA_PEREZ', 'foto.png'), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='));
        file_put_contents($this->ruta('ANA_PEREZ', 'escaneo.pdf'), Pdf::loadHTML('<html><body></body></html>')->output());

        $a = $this->procesar('foto.png');
        $b = app(Procesador::class)->procesar(DocumentoIndexado::where('nombre', 'escaneo.pdf')->first());

        $this->assertSame(['analizado', 'ocr'], [$a->estado, $a->metodo], (string) $a->motivo);
        $this->assertSame(['analizado', 'ocr'], [$b->estado, $b->metodo]);
        $this->assertTrue(Hallazgo::where('valor', 'ocr@ejemplo.test')->exists());
    }

    public function test_un_pdf_roto_se_anota_sin_romper_nada(): void
    {
        file_put_contents($this->ruta('ANA_PEREZ', 'roto.pdf'), 'esto no es un pdf');

        $d = $this->procesar('roto.pdf');

        $this->assertSame('omitido', $d->estado);
        $this->assertStringContainsString('dañado', $d->motivo);
    }

    private function bindOcr(bool $disponible): void
    {
        $this->app->bind(Ocr::class, fn () => new class($disponible) extends Ocr {
            public function __construct(private bool $ok)
            {
            }

            public function disponible(): bool
            {
                return $this->ok;
            }

            public function conPdf(): bool
            {
                return $this->ok;
            }

            public function imagen(string $ruta): string
            {
                return $this->ok ? 'Correo leído por OCR: ocr@ejemplo.test' : throw new ExtraccionException('Imagen sin texto: falta instalar el OCR (Tesseract).');
            }

            public function pdf(string $ruta): array
            {
                return $this->ok ? ['Correo leído por OCR: ocr@ejemplo.test'] : throw new ExtraccionException('PDF escaneado: falta instalar el OCR (Tesseract y Poppler).');
            }
        });
    }

    // ── Cola ─────────────────────────────────────────────────────────────────────────────

    public function test_el_trabajo_de_la_cola_procesa_solo_su_documento(): void
    {
        $this->txt('ANA_PEREZ', 'a.txt', 'Correo: a@ejemplo.test');
        $this->txt('ANA_PEREZ', 'b.txt', 'Correo: b@ejemplo.test');
        (new Inventario())->inventariar(null, false);
        $a = DocumentoIndexado::where('nombre', 'a.txt')->first();

        (new ProcesarDocumentoIndexado($a->id))->handle(app(Procesador::class));

        $this->assertSame(['analizado', 'pendiente'], [$a->fresh()->estado, DocumentoIndexado::where('nombre', 'b.txt')->first()->estado]);
        $this->assertSame(['a@ejemplo.test'], Hallazgo::pluck('valor')->all());
    }

    public function test_los_comandos_inventarian_y_el_barrido_procesa_lo_pendiente(): void
    {
        $this->txt('ANA_PEREZ', 'a.txt', 'Correo: a@ejemplo.test');

        $this->artisan('documentos:inventariar', ['--sin-cola' => true])->expectsOutputToContain('nuevos: 1')->assertSuccessful();
        $this->artisan('documentos:barrido')->expectsOutputToContain('Procesados: 1')->assertSuccessful();

        $this->assertSame(1, Hallazgo::count());
    }

    public function test_sin_carpeta_raiz_los_comandos_avisan_y_no_fallan(): void
    {
        config(['documental.raiz' => '']);

        $this->artisan('documentos:inventariar')->expectsOutputToContain('No hay carpeta raíz')->assertSuccessful();
        $this->artisan('documentos:barrido')->expectsOutputToContain('No hay carpeta raíz')->assertSuccessful();
    }

    // ── Decisiones humanas ───────────────────────────────────────────────────────────────

    private function propuesta(Client $c, string $campo, string $valor, string $tipo = 'propuesta'): Hallazgo
    {
        $d = DocumentoIndexado::create(['client_id' => $c->id, 'ruta' => 'x/'.bin2hex(random_bytes(4)).'.txt', 'nombre' => 'doc.txt', 'extension' => 'txt', 'sha256' => str_repeat('a', 64), 'estado' => 'analizado']);

        return Hallazgo::create(['client_id' => $c->id, 'documento_id' => $d->id, 'tipo' => $tipo, 'campo' => $campo, 'valor' => $valor, 'valor_actual' => $c->{$campo}, 'confianza' => 70, 'sha256' => $d->sha256]);
    }

    public function test_aceptar_rellena_el_hueco_deja_historial_auditoria_y_evento_de_sharepoint(): void
    {
        $h = $this->propuesta($this->ana, 'email', 'ana@ejemplo.test');

        (new Bandeja())->decidir($h, 'aceptar', $this->worker, 'Visto en el contrato');

        $this->assertSame('ana@ejemplo.test', $this->ana->fresh()->email);
        $this->assertSame(['aceptado', $this->worker->id], [$h->fresh()->estado, $h->fresh()->decidido_por]);
        $dec = $h->decisiones()->firstOrFail();
        $this->assertSame(['aceptar', null, 'ana@ejemplo.test', 'Visto en el contrato'], [$dec->accion, $dec->valor_antes, $dec->valor_despues, $dec->nota]);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'hallazgo_aceptado', 'modelo_id' => $this->ana->id]);
        $this->assertSame(1, SyncEvent::where('client_id', $this->ana->id)->count());
    }

    public function test_aceptar_no_pisa_un_dato_existente_pero_corregir_si_y_lo_deja_escrito(): void
    {
        $this->ana->update(['email' => 'vieja@ejemplo.test']);
        $h = $this->propuesta($this->ana->fresh(), 'email', 'nueva@ejemplo.test', 'contradiccion');

        try {
            (new Bandeja())->decidir($h, 'aceptar', $this->worker);
            $this->fail('No debía pisar la ficha');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Corregir', $e->getMessage());
        }
        $this->assertSame('vieja@ejemplo.test', $this->ana->fresh()->email);

        (new Bandeja())->decidir($h, 'corregir', $this->worker);

        $this->assertSame('nueva@ejemplo.test', $this->ana->fresh()->email);
        $dec = $h->fresh()->decisiones()->firstOrFail();
        $this->assertSame(['corregir', 'vieja@ejemplo.test', 'nueva@ejemplo.test'], [$dec->accion, $dec->valor_antes, $dec->valor_despues]);
        $this->assertSame('corregido', $h->fresh()->estado);
    }

    public function test_un_nif_que_ya_tiene_otra_ficha_no_se_guarda(): void
    {
        $this->beto->update(['nif' => '89890001K']);
        $h = $this->propuesta($this->ana, 'nif', '89890001K');

        $this->expectException(\DomainException::class);
        (new Bandeja())->decidir($h, 'aceptar', $this->worker);
    }

    public function test_rechazar_reabrir_y_no_decidir_dos_veces(): void
    {
        $h = $this->propuesta($this->ana, 'telefono', '666 123 456');

        (new Bandeja())->decidir($h, 'rechazar', $this->worker, 'Es de su hermana');
        $this->assertSame('rechazado', $h->fresh()->estado);
        $this->assertNull($this->ana->fresh()->telefono);

        (new Bandeja())->decidir($h, 'reabrir', $this->worker);
        $this->assertSame('pendiente', $h->fresh()->estado);

        (new Bandeja())->decidir($h, 'aceptar', $this->worker);
        $this->assertSame(['rechazar', 'reabrir', 'aceptar'], $h->decisiones()->orderBy('id')->pluck('accion')->all());

        $this->expectException(\DomainException::class);
        (new Bandeja())->decidir($h, 'rechazar', $this->worker);
    }

    public function test_una_decision_solo_toca_la_ficha_del_cliente_del_hallazgo(): void
    {
        $h = $this->propuesta($this->ana, 'email', 'ana@ejemplo.test');

        (new Bandeja())->decidir($h, 'aceptar', $this->worker);

        $this->assertNull($this->beto->fresh()->email);
    }

    // ── Mapa de conocimiento ─────────────────────────────────────────────────────────────

    public function test_el_mapa_relaciona_requisitos_con_documentos_posibles_del_propio_cliente(): void
    {
        $s = Service::create(['codigo' => 'ALTA', 'nombre' => 'Alta de trabajador', 'tipo' => 'sin_cita', 'facturable' => true]);
        $s->requirements()->create(['codigo' => 'R1', 'descripcion' => 'Contrato de arrendamiento firmado', 'obligatorio' => true, 'normalizado' => true]);
        $s->requirements()->create(['codigo' => 'R2', 'descripcion' => 'Certificado de empadronamiento', 'obligatorio' => true, 'normalizado' => true]);
        Expedient::create(['codigo' => '2026-1', 'client_id' => $this->ana->id, 'service_id' => $s->id, 'titulo' => 'ALTA', 'estado' => 'abierto']);
        $this->txt('ANA_PEREZ', 'contrato arrendamiento.txt', 'Contrato de arrendamiento de vivienda entre las partes');
        $this->txt('BETO_RUIZ', 'empadronamiento.txt', 'Certificado de empadronamiento de otro cliente');
        (new Inventario())->inventariar(null, false);
        DocumentoIndexado::query()->each(fn ($d) => app(Procesador::class)->procesar($d));

        $mapa = Conocimiento::de($this->ana->fresh());

        $req = collect($mapa['expedientes'][0]['requisitos'])->keyBy('texto');
        $this->assertSame(['contrato arrendamiento.txt'], $req['Contrato de arrendamiento firmado']['posibles']);
        $this->assertSame([], $req['Certificado de empadronamiento']['posibles'], 'No mira documentos de otro cliente');
        $this->assertSame(1, $mapa['documentos']['total']);
    }

    // ── Panel ────────────────────────────────────────────────────────────────────────────

    public function test_la_bandeja_del_panel_lista_decide_y_revisa_carpetas(): void
    {
        $this->propuesta($this->ana, 'email', 'ana@ejemplo.test');
        $this->ana->update(['telefono' => '600 000 000']);
        $c = $this->propuesta($this->ana->fresh(), 'telefono', '666 000 111', 'contradiccion');

        $this->actingAs($this->worker)->get('/panel/documental')->assertOk()->assertSee('Bandeja documental')->assertSee('ana@ejemplo.test')->assertDontSee('666 000 111');
        $this->actingAs($this->worker)->get('/panel/documental?vista=contradicciones')->assertOk()->assertSee('666 000 111');

        $h = Hallazgo::where('campo', 'email')->first();
        $this->actingAs($this->worker)->post("/panel/documental/hallazgos/{$h->id}", ['accion' => 'aceptar'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('ana@ejemplo.test', $this->ana->fresh()->email);
        $this->actingAs($this->worker)->get('/panel/documental?vista=resueltos')->assertOk()->assertSee('Aceptado');

        $this->actingAs($this->worker)->post("/panel/documental/hallazgos/{$c->id}", ['accion' => 'aceptar'])->assertSessionHas('error');
        $this->actingAs($this->worker)->post('/panel/documental/revisar')->assertSessionHas('success');
        $this->actingAs($this->worker)->get("/panel/clientes/{$this->ana->id}/conocimiento")->assertOk()->assertSee('Conocimiento de Ana Pérez');
    }

    public function test_la_bandeja_exige_ser_personal_del_despacho(): void
    {
        $cliente = User::factory()->create();
        $cliente->assignRole('client');

        $this->actingAs($cliente)->get('/panel/documental')->assertForbidden();
        $this->actingAs($cliente)->get("/panel/clientes/{$this->ana->id}/conocimiento")->assertForbidden();
        auth()->logout();
        $this->get('/panel/documental')->assertRedirect('/login');
    }
}
