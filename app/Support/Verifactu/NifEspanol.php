<?php

namespace App\Support\Verifactu;

/** NIF/NIE/CIF con dígito de control: la AEAT valida los NIF contra su base centralizada y rechaza los que no existen. */
final class NifEspanol
{
    private const LETRAS_DNI = 'TRWAGMYFPDXBNJZSQVHLCKE';

    /** NIF provisional que se usa en el entorno de pruebas cuando falta el real (9 caracteres, no válido a propósito). */
    public const PROVISIONAL = '00000000T';

    /** Solo en producción se exigen NIF válidos; en pruebas se puede emitir sin ellos (los registros llevan un NIF provisional). */
    public static function exigible(): bool
    {
        return config('verifactu.entorno') === 'produccion';
    }

    /** NIF limpio para un registro: el real si es válido, o el provisional si aún estamos en pruebas. */
    public static function paraRegistro(?string $nif): string
    {
        $nif = self::limpiar($nif);

        return self::valido($nif) || self::exigible() ? $nif : self::PROVISIONAL;
    }

    public static function limpiar(?string $nif): string
    {
        return strtoupper(str_replace([' ', '-', '.', '/'], '', trim((string) $nif)));
    }

    public static function valido(?string $nif): bool
    {
        $nif = self::limpiar($nif);

        if (preg_match('/^(\d{8})([A-Z])$/', $nif, $m)) {                       // DNI
            return self::LETRAS_DNI[(int) $m[1] % 23] === $m[2];
        }
        if (preg_match('/^([KLM])(\d{7})([A-Z])$/', $nif, $m)) {                // K, L, M: números sin DNI (se valida como DNI)
            return self::LETRAS_DNI[(int) $m[2] % 23] === $m[3];
        }
        if (preg_match('/^([XYZ])(\d{7})([A-Z])$/', $nif, $m)) {               // NIE
            return self::LETRAS_DNI[(int) (strtr($m[1], 'XYZ', '012').$m[2]) % 23] === $m[3];
        }
        if (preg_match('/^([ABCDEFGHJNPQRSUVW])(\d{7})([0-9A-J])$/', $nif, $m)) { // CIF
            return in_array($m[3], self::controlCif($m[1], $m[2]), true);
        }

        return false;
    }

    /** @return array<int, string> dígitos o letras de control admitidos para esa clase de entidad */
    private static function controlCif(string $letra, string $digitos): array
    {
        $suma = 0;
        foreach (str_split($digitos) as $i => $d) {
            $d = (int) $d;
            if ($i % 2 === 0) {            // posiciones impares (1.ª, 3.ª…): doble y suma de cifras
                $d *= 2;
                $d = intdiv($d, 10) + $d % 10;
            }
            $suma += $d;
        }
        $control = (10 - $suma % 10) % 10;

        // Entidades públicas y sociedades extranjeras llevan letra; las demás, número (algunas admiten ambos)
        if (str_contains('PQRSNW', $letra)) {
            return ['JABCDEFGHI'[$control]];
        }
        if (str_contains('ABEH', $letra)) {
            return [(string) $control];
        }

        return [(string) $control, 'JABCDEFGHI'[$control]];
    }
}
