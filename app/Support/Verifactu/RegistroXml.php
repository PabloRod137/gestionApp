<?php

namespace App\Support\Verifactu;

use App\Models\Invoice;
use DOMDocument;
use DOMElement;

/**
 * Construye el XML de los registros de facturación (alta y anulación) con el diseño de registro de la AEAT
 * (SuministroInformacion.xsd). El orden de los elementos es el del esquema.
 */
final class RegistroXml
{
    public const NS_SF = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';
    public const NS_LR = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';

    /**
     * @param  array<string, mixed>  $t  totales de Facturacion::totales()
     * @param  array{emisor:string,numSerie:string,fecha:string,huellaAnterior:?string,anterior:?array,fechaHora:string,huella:string,cuotaTotal:string,importeTotal:string}  $r
     */
    public static function alta(Invoice $f, array $t, array $r): string
    {
        $d = new DOMDocument('1.0', 'UTF-8');
        $raiz = $d->createElementNS(self::NS_SF, 'sf:RegistroAlta');
        $d->appendChild($raiz);

        self::el($d, $raiz, 'IDVersion', '1.0');
        $id = self::el($d, $raiz, 'IDFactura');
        self::el($d, $id, 'IDEmisorFactura', $r['emisor']);
        self::el($d, $id, 'NumSerieFactura', $r['numSerie']);
        self::el($d, $id, 'FechaExpedicionFactura', $r['fecha']);
        self::el($d, $raiz, 'NombreRazonEmisor', mb_substr((string) $f->emisor_nombre, 0, 120));
        self::el($d, $raiz, 'TipoFactura', $f->tipo_factura);

        if ($f->esRectificativa()) {
            self::el($d, $raiz, 'TipoRectificativa', $f->tipo_rectificativa);
            $original = $f->rectificada;
            $rec = self::el($d, $raiz, 'FacturasRectificadas');
            $idr = self::el($d, $rec, 'IDFacturaRectificada');
            self::el($d, $idr, 'IDEmisorFactura', NifEspanol::limpiar($original->emisor_nif ?: $f->emisor_nif));
            self::el($d, $idr, 'NumSerieFactura', $original->numero);
            self::el($d, $idr, 'FechaExpedicionFactura', $original->fecha->format('d-m-Y'));
            if ($f->tipo_rectificativa === 'S') {
                $imp = self::el($d, $raiz, 'ImporteRectificacion');
                self::el($d, $imp, 'BaseRectificada', self::importe(self::centimos($original->base_imponible)));
                self::el($d, $imp, 'CuotaRectificada', self::importe(self::centimos($original->cuota_iva)));
            }
        }

        if ($f->fecha_operacion) {
            self::el($d, $raiz, 'FechaOperacion', $f->fecha_operacion->format('d-m-Y'));
        }
        self::el($d, $raiz, 'DescripcionOperacion', mb_substr((string) ($f->descripcion ?: 'Servicios profesionales'), 0, 500));

        $dest = self::el($d, $raiz, 'Destinatarios');
        $idd = self::el($d, $dest, 'IDDestinatario');
        self::el($d, $idd, 'NombreRazon', mb_substr((string) $f->receptor_nombre, 0, 120));
        self::el($d, $idd, 'NIF', NifEspanol::limpiar($f->receptor_nif));

        $desglose = self::el($d, $raiz, 'Desglose');
        foreach ($t['grupos'] as $g) {
            $det = self::el($d, $desglose, 'DetalleDesglose');
            self::el($d, $det, 'Impuesto', '01');
            self::el($d, $det, 'ClaveRegimen', '01');
            if (str_starts_with($g['calificacion'], 'E')) {
                self::el($d, $det, 'OperacionExenta', $g['calificacion']);
            } else {
                self::el($d, $det, 'CalificacionOperacion', $g['calificacion']);
            }
            if ($g['calificacion'] === 'S1') {
                self::el($d, $det, 'TipoImpositivo', rtrim(rtrim(number_format($g['tipo'], 2, '.', ''), '0'), '.'));
            }
            self::el($d, $det, 'BaseImponibleOimporteNoSujeto', self::importe($g['base']));
            if ($g['calificacion'] === 'S1') {
                self::el($d, $det, 'CuotaRepercutida', self::importe($g['cuota']));
            }
        }

        self::el($d, $raiz, 'CuotaTotal', $r['cuotaTotal']);
        self::el($d, $raiz, 'ImporteTotal', $r['importeTotal']);
        self::encadenamiento($d, $raiz, $r['anterior']);
        self::sistema($d, $raiz);
        self::el($d, $raiz, 'FechaHoraHusoGenRegistro', $r['fechaHora']);
        self::el($d, $raiz, 'TipoHuella', '01');
        self::el($d, $raiz, 'Huella', $r['huella']);

        return $d->saveXML($raiz);
    }

    /** @param array{emisor:string,numSerie:string,fecha:string,anterior:?array,fechaHora:string,huella:string} $r */
    public static function anulacion(array $r): string
    {
        $d = new DOMDocument('1.0', 'UTF-8');
        $raiz = $d->createElementNS(self::NS_SF, 'sf:RegistroAnulacion');
        $d->appendChild($raiz);

        self::el($d, $raiz, 'IDVersion', '1.0');
        $id = self::el($d, $raiz, 'IDFactura');
        self::el($d, $id, 'IDEmisorFacturaAnulada', $r['emisor']);
        self::el($d, $id, 'NumSerieFacturaAnulada', $r['numSerie']);
        self::el($d, $id, 'FechaExpedicionFacturaAnulada', $r['fecha']);
        self::encadenamiento($d, $raiz, $r['anterior']);
        self::sistema($d, $raiz);
        self::el($d, $raiz, 'FechaHoraHusoGenRegistro', $r['fechaHora']);
        self::el($d, $raiz, 'TipoHuella', '01');
        self::el($d, $raiz, 'Huella', $r['huella']);

        return $d->saveXML($raiz);
    }

    /** Lote completo para el servicio web: cabecera con el obligado y los registros ya generados. */
    public static function lote(string $nif, string $nombre, array $registrosXml): string
    {
        $d = new DOMDocument('1.0', 'UTF-8');
        $raiz = $d->createElementNS(self::NS_LR, 'lr:RegFactuSistemaFacturacion');
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sf', self::NS_SF);
        $d->appendChild($raiz);

        $cab = $d->createElementNS(self::NS_LR, 'lr:Cabecera');
        $raiz->appendChild($cab);
        $obligado = $d->createElementNS(self::NS_SF, 'sf:ObligadoEmision');
        $cab->appendChild($obligado);
        $razon = $d->createElementNS(self::NS_SF, 'sf:NombreRazon');
        $razon->appendChild($d->createTextNode($nombre));
        $obligado->appendChild($razon);
        $obligado->appendChild($d->createElementNS(self::NS_SF, 'sf:NIF', $nif));

        foreach ($registrosXml as $xml) {
            $envoltorio = $d->createElementNS(self::NS_LR, 'lr:RegistroFactura');
            $raiz->appendChild($envoltorio);
            $fragmento = new DOMDocument('1.0', 'UTF-8');
            $fragmento->loadXML($xml);
            $envoltorio->appendChild($d->importNode($fragmento->documentElement, true));
        }

        return $d->saveXML($raiz);
    }

    private static function encadenamiento(DOMDocument $d, DOMElement $padre, ?array $anterior): void
    {
        $enc = self::el($d, $padre, 'Encadenamiento');
        if ($anterior === null) {
            self::el($d, $enc, 'PrimerRegistro', 'S');

            return;
        }
        $ra = self::el($d, $enc, 'RegistroAnterior');
        self::el($d, $ra, 'IDEmisorFactura', $anterior['emisor']);
        self::el($d, $ra, 'NumSerieFactura', $anterior['numSerie']);
        self::el($d, $ra, 'FechaExpedicionFactura', $anterior['fecha']);
        self::el($d, $ra, 'Huella', $anterior['huella']);
    }

    private static function sistema(DOMDocument $d, DOMElement $padre): void
    {
        $s = SistemaInformatico::datos();
        $si = self::el($d, $padre, 'SistemaInformatico');
        self::el($d, $si, 'NombreRazon', $s['productor_nombre']);
        self::el($d, $si, 'NIF', $s['productor_nif']);
        self::el($d, $si, 'NombreSistemaInformatico', $s['nombre']);
        self::el($d, $si, 'IdSistemaInformatico', $s['id']);
        self::el($d, $si, 'Version', $s['version']);
        self::el($d, $si, 'NumeroInstalacion', $s['num_instalacion']);
        self::el($d, $si, 'TipoUsoPosibleSoloVerifactu', 'S');
        self::el($d, $si, 'TipoUsoPosibleMultiOT', 'N');
        self::el($d, $si, 'IndicadorMultiplesOT', 'N');
    }

    private static function el(DOMDocument $d, DOMElement $padre, string $nombre, ?string $valor = null): DOMElement
    {
        $e = $d->createElementNS(self::NS_SF, 'sf:'.$nombre);
        if ($valor !== null) {
            $e->appendChild($d->createTextNode($valor));
        }
        $padre->appendChild($e);

        return $e;
    }

    public static function centimos(mixed $valor): int
    {
        return (int) bcmul(bcadd((string) $valor, '0', 2), '100', 0);
    }

    public static function importe(int $centimos): string
    {
        return ($centimos < 0 ? '-' : '').intdiv(abs($centimos), 100).'.'.str_pad((string) (abs($centimos) % 100), 2, '0', STR_PAD_LEFT);
    }
}
