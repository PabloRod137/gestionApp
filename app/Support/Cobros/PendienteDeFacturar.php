<?php

namespace App\Support\Cobros;

use App\Models\Expedient;
use App\Models\Invoice;
use App\Support\Verifactu\Facturacion;
use Illuminate\Support\Collection;

/**
 * Qué expedientes piden una factura y por qué (o por qué aún no se puede preparar). Reglas, sin IA:
 * servicio facturable con tarifa, momento de facturación cumplido y sin factura ya. Con datos incompletos no falla:
 * dice qué falta, para poder completarlo cuando haya datos reales.
 */
final class PendienteDeFacturar
{
    public const LISTO = 'listo';
    public const BORRADOR = 'borrador';
    public const FACTURADO = 'facturado';
    public const A_PRESUPUESTAR = 'a_presupuestar';
    public const FALTA_TARIFA = 'falta_tarifa';
    public const FALTA_SERVICIO = 'falta_servicio';
    public const ESPERA = 'espera';
    public const SIN_FACTURA = 'sin_factura';

    public const ETIQUETAS = [
        self::LISTO => 'Listo para preparar',
        self::BORRADOR => 'Borrador preparado',
        self::FACTURADO => 'Ya facturado',
        self::A_PRESUPUESTAR => 'A presupuestar (importe por pactar)',
        self::FALTA_TARIFA => 'Falta la tarifa del servicio',
        self::FALTA_SERVICIO => 'Falta asignar el servicio',
        self::ESPERA => 'Se facturará más adelante',
        self::SIN_FACTURA => 'No se factura aparte',
    ];

    /** @return array{estado:string,motivo:string,importe:?float,iva:?float,factura:?Invoice,momento:?string} */
    public static function evaluar(Expedient $e): array
    {
        $e->loadMissing('service');
        $s = $e->service;

        $factura = Invoice::where('expedient_id', $e->id)
            ->where('tipo_factura', 'not like', 'R%')->where('emision', '!=', Invoice::ANULADA)
            ->orderByDesc('id')->first();
        if ($factura) {
            $borrador = $factura->esBorrador();

            return self::res($borrador ? self::BORRADOR : self::FACTURADO, $borrador ? 'Hay un borrador pendiente de revisar y emitir.' : 'Factura '.$factura->numero.'.', $factura);
        }

        if (! $s) {
            return self::res(self::FALTA_SERVICIO, 'El expediente no tiene servicio asignado: sin servicio no hay tarifa.');
        }
        if (! $s->facturable || in_array($s->tipo_servicio, config('facturacion.sin_factura_aparte', []), true)) {
            return self::res(self::SIN_FACTURA, 'El servicio «'.$s->nombre.'» no se factura aparte ('.($s->tipo_servicio ?: 'no facturable').').');
        }

        $momento = $s->momento_facturacion ?: config('facturacion.momento_por_defecto', 'finalizado');
        if ($momento === 'finalizado' && $e->estado !== 'finalizado') {
            return self::res(self::ESPERA, 'Se factura al finalizar el expediente (ahora: '.str_replace('_', ' ', $e->estado).').', null, $momento);
        }

        if ($s->tipo_servicio === 'A presupuestar') {
            return self::res(self::A_PRESUPUESTAR, 'El importe de «'.$s->nombre.'» se pacta caso por caso: presupuesta y fija el precio.', null, $momento);
        }
        $precio = (float) $s->precio_base;
        if ($precio <= 0) {
            return self::res(self::FALTA_TARIFA, 'El servicio «'.$s->nombre.'» no tiene precio en la tarifa.', null, $momento);
        }
        $iva = $s->iva === null ? 21.0 : round((float) $s->iva, 2);
        if (! in_array($iva, Facturacion::TIPOS_IVA, true)) {
            return self::res(self::FALTA_TARIFA, 'El IVA del servicio ('.$iva.' %) no es uno de los admitidos (4, 5, 10, 21).', null, $momento);
        }

        return self::res(self::LISTO, 'Servicio «'.$s->nombre.'»: '.number_format($precio, 2, ',', '.').' € + '.rtrim(rtrim(number_format($iva, 2, ',', ''), '0'), ',').' % de IVA.', null, $momento, $precio, $iva);
    }

    /** Expedientes que piden atención: todo salvo «ya facturado», «no se factura» y «se facturará más adelante». */
    public static function lista(): Collection
    {
        return Expedient::with(['client', 'service'])->orderByDesc('id')->get()
            ->map(fn (Expedient $e) => ['expediente' => $e] + self::evaluar($e))
            ->reject(fn ($f) => in_array($f['estado'], [self::FACTURADO, self::SIN_FACTURA, self::ESPERA], true)
                || ($f['estado'] === self::FALTA_SERVICIO && $f['expediente']->estado !== 'finalizado'))
            ->values();
    }

    private static function res(string $estado, string $motivo, ?Invoice $factura = null, ?string $momento = null, ?float $importe = null, ?float $iva = null): array
    {
        return ['estado' => $estado, 'motivo' => $motivo, 'importe' => $importe, 'iva' => $iva, 'factura' => $factura, 'momento' => $momento];
    }
}
