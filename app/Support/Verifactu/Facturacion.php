<?php

namespace App\Support\Verifactu;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\RegistroFacturacion;
use App\Models\SerieFactura;
use App\Models\User;
use App\Support\Despacho;
use App\Support\Readiness;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Emisión, anulación y rectificación de facturas conforme a Veri*Factu. Es el único sitio que crea registros de
 * facturación: una factura se emite una vez, con su número correlativo y su registro de alta encadenado con la
 * huella del registro anterior; no se edita ni se borra; se anula con un registro de anulación o se corrige con
 * una rectificativa. Nunca envía nada por sí mismo: el envío a la AEAT lo hace EnviadorAeat con los registros pendientes.
 */
final class Facturacion
{
    public const TIPOS_IVA = [4.0, 5.0, 10.0, 21.0];
    public const TIPOS_RECTIFICATIVA = ['R1' => 'R1 · Error fundado en derecho y artículos 80.Uno, Dos y Seis LIVA', 'R4' => 'R4 · Resto de casos'];

    /**
     * Totales en céntimos, con el desglose por calificación y tipo de IVA que va en el registro.
     *
     * @return array{grupos: array<int, array{calificacion:string,tipo:float,base:int,cuota:int}>, base:int, cuota:int, total:int, retencion:int}
     */
    public static function totales(Invoice $f): array
    {
        $grupos = [];
        foreach ($f->lines as $linea) {
            $calificacion = $linea->calificacion ?: 'S1';
            $tipo = $calificacion === 'S1' ? round((float) $linea->tipo_iva, 2) : 0.0;
            $clave = $calificacion.'|'.$tipo;
            $base = RegistroXml::centimos($linea->cantidad) * RegistroXml::centimos($linea->precio_unitario) / 100;
            $grupos[$clave] ??= ['calificacion' => $calificacion, 'tipo' => $tipo, 'base' => 0, 'cuota' => 0];
            $grupos[$clave]['base'] += (int) round($base);
        }

        $baseSujeta = 0;
        foreach ($grupos as &$g) {
            $g['cuota'] = self::porcentaje($g['base'], $g['tipo']);
            if ($g['calificacion'] === 'S1') {
                $baseSujeta += $g['base'];
            }
        }
        unset($g);

        $base = array_sum(array_column($grupos, 'base'));
        $cuota = array_sum(array_column($grupos, 'cuota'));

        return [
            'grupos' => array_values($grupos),
            'base' => $base,
            'cuota' => $cuota,
            'total' => $base + $cuota,
            'retencion' => self::porcentaje($baseSujeta, (float) $f->retencion_irpf),
        ];
    }

    /** Porcentaje de un importe en céntimos, redondeado al céntimo (mitad hacia fuera del cero). */
    private static function porcentaje(int $centimos, float $porcentaje): int
    {
        $signo = $centimos < 0 ? -1 : 1;

        return $signo * intdiv(abs($centimos) * (int) round($porcentaje * 100) + 5000, 10000);
    }

    /** @return array<int, string> motivos por los que la factura aún no se puede emitir (vacío = se puede) */
    public static function problemas(Invoice $f): array
    {
        $p = [];
        if (! $f->esBorrador()) {
            return ['Esta factura ya no es un borrador.'];
        }

        $emisor = Despacho::datos();
        if (NifEspanol::exigible() && (Readiness::isPlaceholder($emisor['nombre']) || ! NifEspanol::valido($emisor['nif']))) {
            $p[] = 'Faltan el nombre o el NIF del despacho (Configuración → datos del despacho): el NIF debe ser válido.';
        }
        $p = array_merge($p, SistemaInformatico::problemas());

        $cliente = $f->client;
        if (! $cliente || trim((string) $cliente->razon_social) === '') {
            $p[] = 'Falta el cliente.';
        } elseif (NifEspanol::exigible() && ! NifEspanol::valido($cliente->nif)) {
            $p[] = 'El NIF del cliente no es válido: la AEAT rechaza las facturas con un NIF que no existe. Corrígelo en su ficha.';
        }

        if ($f->lines->isEmpty()) {
            $p[] = 'La factura no tiene líneas.';
        }
        foreach ($f->lines as $i => $l) {
            $n = $i + 1;
            if (trim((string) $l->concepto) === '') {
                $p[] = "La línea $n no tiene concepto.";
            }
            if ((int) $l->cantidad < 1) {
                $p[] = "La línea $n tiene una cantidad no válida.";
            }
            if (! $f->esRectificativa() && (float) $l->precio_unitario < 0) {
                $p[] = "La línea $n tiene un precio negativo; las correcciones se hacen con una factura rectificativa.";
            }
            if (! array_key_exists($l->calificacion ?: 'S1', InvoiceLine::CALIFICACIONES)) {
                $p[] = "La línea $n tiene una calificación no válida.";
            } elseif (($l->calificacion ?: 'S1') === 'S1' && ! in_array(round((float) $l->tipo_iva, 2), self::TIPOS_IVA, true)) {
                $p[] = "La línea $n tiene un tipo de IVA no válido (admitidos: 4, 5, 10 y 21 %).";
            }
        }
        if ($f->lines->isNotEmpty()) {
            $t = self::totales($f);
            if (! $f->esRectificativa() && $t['total'] <= 0) {
                $p[] = 'El importe total de la factura debe ser mayor que cero.';
            }
            if (abs($t['total']) >= 10 ** 14) {
                $p[] = 'El importe total es demasiado grande.';
            }
            if ((float) $f->retencion_irpf < 0 || (float) $f->retencion_irpf > 100) {
                $p[] = 'La retención de IRPF no es válida.';
            }
        }

        if ($f->esRectificativa()) {
            $original = $f->rectificada;
            if (! in_array($f->tipo_factura, array_keys(self::TIPOS_RECTIFICATIVA), true)) {
                $p[] = 'Tipo de factura rectificativa no válido.';
            }
            if (! in_array($f->tipo_rectificativa, ['S', 'I'], true)) {
                $p[] = 'Indica si la rectificativa es por sustitución o por diferencias.';
            }
            if (! $original || $original->esBorrador()) {
                $p[] = 'La factura que se rectifica debe estar emitida.';
            }
        }

        return $p;
    }

    public static function emitir(Invoice $factura, ?User $por = null): Invoice
    {
        $factura->loadMissing(['client', 'lines', 'rectificada']);
        if ($motivos = self::problemas($factura)) {
            throw new FacturaNoEmitible($motivos);
        }

        return Cache::lock('verifactu:cadena', 30)->block(20, fn () => DB::transaction(function () use ($factura, $por) {
            $f = Invoice::with(['client', 'lines', 'rectificada'])->lockForUpdate()->findOrFail($factura->id);
            if (! $f->esBorrador()) {
                throw new FacturaNoEmitible(['Esta factura ya se emitió.']);
            }

            $emisor = Despacho::datos();
            $nif = NifEspanol::paraRegistro($emisor['nif']);
            $ahora = Carbon::now('Europe/Madrid');
            $t = self::totales($f);
            $cuotaTotal = RegistroXml::importe($t['cuota']);
            $importeTotal = RegistroXml::importe($t['total']);

            [$serie, $secuencia] = self::siguienteNumero($f->esRectificativa() ? 'R' : 'F', $ahora->year);
            $numero = $serie.'-'.str_pad((string) $secuencia, 4, '0', STR_PAD_LEFT);
            $fecha = $ahora->format('d-m-Y');

            $f->update([
                'emision' => Invoice::EMITIDA,
                'serie' => $serie,
                'secuencia' => $secuencia,
                'numero' => $numero,
                'fecha' => $ahora->toDateString(),
                'emisor_nif' => $nif,
                'emisor_nombre' => $emisor['nombre'],
                'emisor_direccion' => $emisor['direccion'] ?: null,
                'receptor_nif' => NifEspanol::paraRegistro($f->client->nif),
                'receptor_nombre' => $f->client->razon_social,
                'receptor_direccion' => $f->client->direccion,
                'descripcion' => $f->descripcion ?: ($f->lines->first()->concepto ?? null),
                'base_imponible' => $t['base'] / 100,
                'cuota_iva' => $t['cuota'] / 100,
                'total' => $t['total'] / 100,
                'retencion_importe' => $t['retencion'] / 100,
                'iva' => (float) ($t['grupos'][0]['tipo'] ?? 0),
                'emitida_at' => $ahora,
                'emitida_por' => $por?->id,
                'qr_url' => QrTributario::url($nif, $numero, $fecha, $importeTotal),
            ]);

            $anterior = self::anterior($nif);
            $huella = Huella::alta([
                'emisor' => $nif, 'numSerie' => $numero, 'fecha' => $fecha, 'tipoFactura' => $f->tipo_factura,
                'cuotaTotal' => $cuotaTotal, 'importeTotal' => $importeTotal,
                'huellaAnterior' => $anterior['huella'] ?? null, 'fechaHora' => $ahora->format('Y-m-d\TH:i:sP'),
            ]);
            $r = ['emisor' => $nif, 'numSerie' => $numero, 'fecha' => $fecha, 'anterior' => $anterior, 'fechaHora' => $ahora->format('Y-m-d\TH:i:sP'),
                'huella' => $huella, 'cuotaTotal' => $cuotaTotal, 'importeTotal' => $importeTotal, 'huellaAnterior' => $anterior['huella'] ?? null];

            RegistroFacturacion::create([
                'tipo' => 'alta', 'invoice_id' => $f->id, 'emisor_nif' => $nif, 'num_serie' => $numero, 'fecha_expedicion' => $fecha,
                'tipo_factura' => $f->tipo_factura, 'cuota_total' => $cuotaTotal, 'importe_total' => $importeTotal,
                'huella_anterior' => $anterior['huella'] ?? null, 'fecha_hora_huso' => $r['fechaHora'], 'huella' => $huella,
                'xml' => RegistroXml::alta($f->fresh(['rectificada']), $t, $r),
            ]);

            return $f->fresh();
        }));
    }

    /** Anula una factura emitida con un registro de anulación; la factura queda en el sistema, marcada como anulada. */
    public static function anular(Invoice $factura, string $motivo, ?User $por = null): RegistroFacturacion
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new FacturaNoEmitible(['Indica el motivo de la anulación.']);
        }

        return Cache::lock('verifactu:cadena', 30)->block(20, fn () => DB::transaction(function () use ($factura, $motivo) {
            $f = Invoice::lockForUpdate()->findOrFail($factura->id);
            if ($f->emision !== Invoice::EMITIDA) {
                throw new FacturaNoEmitible(['Solo se puede anular una factura emitida.']);
            }
            $alta = $f->registros()->where('tipo', 'alta')->first();
            if (! $alta) {
                throw new LogicException('La factura emitida no tiene registro de alta.');
            }
            if ($alta->estado_envio === 'rechazado') {
                throw new FacturaNoEmitible(['El registro de esta factura fue rechazado por la AEAT y no existe allí: no se puede anular, revisa el error del envío.']);
            }

            $nif = NifEspanol::limpiar($f->emisor_nif);
            $ahora = Carbon::now('Europe/Madrid');
            $fecha = $f->fecha->format('d-m-Y');
            $anterior = self::anterior($nif);
            $fechaHora = $ahora->format('Y-m-d\TH:i:sP');
            $huella = Huella::anulacion(['emisor' => $nif, 'numSerie' => $f->numero, 'fecha' => $fecha, 'huellaAnterior' => $anterior['huella'] ?? null, 'fechaHora' => $fechaHora]);

            $registro = RegistroFacturacion::create([
                'tipo' => 'anulacion', 'invoice_id' => $f->id, 'emisor_nif' => $nif, 'num_serie' => $f->numero, 'fecha_expedicion' => $fecha,
                'huella_anterior' => $anterior['huella'] ?? null, 'fecha_hora_huso' => $fechaHora, 'huella' => $huella,
                'xml' => RegistroXml::anulacion(['emisor' => $nif, 'numSerie' => $f->numero, 'fecha' => $fecha, 'anterior' => $anterior, 'fechaHora' => $fechaHora, 'huella' => $huella]),
            ]);
            $f->update(['emision' => Invoice::ANULADA, 'anulada_at' => $ahora, 'anulada_motivo' => $motivo]);

            return $registro;
        }));
    }

    /** Crea el borrador de una factura rectificativa de una emitida. Por sustitución parte de sus líneas; por diferencias, sin líneas. */
    public static function crearRectificativa(Invoice $original, string $tipoRectificativa, string $clave = 'R1', ?string $motivo = null): Invoice
    {
        if (! in_array($original->emision, [Invoice::EMITIDA, Invoice::HISTORICA, Invoice::ANULADA], true)) {
            throw new FacturaNoEmitible(['Solo se rectifica una factura emitida.']);
        }
        if (! in_array($tipoRectificativa, ['S', 'I'], true) || ! array_key_exists($clave, self::TIPOS_RECTIFICATIVA)) {
            throw new FacturaNoEmitible(['Tipo de rectificativa no válido.']);
        }

        return DB::transaction(function () use ($original, $tipoRectificativa, $clave, $motivo) {
            $nueva = Invoice::create([
                'client_id' => $original->client_id,
                'expedient_id' => $original->expedient_id,
                'numero' => 'BORRADOR-'.bin2hex(random_bytes(6)),
                'fecha' => now('Europe/Madrid')->toDateString(),
                'estado' => 'pendiente',
                'emision' => Invoice::BORRADOR,
                'tipo_factura' => $clave,
                'tipo_rectificativa' => $tipoRectificativa,
                'rectifica_invoice_id' => $original->id,
                'motivo_rectificacion' => $motivo,
                'descripcion' => 'Rectificación de la factura '.$original->numero,
                'retencion_irpf' => (float) $original->retencion_irpf,
            ]);
            if ($tipoRectificativa === 'S') {
                foreach ($original->lines as $l) {
                    $nueva->lines()->create($l->only(['concepto', 'cantidad', 'precio_unitario', 'subtotal', 'tipo_iva', 'calificacion']));
                }
            }

            return $nueva;
        });
    }

    /** @return array{0:string,1:int} serie y siguiente número, sin huecos */
    private static function siguienteNumero(string $prefijo, int $anio): array
    {
        $codigo = $prefijo.$anio;
        SerieFactura::firstOrCreate(['codigo' => $codigo]);
        $serie = SerieFactura::where('codigo', $codigo)->lockForUpdate()->firstOrFail();
        $serie->increment('ultimo');

        return [$codigo, (int) $serie->fresh()->ultimo];
    }

    /** Datos del último registro de la cadena de este obligado, para encadenar el siguiente. */
    private static function anterior(string $nif): ?array
    {
        $r = RegistroFacturacion::where('emisor_nif', $nif)->orderByDesc('id')->first();

        return $r ? ['emisor' => $r->emisor_nif, 'numSerie' => $r->num_serie, 'fecha' => $r->fecha_expedicion, 'huella' => $r->huella] : null;
    }
}
