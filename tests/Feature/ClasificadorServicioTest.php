<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Expedient;
use App\Models\Service;
use App\Support\ClasificadorServicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Reconoce el servicio probable de un expediente por su asunto: palabras clave de la tarifa + lo que el despacho ya clasificó. */
class ClasificadorServicioTest extends TestCase
{
    use RefreshDatabase;

    private Client $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliente = Client::create(['codigo' => 'C-1', 'razon_social' => 'Comunidad Demo', 'tipo' => 'comunidad']);
        $this->servicio('CERT_DEUDA', 'Certificado de deuda (transmisión de inmueble)', 'certificado, deuda, comunidad, venta, transmisión');
        $this->servicio('INC_CERR', 'Incidencia de cerrajería y accesos', 'incidencia, cerrajería, cerradura, puerta, acceso');
        $this->servicio('RECL_IMPAG', 'Reclamación de impagados a propietarios', 'impagado, moroso, reclamación, propietario');
        $this->servicio('MOD_303', 'Presentación IVA trimestral (Modelo 303)', '303, iva, trimestral, autoliquidación');
    }

    private function servicio(string $codigo, string $nombre, ?string $claves = null): Service
    {
        return Service::create(['codigo' => $codigo, 'nombre' => $nombre, 'precio_base' => 10, 'tipo' => 'sin_cita', 'palabras_clave' => $claves]);
    }

    private function expediente(string $codigo, string $titulo, ?Service $s = null): Expedient
    {
        return Expedient::create(['codigo' => $codigo, 'client_id' => $this->cliente->id, 'service_id' => $s?->id, 'titulo' => $titulo, 'estado' => 'abierto']);
    }

    private function mejor(string $asunto, ?int $excluir = null): ?string
    {
        return app(ClasificadorServicio::class)->sugerir($asunto, $excluir)[0]['servicio']->codigo ?? null;
    }

    public function test_reconoce_el_servicio_por_las_palabras_clave_sin_importar_tildes_mayusculas_ni_plurales(): void
    {
        $this->assertSame('CERT_DEUDA', $this->mejor('CERTIFICADOS DE DEUDA para la venta del piso'));
        $this->assertSame('INC_CERR', $this->mejor('Cambio de CERRADURA del portal'));
    }

    public function test_aprende_de_los_expedientes_que_el_despacho_ya_clasifico(): void
    {
        $this->expediente('2026-001', 'GABRIEL ARIAS DEUDA', Service::where('codigo', 'RECL_IMPAG')->first());
        $this->expediente('2026-002', 'PEDRO LOPEZ DEUDA ATRASADA', Service::where('codigo', 'RECL_IMPAG')->first());
        $this->expediente('2026-003', 'LECTURAS CONTADOR ENERO', Service::where('codigo', 'MOD_303')->first());

        $this->assertSame('RECL_IMPAG', $this->mejor('ANA GARCIA DEUDA 3B'));
    }

    public function test_no_se_aprende_de_si_mismo_al_evaluar_un_expediente(): void
    {
        $e = $this->expediente('2026-010', 'ZZZ RARISIMO', Service::where('codigo', 'MOD_303')->first());

        $this->assertNull($this->mejor('ZZZ RARISIMO', $e->id));
        $this->assertSame('MOD_303', $this->mejor('ZZZ RARISIMO'));
    }

    public function test_si_nada_encaja_no_inventa_un_servicio(): void
    {
        $this->assertSame([], app(ClasificadorServicio::class)->sugerir('xyzzy plugh'));
        $this->assertSame([], app(ClasificadorServicio::class)->sugerir('   '));
    }

    public function test_devuelve_como_mucho_los_candidatos_pedidos_ordenados_con_su_motivo(): void
    {
        $r = app(ClasificadorServicio::class)->sugerir('certificado deuda comunidad reclamación impagado cerradura', null, 2);

        $this->assertCount(2, $r);
        $this->assertGreaterThanOrEqual($r[1]['puntos'], $r[0]['puntos']);
        $this->assertNotSame('', $r[0]['motivo']);
    }

    public function test_indica_si_el_resultado_es_claro_o_dudoso(): void
    {
        $claro = app(ClasificadorServicio::class)->sugerir('certificado de deuda para venta');
        $dudoso = app(ClasificadorServicio::class)->sugerir('deuda');

        $this->assertTrue($claro[0]['claro']);
        $this->assertFalse($dudoso[0]['claro'] ?? false);
    }
}
