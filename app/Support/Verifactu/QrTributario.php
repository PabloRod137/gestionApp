<?php

namespace App\Support\Verifactu;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Código QR tributario de la factura (artículos 20 y 21 de la orden; «Detalle de las especificaciones técnicas del
 * código QR» de la AEAT v0.5.0): URL del servicio de cotejo con nif, numserie, fecha e importe codificados (UTF-8),
 * ISO/IEC 18004 con corrección de errores de nivel M, impreso entre 30x30 y 40x40 mm.
 */
final class QrTributario
{
    public const TEXTO_PREVIO = 'QR tributario:';
    public const TEXTO_VERIFICABLE = 'VERI*FACTU';

    public static function url(string $nif, string $numSerie, string $fechaDdMmAaaa, string $importe): string
    {
        $base = config('verifactu.urls.'.self::entorno().'.qr');

        return $base.'?'.implode('&', [
            'nif='.rawurlencode(trim($nif)),
            'numserie='.rawurlencode(trim($numSerie)),
            'fecha='.rawurlencode($fechaDdMmAaaa),
            'importe='.rawurlencode($importe),
        ]);
    }

    /** Imagen PNG en formato data URI, lista para <img> en el PDF. */
    public static function dataUri(string $url): string
    {
        $opciones = new QROptions([
            'eccLevel' => EccLevel::M,
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 8,
            'quietzoneSize' => 0,
        ]);

        return (new QRCode($opciones))->render($url);
    }

    private static function entorno(): string
    {
        return config('verifactu.entorno') === 'produccion' ? 'produccion' : 'pruebas';
    }
}
