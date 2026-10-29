<?php

namespace App\Support\Cobros;

use App\Models\CuotaCliente;
use App\Models\Expedient;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Prepara BORRADORES de factura a partir de hechos (un expediente, una cuota). Nunca emite: una persona revisa el
 * borrador y lo emite en Facturación. Es idempotente: no duplica un borrador ni factura ya preparados.
 */
final class PrepararFactura
{
    /**
     * @param  bool  $forzar  también cuando el importe está por pactar o falta la tarifa o el servicio (el borrador queda con importe 0 para completarlo)
     */
    public static function desdeExpediente(Expedient $e, bool $forzar = false): ?Invoice
    {
        $r = PendienteDeFacturar::evaluar($e);
        if ($r['estado'] === PendienteDeFacturar::BORRADOR) {
            return $r['factura'];
        }
        $permitidos = [PendienteDeFacturar::LISTO];
        if ($forzar) {
            $permitidos = [...$permitidos, PendienteDeFacturar::A_PRESUPUESTAR, PendienteDeFacturar::FALTA_TARIFA, PendienteDeFacturar::FALTA_SERVICIO, PendienteDeFacturar::ESPERA];
        }
        if (! in_array($r['estado'], $permitidos, true)) {
            return null;
        }
        $e->loadMissing('service', 'client');
        $concepto = ($e->service?->nombre ?: $e->titulo).' (expediente '.$e->codigo.')';

        return DB::transaction(function () use ($e, $r, $concepto) {
            $f = Invoice::create([
                'client_id' => $e->client_id, 'expedient_id' => $e->id,
                'numero' => 'BORRADOR-'.bin2hex(random_bytes(6)), 'fecha' => now('Europe/Madrid')->toDateString(),
                'estado' => 'pendiente', 'emision' => Invoice::BORRADOR, 'tipo_factura' => 'F1',
                'descripcion' => mb_substr($concepto, 0, 500), 'retencion_irpf' => (float) config('facturacion.retencion_irpf', 0),
            ]);
            $precio = $r['importe'] ?? 0.0;
            $f->lines()->create([
                'concepto' => mb_substr($concepto, 0, 255), 'cantidad' => 1, 'precio_unitario' => $precio, 'subtotal' => $precio,
                'tipo_iva' => $r['iva'] ?? 21, 'calificacion' => 'S1',
            ]);

            return $f;
        });
    }

    /** Borrador de una cuota periódica para el mes dado; una cuota solo se factura una vez por periodo. */
    public static function desdeCuota(CuotaCliente $c, Carbon $mes): ?Invoice
    {
        if (! $c->tocaEn($mes)) {
            return null;
        }
        $periodo = $mes->format('Y-m');
        if (Invoice::where('cuota_id', $c->id)->where('periodo', $periodo)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($c, $periodo) {
            $f = Invoice::create([
                'client_id' => $c->client_id, 'numero' => 'BORRADOR-'.bin2hex(random_bytes(6)), 'fecha' => now('Europe/Madrid')->toDateString(),
                'estado' => 'pendiente', 'emision' => Invoice::BORRADOR, 'tipo_factura' => 'F1', 'cuota_id' => $c->id, 'periodo' => $periodo,
                'descripcion' => mb_substr($c->concepto.' · '.$periodo, 0, 500), 'retencion_irpf' => (float) config('facturacion.retencion_irpf', 0),
            ]);
            $f->lines()->create([
                'concepto' => mb_substr($c->concepto.' ('.$periodo.')', 0, 255), 'cantidad' => 1, 'precio_unitario' => $c->importe,
                'subtotal' => $c->importe, 'tipo_iva' => $c->iva, 'calificacion' => 'S1',
            ]);

            return $f;
        });
    }

    /** Borradores de todas las cuotas que tocan en el mes. @return int borradores creados */
    public static function finDeMes(Carbon $mes): int
    {
        $n = 0;
        foreach (CuotaCliente::where('activa', true)->get() as $cuota) {
            if (self::desdeCuota($cuota, $mes)) {
                $n++;
            }
        }

        return $n;
    }
}
