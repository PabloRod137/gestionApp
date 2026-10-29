<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\RegistroFacturacion;
use App\Models\Setting;
use App\Support\Verifactu\FacturaNoEmitible;
use App\Support\Verifactu\Facturacion;
use App\Support\Verifactu\Huella;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\CreaFacturas;
use Tests\TestCase;

class VerifactuFacturacionTest extends TestCase
{
    use CreaFacturas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 10:15:30', 'Europe/Madrid'));
        $this->datosDelDespacho();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_emitir_numera_correlativamente_y_congela_los_datos(): void
    {
        $cliente = $this->clienteDePrueba();

        $a = Facturacion::emitir($this->borrador($cliente));
        $b = Facturacion::emitir($this->borrador($cliente));

        $this->assertSame(['F2026-0001', 'F2026-0002'], [$a->numero, $b->numero]);
        $this->assertSame('emitida', $a->emision);
        $this->assertSame('B12345674', $a->emisor_nif);
        $this->assertSame('Despacho de Prueba S.L.', $a->emisor_nombre);
        $this->assertSame('89890001K', $a->receptor_nif);
        $this->assertSame('2026-10-06', $a->fecha->toDateString());
        $this->assertStringContainsString('numserie=F2026-0001&fecha=06-10-2026&importe=121.00', $a->qr_url);
    }

    public function test_desglose_con_varios_tipos_de_iva_suplidos_y_retencion(): void
    {
        $f = $this->borrador($this->clienteDePrueba(), [
            ['Honorarios', 1, 1000.0, 21], ['Tasas judiciales', 1, 150.0, 0, 'N1'], ['Consulta reducida', 3, 33.33, 10],
        ], ['retencion_irpf' => 15]);

        $f = Facturacion::emitir($f);

        $this->assertSame('1249.99', $f->base_imponible);   // 1000 + 150 + 99,99
        $this->assertSame('220.00', $f->cuota_iva);          // 210 + 9,999 redondeado a 10,00
        $this->assertSame('1469.99', $f->total);
        $this->assertSame('165.00', $f->retencion_importe);  // 15 % de la base sujeta (1099,99): 164,9985 -> 165,00
        $this->assertSame('1304.99', $f->total_a_pagar);
    }

    public function test_el_registro_de_alta_lleva_la_huella_correcta_y_la_cadena_empieza_vacia(): void
    {
        $f = Facturacion::emitir($this->borrador($this->clienteDePrueba()));
        $r = $f->registros->first();

        $this->assertSame('alta', $r->tipo);
        $this->assertNull($r->huella_anterior);
        $this->assertSame(Huella::alta([
            'emisor' => 'B12345674', 'numSerie' => 'F2026-0001', 'fecha' => '06-10-2026', 'tipoFactura' => 'F1',
            'cuotaTotal' => '21.00', 'importeTotal' => '121.00', 'huellaAnterior' => null, 'fechaHora' => '2026-10-06T10:15:30+02:00',
        ]), $r->huella);
        $this->assertStringContainsString('<sf:PrimerRegistro>S</sf:PrimerRegistro>', $r->xml);
        $this->assertSame('pendiente', $r->estado_envio);
    }

    public function test_cada_registro_se_encadena_con_la_huella_del_anterior(): void
    {
        $cliente = $this->clienteDePrueba();
        $a = Facturacion::emitir($this->borrador($cliente));
        $b = Facturacion::emitir($this->borrador($cliente));
        $c = Facturacion::anular($b, 'Emitida por error');

        $ra = $a->registros->first();
        $rb = $b->registros->first();
        $this->assertSame($ra->huella, $rb->huella_anterior);
        $this->assertSame($rb->huella, $c->huella_anterior);
        $this->assertStringContainsString('<sf:Huella>'.$ra->huella.'</sf:Huella>', $rb->xml);
    }

    public function test_los_registros_son_validos_segun_los_esquemas_oficiales_de_la_aeat(): void
    {
        $cliente = $this->clienteDePrueba();
        $a = Facturacion::emitir($this->borrador($cliente, [['Honorarios', 2, 150.5, 21], ['Suplido', 1, 20.0, 0, 'N1'], ['Exenta', 1, 10.0, 0, 'E1']]));
        $anulacion = Facturacion::anular(Facturacion::emitir($this->borrador($cliente)), 'Error');

        $this->assertSame([], $this->erroresXsd($a->registros->first()->xml, $anulacion->xml));
    }

    public function test_rectificativas_por_sustitucion_y_por_diferencias_son_validas(): void
    {
        $original = Facturacion::emitir($this->borrador($this->clienteDePrueba()));

        $sustitutiva = Facturacion::crearRectificativa($original, 'S');
        $sustitutiva->lines()->first()->update(['precio_unitario' => 80, 'subtotal' => 80]);
        $s = Facturacion::emitir($sustitutiva->fresh());

        $dif = Facturacion::crearRectificativa($original, 'I', 'R4');
        $dif->lines()->create(['concepto' => 'Menos horas', 'cantidad' => 1, 'precio_unitario' => -20, 'subtotal' => -20, 'tipo_iva' => 21, 'calificacion' => 'S1']);
        $d = Facturacion::emitir($dif->fresh());

        $this->assertSame('R2026-0001', $s->numero);
        $this->assertSame('R2026-0002', $d->numero);
        $this->assertSame('-24.20', $d->total);
        $this->assertStringContainsString('<sf:TipoRectificativa>S</sf:TipoRectificativa>', $s->registros->first()->xml);
        $this->assertStringContainsString('<sf:BaseRectificada>100.00</sf:BaseRectificada>', $s->registros->first()->xml);
        $this->assertStringNotContainsString('ImporteRectificacion', $d->registros->first()->xml);
        $this->assertSame([], $this->erroresXsd($s->registros->first()->xml, $d->registros->first()->xml));
    }

    public function test_en_pruebas_se_emite_sin_nif_y_los_registros_llevan_un_nif_provisional(): void
    {
        Setting::setValue('company_nif', '');
        $cliente = $this->clienteDePrueba('SINNIF', 'Cliente sin NIF');

        $f = Facturacion::emitir($this->borrador($cliente));

        $this->assertSame('00000000T', $f->emisor_nif);
        $this->assertSame('00000000T', $f->receptor_nif);
        $this->assertSame([], $this->erroresXsd($f->registros->first()->xml));
    }

    public function test_en_produccion_no_se_emite_sin_nif_valido_del_cliente_ni_del_despacho_y_no_gasta_numero(): void
    {
        config(['verifactu.entorno' => 'produccion']);
        $malo = $this->borrador($this->clienteDePrueba('12345678A', 'Sin NIF válido'));

        try {
            Facturacion::emitir($malo);
            $this->fail('Debía rechazarse');
        } catch (FacturaNoEmitible $e) {
            $this->assertStringContainsString('NIF del cliente no es válido', $e->getMessage());
        }
        $this->assertSame('borrador', $malo->fresh()->emision);
        $this->assertSame(0, RegistroFacturacion::count());

        Setting::setValue('company_nif', '12345678A');
        $this->expectException(FacturaNoEmitible::class);
        Facturacion::emitir($this->borrador($this->clienteDePrueba()));
    }

    public function test_el_siguiente_numero_no_deja_huecos_tras_un_intento_fallido(): void
    {
        $cliente = $this->clienteDePrueba();
        try {
            Facturacion::emitir($this->borrador($cliente, [['', 1, 10.0]]));
        } catch (FacturaNoEmitible) {
        }

        $this->assertSame('F2026-0001', Facturacion::emitir($this->borrador($cliente))->numero);
    }

    public function test_una_factura_emitida_es_inalterable_pero_el_cobro_si_cambia(): void
    {
        $f = Facturacion::emitir($this->borrador($this->clienteDePrueba()));

        $f->update(['estado' => 'pagada']);
        $this->assertSame('pagada', $f->fresh()->estado);

        foreach (['numero' => 'F2026-9999', 'total' => 5, 'receptor_nif' => 'X', 'fecha' => '2020-01-01'] as $campo => $valor) {
            try {
                $f->fresh()->update([$campo => $valor]);
                $this->fail("Se pudo cambiar $campo");
            } catch (LogicException $e) {
                $this->assertStringContainsString('inalterable', $e->getMessage());
            }
        }
        $this->assertSame('F2026-0001', $f->fresh()->numero);
    }

    public function test_una_factura_emitida_no_se_borra_ni_se_tocan_sus_lineas(): void
    {
        $f = Facturacion::emitir($this->borrador($this->clienteDePrueba()));

        $acciones = [
            fn () => $f->lines()->first()->update(['precio_unitario' => 1]),
            fn () => $f->lines()->create(['concepto' => 'x', 'cantidad' => 1, 'precio_unitario' => 1, 'subtotal' => 1]),
            fn () => $f->lines()->first()->delete(),
            fn () => $f->fresh()->delete(),
        ];
        foreach ($acciones as $i => $accion) {
            try {
                $accion();
                $this->fail("La acción $i debía impedirse");
            } catch (LogicException) {
            }
        }
        $this->assertSame(1, $f->lines()->count());
        $this->assertNotNull(Invoice::find($f->id));
    }

    public function test_un_borrador_se_edita_y_se_borra_libremente(): void
    {
        $f = $this->borrador($this->clienteDePrueba());
        $f->update(['descripcion' => 'Cambiada']);
        $f->lines()->first()->update(['precio_unitario' => 5]);

        $f->delete();

        $this->assertNull(Invoice::find($f->id));
    }

    public function test_el_registro_de_facturacion_no_se_puede_alterar_ni_borrar_ni_en_la_base_de_datos(): void
    {
        $r = Facturacion::emitir($this->borrador($this->clienteDePrueba()))->registros->first();

        try {
            $r->update(['importe_total' => '1.00']);
            $this->fail('El modelo debía impedirlo');
        } catch (LogicException) {
        }
        try {
            $r->delete();
            $this->fail('El modelo debía impedirlo');
        } catch (LogicException) {
        }
        foreach (["update registros_facturacion set importe_total = '1.00'", "update registros_facturacion set huella = 'X'", 'delete from registros_facturacion'] as $sql) {
            try {
                DB::statement($sql);
                $this->fail("La base de datos permitió: $sql");
            } catch (QueryException $e) {
                $this->assertStringContainsString('inalterables', $e->getMessage());
            }
        }
        // el estado del envío sí cambia (lo actualiza el envío a la AEAT)
        $limpio = $r->fresh();
        $limpio->update(['estado_envio' => 'aceptado', 'csv' => 'ABC123']);
        $this->assertSame('aceptado', $r->fresh()->estado_envio);
    }

    public function test_anular_deja_la_factura_anulada_y_solo_una_vez(): void
    {
        $f = Facturacion::emitir($this->borrador($this->clienteDePrueba()));

        $reg = Facturacion::anular($f, 'Duplicada');

        $this->assertSame('anulacion', $reg->tipo);
        $this->assertSame('anulada', $f->fresh()->emision);
        $this->assertSame('Duplicada', $f->fresh()->anulada_motivo);
        $this->expectException(FacturaNoEmitible::class);
        Facturacion::anular($f->fresh(), 'Otra vez');
    }

    public function test_no_se_anula_sin_motivo_ni_un_borrador(): void
    {
        $cliente = $this->clienteDePrueba();
        $f = Facturacion::emitir($this->borrador($cliente));
        try {
            Facturacion::anular($f, '  ');
            $this->fail('Sin motivo');
        } catch (FacturaNoEmitible) {
        }
        $this->expectException(FacturaNoEmitible::class);
        Facturacion::anular($this->borrador($cliente), 'x');
    }

    public function test_no_se_anula_si_la_aeat_rechazo_el_alta(): void
    {
        $f = Facturacion::emitir($this->borrador($this->clienteDePrueba()));
        $f->registros->first()->update(['estado_envio' => 'rechazado']);

        $this->expectException(FacturaNoEmitible::class);
        Facturacion::anular($f->fresh(), 'x');
    }

    public function test_las_facturas_historicas_no_cambian_y_se_pueden_rectificar(): void
    {
        $h = Invoice::create(['client_id' => $this->clienteDePrueba()->id, 'numero' => 'VIEJA-1', 'fecha' => '2025-05-01', 'base_imponible' => 10, 'iva' => 21, 'total' => 12.1, 'estado' => 'pagada', 'emision' => 'historica']);

        $r = Facturacion::crearRectificativa($h, 'S');

        $this->assertSame($h->id, $r->rectifica_invoice_id);
        $this->expectException(LogicException::class);
        $h->update(['total' => 99]);
    }
}
