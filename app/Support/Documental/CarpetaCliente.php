<?php

namespace App\Support\Documental;

use App\Models\Client;

/**
 * Carpeta de UN cliente dentro de la raíz documental. La ruta de SharePoint del cliente apunta a `…/01_CLIENTES/<cliente>/…`:
 * la carpeta del cliente es hasta ese nivel, nunca una subcarpeta (por ejemplo `00_FACTURAS`) ni la de otro cliente.
 * Rechaza rutas con «..» y cualquier cosa que quede fuera de la raíz.
 */
final class CarpetaCliente
{
    /** Ruta absoluta de la carpeta del cliente, o null si no hay raíz, ruta o carpeta válidas. */
    public static function de(Client $cliente, ?string $raiz = null): ?string
    {
        $raiz ??= config('documental.raiz');
        $ruta = (string) $cliente->ruta_sharepoint;
        if (! $raiz || ! is_dir($raiz) || $ruta === '') {
            return null;
        }
        $partes = array_values(array_filter(explode('/', str_replace('\\', '/', $ruta)), fn ($p) => ! in_array($p, ['', '.'], true)));
        if (in_array('..', $partes, true)) {
            return null;
        }
        if ($partes && mb_strtolower($partes[0]) === 'documentos compartidos') {
            array_shift($partes);
        }
        $i = null;
        foreach ($partes as $k => $p) {
            if (mb_strtolower($p) === '01_clientes') {
                $i = $k;
                break;
            }
        }
        if ($i === null || ! isset($partes[$i + 1])) {
            return null;
        }
        $carpeta = realpath($raiz.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, array_slice($partes, 0, $i + 2)));
        $rootReal = realpath($raiz);
        if (! $carpeta || ! $rootReal || ! str_starts_with($carpeta, $rootReal.DIRECTORY_SEPARATOR) || ! is_dir($carpeta)) {
            return null;
        }

        return $carpeta;
    }
}
