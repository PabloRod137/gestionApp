<?php

namespace App\Support\Verifactu;

use App\Support\Despacho;
use App\Support\Readiness;

/**
 * Datos del sistema informático de facturación que van en cada registro (bloque SistemaInformatico) y que deben
 * coincidir con su declaración responsable. El productor es quien firma esa declaración: si no se indica en el
 * .env se usa el propio despacho, lo cual hay que confirmar con el gestor (ver docs/VERIFACTU.md).
 */
final class SistemaInformatico
{
    /** @return array{nombre:string,id:string,version:string,num_instalacion:string,productor_nombre:string,productor_nif:string,productor_por_defecto:bool} */
    public static function datos(): array
    {
        $c = config('verifactu.sistema');
        $despacho = Despacho::datos();
        $nombre = trim((string) $c['productor_nombre']);
        $nif = NifEspanol::limpiar($c['productor_nif']);
        $porDefecto = $nombre === '' || $nif === '';

        return [
            'nombre' => mb_substr($c['nombre'], 0, 30),
            'id' => mb_substr($c['id'], 0, 2),
            'version' => mb_substr($c['version'], 0, 50),
            'num_instalacion' => mb_substr((string) $c['num_instalacion'], 0, 100),
            'productor_nombre' => mb_substr($porDefecto ? (string) $despacho['nombre'] : $nombre, 0, 120),
            'productor_nif' => NifEspanol::paraRegistro($porDefecto ? $despacho['nif'] : $nif),
            'productor_por_defecto' => $porDefecto,
        ];
    }

    /** @return array<int, string> motivos por los que el sistema no puede identificarse ante la AEAT */
    public static function problemas(): array
    {
        $d = self::datos();
        $errores = [];
        if (NifEspanol::exigible() && (Readiness::isPlaceholder($d['productor_nombre']) || ! NifEspanol::valido($d['productor_nif']))) {
            $errores[] = 'Falta el NIF o el nombre del productor del programa (VERIFACTU_PRODUCTOR_NIF y VERIFACTU_PRODUCTOR_NOMBRE, o los datos legales del despacho).';
        }

        return $errores;
    }
}
