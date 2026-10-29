<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CuotaCliente;
use App\Models\Expedient;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Support\Cobros\PendienteDeFacturar as P;
use App\Support\Cobros\PrepararFactura;
use App\Support\Verifactu\Facturacion;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreaFacturas;
use Tests\TestCase;

/** El programa detecta qué expedientes piden factura y prepara borradores (nunca emite). Con datos incompletos explica qué falta. */
class FacturacionAutomaticaTest extends TestCase
{
    use CreaFacturas, RefreshDatabase;

    private Client $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliente = $this->clienteDePrueba();
    }

    private function servicio(array $extra = []): Service
    {
        return Service::create($extra + ['codigo' => 'ALTA_TRAB'.random_int(100, 999), 'nombre' => 'Alta de trabajador', 'precio_base' => 45, 'iva' => 21, 'tipo' => 'sin_cita', 'facturable' => true, 'tipo_servicio' => 'Facturable']);
    }

    private function expediente(?Service $s, string $estado = 'finalizado', array $extra = []): Expedient
    {
        return Expedient::create($extra + ['codigo' => '2026-'.random_int(1000, 9999), 'client_id' => $this->cliente->id, 'service_id' => $s?->id, 'titulo' => 'ALTA DE UN TRABAJADOR', 'estado' => $estado]);
    }

    public function test_un_servicio_con_tarifa_en_un_expediente_finalizado_esta_listo(): void
    {
        $r = P::evaluar($this->expediente($this->servicio()));

        $this->assertSame([P::LISTO, 45.0, 21.0], [$r['estado'], $r['importe'], $r['iva']]);
        $this->assertStringContainsString('45,00 €', $r['motivo']);
    }

    public function test_cada_dato_que_falta_se_explica_sin_fallar(): void
    {
        $casos = [
            P::FALTA_SERVICIO => $this->expediente(null),
            P::FALTA_TARIFA => $this->expediente($this->servicio(['precio_base' => 0])),
            P::A_PRESUPUESTAR => $this->expediente($this->servicio(['tipo_servicio' => 'A presupuestar'])),
            P::SIN_FACTURA => $this->expediente($this->servicio(['tipo_servicio' => 'Incluido en cuota'])),
            P::ESPERA => $this->expediente($this->servicio(), 'abierto'),
        ];
        foreach ($casos as $esperado => $e) {
            $this->assertSame($esperado, P::evaluar($e)['estado'], $esperado);
            $this->assertNotSame('', P::evaluar($e)['motivo']);
        }
        $this->assertSame(P::FALTA_TARIFA, P::evaluar($this->expediente($this->servicio(['iva' => 13])))['estado']);
    }

    public function test_el_momento_de_facturacion_lo_decide_el_servicio_o_la_configuracion(): void
    {
        $alAbrir = $this->expediente($this->servicio(['momento_facturacion' => 'apertura']), 'abierto');
        $this->assertSame(P::LISTO, P::evaluar($alAbrir)['estado']);

        config(['facturacion.momento_por_defecto' => 'apertura']);
        $this->assertSame(P::LISTO, P::evaluar($this->expediente($this->servicio(), 'abierto'))['estado']);
    }

    public function test_preparar_crea_un_borrador_con_la_tarifa_y_no_se_duplica(): void
    {
        $e = $this->expediente($this->servicio());

        $f = PrepararFactura::desdeExpediente($e);
        $otra = PrepararFactura::desdeExpediente($e);

        $this->assertSame($f->id, $otra->id);
        $this->assertSame(1, Invoice::count());
        $this->assertSame(['borrador', $this->cliente->id, $e->id], [$f->emision, $f->client_id, $f->expedient_id]);
        $l = $f->lines->first();
        $this->assertSame(['1', '45.00', '21.00', 'S1'], [(string) $l->cantidad, $l->precio_unitario, $l->tipo_iva, $l->calificacion]);
        $this->assertStringContainsString('Alta de trabajador', $l->concepto);
        $this->assertSame(P::BORRADOR, P::evaluar($e->fresh())['estado']);
    }

    public function test_emitida_la_factura_el_expediente_figura_como_facturado_y_una_anulada_vuelve_a_pedirla(): void
    {
        $e = $this->expediente($this->servicio());
        $f = Facturacion::emitir(PrepararFactura::desdeExpediente($e));
        $this->assertSame(P::FACTURADO, P::evaluar($e->fresh())['estado']);

        Facturacion::anular($f, 'Error');
        $this->assertSame(P::LISTO, P::evaluar($e->fresh())['estado']);
    }

    public function test_con_importe_por_pactar_el_borrador_forzado_queda_a_completar_y_no_se_puede_emitir(): void
    {
        $e = $this->expediente($this->servicio(['tipo_servicio' => 'A presupuestar']));

        $this->assertNull(PrepararFactura::desdeExpediente($e));
        $f = PrepararFactura::desdeExpediente($e, forzar: true);

        $this->assertSame('0.00', $f->lines->first()->precio_unitario);
        $this->assertNotEmpty(Facturacion::problemas($f->fresh(['client', 'lines'])));
    }

    public function test_evento_al_finalizar_un_expediente_se_prepara_el_borrador_si_esta_activado(): void
    {
        config(['facturacion.borradores_automaticos' => true]);
        $e = $this->expediente($this->servicio(), 'en_proceso');
        $this->assertSame(0, Invoice::count());

        $e->update(['estado' => 'finalizado', 'fecha_cierre' => now()]);

        $this->assertSame(1, Invoice::count());
        $this->assertSame('borrador', Invoice::first()->emision);
    }

    public function test_el_evento_no_actua_apagado_ni_en_cierres_antiguos_ni_en_importaciones(): void
    {
        $s = $this->servicio();
        $e = $this->expediente($s, 'en_proceso');
        $e->update(['estado' => 'finalizado']);                                         // apagado (como en pruebas)
        $this->assertSame(0, Invoice::count());

        config(['facturacion.borradores_automaticos' => true]);
        $this->expediente($s, 'finalizado');                                            // llega ya finalizado: historia
        $viejo = $this->expediente($s, 'en_proceso');
        $viejo->update(['estado' => 'finalizado', 'fecha_cierre' => now()->subDays(200)]); // cierre antiguo
        $this->assertSame(0, Invoice::count());
    }

    public function test_cuotas_de_fin_de_mes_una_vez_por_periodo_y_segun_su_periodicidad(): void
    {
        $mensual = CuotaCliente::create(['client_id' => $this->cliente->id, 'concepto' => 'Asesoría fiscal mensual', 'importe' => 100, 'iva' => 21, 'periodicidad' => 'mensual']);
        $trimestral = CuotaCliente::create(['client_id' => $this->cliente->id, 'concepto' => 'Cuota trimestral', 'importe' => 300, 'iva' => 21, 'periodicidad' => 'trimestral']);
        CuotaCliente::create(['client_id' => $this->cliente->id, 'concepto' => 'Pausada', 'importe' => 10, 'periodicidad' => 'mensual', 'activa' => false]);

        $this->assertSame(2, PrepararFactura::finDeMes(Carbon::parse('2026-10-31')));   // octubre: mensual y trimestral
        $this->assertSame(0, PrepararFactura::finDeMes(Carbon::parse('2026-10-31')));   // no se repite
        $this->assertSame(1, PrepararFactura::finDeMes(Carbon::parse('2026-11-30')));   // noviembre: solo la mensual

        $f = Invoice::where('cuota_id', $mensual->id)->where('periodo', '2026-10')->firstOrFail();
        $this->assertSame(['borrador', '100.00'], [$f->emision, $f->lines->first()->precio_unitario]);
        $this->assertSame(3, Invoice::count());
    }

    public function test_una_cuota_con_fechas_solo_toca_dentro_de_su_vigencia(): void
    {
        $c = CuotaCliente::create(['client_id' => $this->cliente->id, 'concepto' => 'Temporal', 'importe' => 50, 'periodicidad' => 'mensual', 'desde' => '2026-11-15', 'hasta' => '2026-12-31']);

        $this->assertFalse($c->tocaEn(Carbon::parse('2026-10-10')));
        $this->assertTrue($c->tocaEn(Carbon::parse('2026-11-10')));
        $this->assertFalse($c->tocaEn(Carbon::parse('2027-01-10')));
    }

    public function test_el_comando_de_fin_de_mes_prepara_borradores(): void
    {
        CuotaCliente::create(['client_id' => $this->cliente->id, 'concepto' => 'Cuota', 'importe' => 20, 'periodicidad' => 'mensual']);

        $this->artisan('facturas:fin-de-mes', ['--mes' => '2026-10'])->expectsOutputToContain('1 borrador(es) preparado(s) para 2026-10')->assertSuccessful();
        $this->artisan('facturas:fin-de-mes', ['--mes' => '2026-10'])->expectsOutputToContain('No había cuotas pendientes')->assertSuccessful();
    }

    public function test_el_panel_lista_lo_pendiente_y_prepara_borradores(): void
    {
        $this->seed(RolesSeeder::class);
        $worker = User::factory()->create();
        $worker->assignRole('worker');
        $listo = $this->expediente($this->servicio());
        $this->expediente(null);
        $this->expediente($this->servicio(), 'abierto');   // se factura al finalizar: no aparece

        $pagina = $this->actingAs($worker)->get('/panel/facturas/pendientes')->assertOk();
        $pagina->assertSee('Listo para preparar')->assertSee('Falta asignar el servicio')->assertSee('Preparar los 1 listos');
        $this->assertSame(2, substr_count($pagina->getContent(), 'Preparar borrador'));

        $this->actingAs($worker)->post("/panel/facturas/pendientes/{$listo->id}/preparar")->assertRedirect();
        $this->assertSame(1, Invoice::where('expedient_id', $listo->id)->count());

        $this->actingAs($worker)->get('/panel/facturas')->assertOk()->assertSee('Pendiente de facturar');
        $this->actingAs($worker)->post('/panel/facturas/pendientes/preparar-todos')->assertRedirect()->assertSessionHas('success');
    }
}
