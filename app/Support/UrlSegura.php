<?php

namespace App\Support;

/**
 * Decide si el servidor puede descargar una dirección que le ha dado un tercero (por ejemplo, el adjunto de un webhook).
 * Sin esto, cualquiera podría hacer que el servidor pida direcciones internas (red del despacho, servicios locales) o enviar
 * claves a un sitio que controla. Solo https, solo servidores de la lista y nunca direcciones privadas o reservadas.
 */
class UrlSegura
{
    /** Solo para pruebas: sustituye la consulta de DNS. fn (string $host): array de IP. */
    public static ?\Closure $resolutor = null;

    /** @param  array<int, string>  $hostsPermitidos  nombres exactos o con comodín: "*.ejemplo.com" */
    public static function permitida(string $url, array $hostsPermitidos): bool
    {
        $partes = parse_url($url);
        if (! is_array($partes) || ($partes['scheme'] ?? '') !== 'https' || isset($partes['user']) || isset($partes['pass'])) {
            return false;
        }
        if (isset($partes['port']) && (int) $partes['port'] !== 443) {
            return false;
        }
        $host = strtolower((string) ($partes['host'] ?? ''));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (! self::enLista($host, $hostsPermitidos)) {
            return false;
        }
        $ips = self::$resolutor ? (self::$resolutor)($host) : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    private static function enLista(string $host, array $permitidos): bool
    {
        foreach ($permitidos as $patron) {
            $patron = strtolower(trim($patron));
            if ($patron === '') {
                continue;
            }
            if ($patron === $host || (str_starts_with($patron, '*.') && str_ends_with($host, substr($patron, 1)) && strlen($host) > strlen($patron) - 1)) {
                return true;
            }
        }

        return false;
    }
}
