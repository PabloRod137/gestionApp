<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sincronización desde los CSV que deja Power Automate (expedientes, documentos, tarifa). Todo inventado.
 * Los CSV de SharePoint llegan en codificación Windows y con el servicio como texto JSON de Power Automate.
 */
class SincronizacionWordVivoTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/sync'.uniqid();
        mkdir($this->dir);
        Client::create(['codigo' => '2020-001', 'razon_social' => 'Cliente Uno SL', 'tipo' => 'empresa']);
        Service::create(['codigo' => 'REQ_NOTIF', 'nombre' => 'Notificación o requerimiento (contestación)', 'precio_base' => 45, 'tipo' => 'sin_cita']);
        Service::create(['codigo' => 'CERT_DEUDA', 'nombre' => 'Certificado de deuda (transmisión de inmueble)', 'precio_base' => 30, 'tipo' => 'sin_cita']);
    }

    private function csv(string $nombre, array $filas, string $codificacion = 'Windows-1252'): void
    {
        $f = fopen('php://temp', 'w+');
        foreach ($filas as $fila) {
            fputcsv($f, $fila, ',', '"', '\\');
        }
        rewind($f);
        file_put_contents("{$this->dir}/{$nombre}", mb_convert_encoding(stream_get_contents($f), $codificacion, 'UTF-8'));
    }

    private function servicioJson(string $nombre, int $id = 40): string
    {
        return json_encode(['@odata.type' => '#Microsoft.Azure.Connectors.SharePoint.SPListExpandedReference', 'Id' => $id, 'Value' => $nombre], JSON_UNESCAPED_UNICODE);
    }

    private function cabeceraExpedientes(): array
    {
        return ['ID_SP', 'EXPEDIENTE_ID', 'CLIENTE_ID_SP', 'CLIENTE_NUMERO', 'CLIENTE', 'TIPO_EXPEDIENTE', 'ASUNTO', 'ESTADO', 'FECHA_COMPLETADO', 'PRIORIDAD', 'FECHA_VENCIMIENTO', 'RUTA_SHAREPOINT', 'MODIFIED', 'SERVICIO'];
    }

    private function sincronizar(): array
    {
        return app(\App\Support\Import\SincronizacionWordVivo::class)->sincronizar($this->dir);
    }

    public function test_crea_los_expedientes_nuevos_con_su_cliente_y_el_servicio_aunque_venga_como_json_y_en_codificacion_windows(): void
    {
        $this->csv('expedientes_word_vivo.csv', [
            $this->cabeceraExpedientes(),
            [1, '2026-176', 5, '2020-001', 'Cliente Uno SL', 'Legal', 'GESTORÍA - ALEGACIÓN', 'En curso', '', 'Normal', '', 'https://sp.example/e/176', '', $this->servicioJson('Notificación o requerimiento (contestación)')],
        ]);

        $r = $this->sincronizar();

        $e = Expedient::where('codigo', '2026-176')->firstOrFail();
        $this->assertSame('GESTORÍA - ALEGACIÓN', $e->titulo);
        $this->assertSame('en_proceso', $e->estado);
        $this->assertSame('REQ_NOTIF', $e->service->codigo);
        $this->assertSame('2020-001', $e->client->codigo);
        $this->assertSame('https://sp.example/e/176', $e->ruta_sharepoint);
        $this->assertSame(1, $r['contadores']['expedientes']);
    }

    public function test_repetir_la_sincronizacion_no_duplica(): void
    {
        $this->csv('expedientes_word_vivo.csv', [$this->cabeceraExpedientes(), [1, '2026-176', 5, '2020-001', 'x', 'Legal', 'Asunto', 'No iniciado', '', '', '', '', '', '']]);

        $this->sincronizar();
        $this->sincronizar();

        $this->assertSame(1, Expedient::where('codigo', '2026-176')->count());
    }

    public function test_no_pisa_un_servicio_ya_asignado_ni_lo_borra_si_el_csv_viene_vacio(): void
    {
        $cert = Service::where('codigo', 'CERT_DEUDA')->first();
        Expedient::create(['codigo' => '2026-177', 'client_id' => Client::first()->id, 'service_id' => $cert->id, 'titulo' => 'Antiguo', 'estado' => 'abierto']);
        $this->csv('expedientes_word_vivo.csv', [
            $this->cabeceraExpedientes(),
            [1, '2026-177', 5, '2020-001', 'x', 'Fincas', 'Título nuevo', 'Completado', '', '', '', '', '', ''],
        ]);

        $this->sincronizar();

        $e = Expedient::where('codigo', '2026-177')->first();
        $this->assertSame($cert->id, $e->service_id);
        $this->assertSame('Título nuevo', $e->titulo);
        $this->assertSame('finalizado', $e->estado);
    }

    public function test_si_el_expediente_no_tenia_servicio_se_completa_con_el_del_csv(): void
    {
        Expedient::create(['codigo' => '2026-178', 'client_id' => Client::first()->id, 'titulo' => 'Sin servicio', 'estado' => 'abierto']);
        $this->csv('expedientes_word_vivo.csv', [$this->cabeceraExpedientes(), [1, '2026-178', 5, '2020-001', 'x', 'Fincas', 'Sin servicio', 'En curso', '', '', '', '', '', $this->servicioJson('Certificado de deuda (transmisión de inmueble)', 71)]]);

        $this->sincronizar();

        $this->assertSame('CERT_DEUDA', Expedient::where('codigo', '2026-178')->first()->service->codigo);
    }

    public function test_un_cliente_desconocido_se_avisa_y_no_se_importa(): void
    {
        $this->csv('expedientes_word_vivo.csv', [$this->cabeceraExpedientes(), [1, '2026-179', 5, '9999-999', 'x', 'Legal', 'Asunto', 'En curso', '', '', '', '', '', '']]);

        $r = $this->sincronizar();

        $this->assertSame(0, Expedient::where('codigo', '2026-179')->count());
        $this->assertNotEmpty(array_filter($r['avisos'], fn ($a) => str_contains($a, '2026-179')));
    }

    public function test_importa_la_tarifa_con_palabras_clave_notas_y_tipo_de_servicio(): void
    {
        $this->csv('servicios_tarifa_word_vivo.csv', [
            ['ID_SP', 'SERVICIO', 'CODIGO_SERVICIO', 'PALABRAS_CLAVE_ASUNTO', 'NOTAS', 'Tipo_de_servicio'],
            [37, 'x', 'CERT_DEUDA', "certificado, deuda, comunidad\n", 'Solicitado por propietario.', 'Facturable'],
            [40, 'x', 'REQ_NOTIF', 'requerimiento', '', 'A presupuestar'],
            [99, 'x', 'NO_EXISTE', 'z', '', ''],
        ]);

        $r = $this->sincronizar();

        $s = Service::where('codigo', 'CERT_DEUDA')->first();
        $this->assertSame('certificado, deuda, comunidad', $s->palabras_clave);
        $this->assertSame('Solicitado por propietario.', $s->notas);
        $this->assertSame('Facturable', $s->tipo_servicio);
        $this->assertSame('A presupuestar', Service::where('codigo', 'REQ_NOTIF')->first()->tipo_servicio);
        $this->assertNotEmpty(array_filter($r['avisos'], fn ($a) => str_contains($a, 'NO_EXISTE')));
    }

    public function test_importa_el_registro_de_documentos_con_su_enlace(): void
    {
        Expedient::create(['codigo' => '2026-180', 'client_id' => Client::first()->id, 'titulo' => 'Asunto', 'estado' => 'abierto']);
        $this->csv('Registro_documentos.csv', [
            ['Codigo_expediente', 'Origen', 'Documento_ID', 'Tipo_documento', 'Nombre_documento', 'Ruta_URL', 'Estado', 'Fecha_documento', 'Fecha_ultima_verificacion'],
            ['2026-180', 'SHAREPOINT', 'DOC-ABC', 'PRESUPUESTO', 'Presupuesto ñ.pdf', 'https://sp.example/d/abc', 'VIGENTE', '', ''],
            ['2026-999', 'SHAREPOINT', 'DOC-ZZZ', 'X', 'Huérfano.pdf', 'https://sp.example/d/zzz', 'VIGENTE', '', ''],
        ]);

        $this->sincronizar();
        $this->sincronizar();

        $d = ExpedientDocument::where('codigo_origen', 'DOC-ABC')->firstOrFail();
        $this->assertSame('Presupuesto ñ.pdf', $d->nombre);
        $this->assertSame('https://sp.example/d/abc', $d->url_externa);
        $this->assertSame(Expedient::where('codigo', '2026-180')->value('id'), $d->expedient_id);
        $this->assertSame(1, ExpedientDocument::where('codigo_origen', 'DOC-ABC')->count());
    }

    public function test_el_comando_avisa_si_la_carpeta_no_existe(): void
    {
        $this->artisan('despacho:sincronizar', ['carpeta' => '/no/existe'])->assertFailed();
    }

    public function test_el_comando_sincroniza_la_carpeta(): void
    {
        $this->csv('expedientes_word_vivo.csv', [$this->cabeceraExpedientes(), [1, '2026-181', 5, '2020-001', 'x', 'Legal', 'Asunto', 'En curso', '', '', '', '', '', '']]);

        $this->artisan('despacho:sincronizar', ['carpeta' => $this->dir])->assertSuccessful();

        $this->assertSame(1, Expedient::where('codigo', '2026-181')->count());
    }
}
