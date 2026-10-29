<?php

namespace App\Support\Verifactu;

/**
 * Huella («hash») de los registros de facturación, según «Detalle de las especificaciones técnicas para generación de
 * la huella o hash de los registros de facturación» de la AEAT (v0.1.2): SHA-256 sobre una cadena
 * «campo=valor&campo=valor…» en UTF-8, salida hexadecimal en mayúsculas de 64 caracteres.
 * Los valores van sin espacios al inicio ni al final; un campo vacío se escribe «campo=».
 */
final class Huella
{
    /** @param array{emisor:string,numSerie:string,fecha:string,tipoFactura:string,cuotaTotal:string,importeTotal:string,huellaAnterior:?string,fechaHora:string} $d */
    public static function alta(array $d): string
    {
        return self::calcular(self::cadena([
            'IDEmisorFactura' => $d['emisor'],
            'NumSerieFactura' => $d['numSerie'],
            'FechaExpedicionFactura' => $d['fecha'],
            'TipoFactura' => $d['tipoFactura'],
            'CuotaTotal' => $d['cuotaTotal'],
            'ImporteTotal' => $d['importeTotal'],
            'Huella' => $d['huellaAnterior'] ?? '',
            'FechaHoraHusoGenRegistro' => $d['fechaHora'],
        ]));
    }

    /** @param array{emisor:string,numSerie:string,fecha:string,huellaAnterior:?string,fechaHora:string} $d */
    public static function anulacion(array $d): string
    {
        return self::calcular(self::cadena([
            'IDEmisorFacturaAnulada' => $d['emisor'],
            'NumSerieFacturaAnulada' => $d['numSerie'],
            'FechaExpedicionFacturaAnulada' => $d['fecha'],
            'Huella' => $d['huellaAnterior'] ?? '',
            'FechaHoraHusoGenRegistro' => $d['fechaHora'],
        ]));
    }

    /** @param array<string, string> $campos en el orden de la especificación */
    public static function cadena(array $campos): string
    {
        $partes = [];
        foreach ($campos as $nombre => $valor) {
            $partes[] = $nombre.'='.trim((string) $valor);
        }

        return implode('&', $partes);
    }

    public static function calcular(string $cadena): string
    {
        return strtoupper(hash('sha256', $cadena));
    }
}
