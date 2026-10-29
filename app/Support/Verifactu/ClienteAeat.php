<?php

namespace App\Support\Verifactu;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente del servicio web VERI*FACTU de la AEAT (SistemaFacturacion.wsdl, operación RegFactuSistemaFacturacion).
 * Envía un lote de registros ya generados y devuelve el resultado de cada uno. Autentica con el certificado
 * electrónico configurado (VERIFACTU_CERT_*). No envía nada si no hay certificado ni si el entorno no es el pedido.
 */
class ClienteAeat
{
    public function enviar(string $nif, string $nombre, array $registrosXml): array
    {
        $cert = config('verifactu.certificado');
        if (! $cert || ! is_file($cert)) {
            throw new RuntimeException('Falta el certificado electrónico (VERIFACTU_CERT_RUTA).');
        }

        $opciones = ['cert' => config('verifactu.certificado_clave') ? [$cert, config('verifactu.certificado_clave')] : $cert];
        if ($clave = config('verifactu.certificado_privada')) {
            $opciones['ssl_key'] = config('verifactu.certificado_clave') ? [$clave, config('verifactu.certificado_clave')] : $clave;
        }

        $respuesta = Http::withOptions($opciones)
            ->timeout(60)
            ->withHeaders(['SOAPAction' => '""'])
            ->withBody(self::sobre(RegistroXml::lote($nif, $nombre, $registrosXml)), 'text/xml; charset=utf-8')
            ->post($this->url());

        return self::interpretar($respuesta->body());
    }

    public function url(): string
    {
        $urls = config('verifactu.urls.'.(config('verifactu.entorno') === 'produccion' ? 'produccion' : 'pruebas'));

        return $urls['servicio'];
    }

    public static function sobre(string $lote): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Header/><soapenv:Body>'
            .preg_replace('/^<\?xml[^>]*\?>\s*/', '', $lote).'</soapenv:Body></soapenv:Envelope>';
    }

    /**
     * Resultado del envío: estado global, tiempo de espera, CSV y estado de cada registro.
     *
     * @return array{error:?string,estado:?string,espera:?int,csv:?string,lineas:array<int,array{num_serie:string,operacion:string,estado:string,codigo:?string,descripcion:?string,duplicado:?string}>}
     */
    public static function interpretar(string $xml): array
    {
        $doc = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $cargado = @$doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        if (! $cargado) {
            return ['error' => 'Respuesta de la AEAT ilegible.', 'estado' => null, 'espera' => null, 'csv' => null, 'lineas' => []];
        }

        $x = new DOMXPath($doc);
        $texto = fn (string $consulta, $contexto = null) => ($n = $x->query($consulta, $contexto)->item(0)) ? trim($n->textContent) : null;

        if ($falta = $texto('//*[local-name()="Fault"]/faultstring')) {
            return ['error' => 'Fallo del servicio: '.mb_substr($falta, 0, 300), 'estado' => null, 'espera' => null, 'csv' => null, 'lineas' => []];
        }

        $lineas = [];
        foreach ($x->query('//*[local-name()="RespuestaLinea"]') as $l) {
            $lineas[] = [
                'num_serie' => (string) $texto('.//*[local-name()="IDFactura"]/*[local-name()="NumSerieFactura"]', $l),
                'operacion' => (string) $texto('.//*[local-name()="Operacion"]/*[local-name()="TipoOperacion"]', $l),
                'estado' => (string) $texto('./*[local-name()="EstadoRegistro"]', $l),
                'codigo' => $texto('./*[local-name()="CodigoErrorRegistro"]', $l),
                'descripcion' => $texto('./*[local-name()="DescripcionErrorRegistro"]', $l),
                'duplicado' => $texto('.//*[local-name()="RegistroDuplicado"]/*[local-name()="EstadoRegistroDuplicado"]', $l),
            ];
        }
        $espera = $texto('//*[local-name()="TiempoEsperaEnvio"]');

        return [
            'error' => null,
            'estado' => $texto('//*[local-name()="EstadoEnvio"]'),
            'espera' => $espera !== null ? (int) $espera : null,
            'csv' => $texto('//*[local-name()="CSV"]'),
            'lineas' => $lineas,
        ];
    }
}
