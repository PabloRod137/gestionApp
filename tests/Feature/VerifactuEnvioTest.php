<?php

namespace Tests\Feature;

use App\Models\RegistroFacturacion;
use App\Support\Verifactu\ClienteAeat;
use App\Support\Verifactu\EnviadorAeat;
use App\Support\Verifactu\Facturacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreaFacturas;
use Tests\TestCase;

/** El envío a la AEAT se prueba siempre con respuestas simuladas: nunca sale una petición real. */
class VerifactuEnvioTest extends TestCase
{
    use CreaFacturas, RefreshDatabase;

    private string $cert;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 10:15:30', 'Europe/Madrid'));
        Cache::flush();
        $this->datosDelDespacho();
        $this->cert = tempnam(sys_get_temp_dir(), 'cert');
        config(['verifactu.activo' => true, 'verifactu.certificado' => $this->cert, 'verifactu.entorno' => 'pruebas']);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        @unlink($this->cert);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function emitirDos(): array
    {
        $c = $this->clienteDePrueba();

        return [Facturacion::emitir($this->borrador($c)), Facturacion::emitir($this->borrador($c))];
    }

    private function linea(string $num, string $estado, string $operacion = 'Alta', string $extra = ''): string
    {
        return '<tikLRRC:RespuestaLinea><tikR:IDFactura><sf:IDEmisorFactura>B12345674</sf:IDEmisorFactura><sf:NumSerieFactura>'.$num.'</sf:NumSerieFactura><sf:FechaExpedicionFactura>06-10-2026</sf:FechaExpedicionFactura></tikR:IDFactura>'
            .'<tikR:Operacion><sf:TipoOperacion>'.$operacion.'</sf:TipoOperacion></tikR:Operacion><tikR:EstadoRegistro>'.$estado.'</tikR:EstadoRegistro>'.$extra.'</tikLRRC:RespuestaLinea>';
    }

    private function respuesta(string $estadoEnvio, string $lineas, int $espera = 60, string $csv = 'A-ABCDEF1234567890'): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"><env:Body>'
            .'<tikR:RespuestaRegFactuSistemaFacturacion xmlns:tikR="urn:r" xmlns:tikLRRC="urn:l" xmlns:sf="urn:s">'
            .'<tikR:CSV>'.$csv.'</tikR:CSV><tikR:Cabecera/><tikR:TiempoEsperaEnvio>'.$espera.'</tikR:TiempoEsperaEnvio><tikR:EstadoEnvio>'.$estadoEnvio.'</tikR:EstadoEnvio>'
            .$lineas.'</tikR:RespuestaRegFactuSistemaFacturacion></env:Body></env:Envelope>';
    }

    public function test_apagado_no_envia_nada(): void
    {
        config(['verifactu.activo' => false]);
        $this->emitirDos();

        $r = (new EnviadorAeat())->enviarPendientes();

        $this->assertSame(0, $r['enviados']);
        $this->assertStringContainsString('apagado', $r['mensaje']);
        Http::assertNothingSent();
        $this->assertSame(2, RegistroFacturacion::where('estado_envio', 'pendiente')->count());
    }

    public function test_envia_un_lote_en_orden_y_marca_los_registros_aceptados(): void
    {
        [$a, $b] = $this->emitirDos();
        Http::fake(['*' => Http::response($this->respuesta('Correcto', $this->linea('F2026-0001', 'Correcto').$this->linea('F2026-0002', 'Correcto')))]);

        $r = (new EnviadorAeat())->enviarPendientes();

        $this->assertSame(['enviados' => 2, 'aceptados' => 2, 'con_errores' => 0, 'rechazados' => 0, 'mensaje' => null], $r);
        Http::assertSentCount(1);
        Http::assertSent(function ($peticion) use ($a, $b) {
            $cuerpo = $peticion->body();

            return $peticion->url() === 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP'
                && str_contains($cuerpo, '<soapenv:Envelope')
                && str_contains($cuerpo, '<sf:NIF>B12345674</sf:NIF>')
                && strpos($cuerpo, 'F2026-0001') < strpos($cuerpo, 'F2026-0002')
                && str_contains($cuerpo, $a->registros->first()->huella)
                && str_contains($cuerpo, $b->registros->first()->huella);
        });
        foreach (RegistroFacturacion::all() as $registro) {
            $this->assertSame('aceptado', $registro->estado_envio);
            $this->assertSame('A-ABCDEF1234567890', $registro->csv);
            $this->assertNotNull($registro->enviado_at);
            $this->assertSame(1, $registro->intentos);
        }
    }

    public function test_respeta_la_espera_entre_envios_que_indica_la_aeat(): void
    {
        [$a] = $this->emitirDos();
        Http::fake(['*' => Http::sequence()
            ->push($this->respuesta('Correcto', $this->linea('F2026-0001', 'Correcto').$this->linea('F2026-0002', 'Correcto'), 120))
            ->push($this->respuesta('Correcto', $this->linea('F2026-0003', 'Correcto')))]);
        (new EnviadorAeat())->enviarPendientes();
        Facturacion::emitir($this->borrador($a->client));

        $espera = (new EnviadorAeat())->enviarPendientes();
        $this->assertStringContainsString('En espera entre envíos (120 s)', $espera['mensaje']);
        Http::assertSentCount(1);

        Carbon::setTestNow(Carbon::now()->addSeconds(121));
        $this->assertSame(1, (new EnviadorAeat())->enviarPendientes()['enviados']);
    }

    public function test_un_registro_rechazado_guarda_el_codigo_y_la_descripcion_y_los_demas_se_aceptan(): void
    {
        $this->emitirDos();
        Http::fake(['*' => Http::response($this->respuesta('ParcialmenteCorrecto',
            $this->linea('F2026-0001', 'Incorrecto', 'Alta', '<tikR:CodigoErrorRegistro>1100</tikR:CodigoErrorRegistro><tikR:DescripcionErrorRegistro>NIF no identificado</tikR:DescripcionErrorRegistro>')
            .$this->linea('F2026-0002', 'AceptadoConErrores', 'Alta', '<tikR:CodigoErrorRegistro>2000</tikR:CodigoErrorRegistro><tikR:DescripcionErrorRegistro>Aviso</tikR:DescripcionErrorRegistro>')))]);

        $r = (new EnviadorAeat())->enviarPendientes();

        $this->assertSame([2, 0, 1, 1], [$r['enviados'], $r['aceptados'], $r['con_errores'], $r['rechazados']]);
        $uno = RegistroFacturacion::where('num_serie', 'F2026-0001')->first();
        $this->assertSame(['rechazado', '1100', 'NIF no identificado'], [$uno->estado_envio, $uno->codigo_error, $uno->descripcion_error]);
        $this->assertSame('aceptado_errores', RegistroFacturacion::where('num_serie', 'F2026-0002')->first()->estado_envio);
    }

    public function test_un_registro_duplicado_en_la_aeat_cuenta_como_ya_enviado(): void
    {
        $this->emitirDos();
        $dup = '<tikR:CodigoErrorRegistro>3000</tikR:CodigoErrorRegistro><tikR:DescripcionErrorRegistro>Duplicado</tikR:DescripcionErrorRegistro><tikR:RegistroDuplicado><sf:IdPeticionRegistroDuplicado>X</sf:IdPeticionRegistroDuplicado><sf:EstadoRegistroDuplicado>Correcta</sf:EstadoRegistroDuplicado></tikR:RegistroDuplicado>';
        Http::fake(['*' => Http::response($this->respuesta('ParcialmenteCorrecto', $this->linea('F2026-0001', 'Incorrecto', 'Alta', $dup).$this->linea('F2026-0002', 'Correcto')))]);

        (new EnviadorAeat())->enviarPendientes();

        $uno = RegistroFacturacion::where('num_serie', 'F2026-0001')->first();
        $this->assertSame('aceptado', $uno->estado_envio);
        $this->assertNull($uno->descripcion_error);
    }

    public function test_un_fallo_del_servicio_no_pierde_los_registros(): void
    {
        $this->emitirDos();
        Http::fake(['*' => Http::response('<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"><env:Body><env:Fault><faultcode>env:Server</faultcode><faultstring>Certificado no válido</faultstring></env:Fault></env:Body></env:Envelope>', 500)]);

        $r = (new EnviadorAeat())->enviarPendientes();

        $this->assertSame(0, $r['enviados']);
        $this->assertStringContainsString('Certificado no válido', $r['mensaje']);
        foreach (RegistroFacturacion::all() as $registro) {
            $this->assertSame('pendiente', $registro->estado_envio);
            $this->assertSame(1, $registro->intentos);
            $this->assertStringContainsString('Certificado no válido', $registro->descripcion_error);
        }
    }

    public function test_una_caida_de_red_deja_los_registros_pendientes(): void
    {
        $this->emitirDos();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('sin conexión'));

        $r = (new EnviadorAeat())->enviarPendientes();

        $this->assertStringContainsString('No se pudo enviar', $r['mensaje']);
        $this->assertSame(2, RegistroFacturacion::where('estado_envio', 'pendiente')->count());
    }

    public function test_sin_certificado_no_sale_ninguna_peticion(): void
    {
        config(['verifactu.certificado' => null]);
        $this->emitirDos();

        $r = (new EnviadorAeat())->enviarPendientes();

        $this->assertStringContainsString('certificado', $r['mensaje']);
        Http::assertNothingSent();
    }

    public function test_el_resultado_se_interpreta_aunque_los_prefijos_cambien(): void
    {
        $r = ClienteAeat::interpretar($this->respuesta('Correcto', $this->linea('X-1', 'Correcto', 'Anulacion'), 45, 'CSV9'));

        $this->assertSame(['Correcto', 45, 'CSV9'], [$r['estado'], $r['espera'], $r['csv']]);
        $this->assertSame(['X-1', 'Anulacion', 'Correcto'], [$r['lineas'][0]['num_serie'], $r['lineas'][0]['operacion'], $r['lineas'][0]['estado']]);
        $this->assertNotNull(ClienteAeat::interpretar('esto no es xml')['error']);
    }

    public function test_el_comando_envia_y_el_de_estado_informa(): void
    {
        $this->emitirDos();
        Http::fake(['*' => Http::response($this->respuesta('Correcto', $this->linea('F2026-0001', 'Correcto').$this->linea('F2026-0002', 'Correcto')))]);

        $this->artisan('verifactu:enviar')->expectsOutputToContain('Enviados 2: 2 aceptados')->assertSuccessful();
        $this->artisan('verifactu:estado')->expectsOutputToContain('aceptado 2')->assertSuccessful();
    }
}
