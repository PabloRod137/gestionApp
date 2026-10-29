<?php

namespace App\Support\Documental;

use App\Models\Client;
use App\Support\DatosCliente;
use App\Support\Despacho;
use App\Support\Verifactu\NifEspanol;

/**
 * Busca en el texto de UN documento datos que podrían ser del cliente al que pertenece la carpeta (NIF/CIF, correo,
 * teléfono) y los compara con su ficha. Solo reglas, sin IA. Nunca escribe: devuelve propuestas que una persona acepta.
 *
 *  - ficha vacía y dato encontrado → «propuesta» (rellena un hueco);
 *  - ficha con otro dato y el de la ficha no aparece en el documento → «contradiccion»;
 *  - varios candidatos distintos del mismo campo → «posible tercero» y menos confianza (el documento menciona a más gente).
 * Los datos del propio despacho se ignoran.
 */
final class DetectorDatos
{
    private const NIF = '/\b([XYZ]\d{7}[A-Z]|\d{8}[A-Z]|[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J])\b/u';
    private const EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u';
    private const TELEFONO = '/(?<![\d])(?:\+34[\s.\-]?|0034[\s.\-]?)?([6789]\d{2}[\s.\-]?\d{3}[\s.\-]?\d{3})(?![\d])/u';

    /**
     * @param  array<int, string>  $paginas texto por página (la primera es la 1)
     * @return array<int, array{tipo:string,campo:string,valor:string,valor_actual:?string,confianza:int,pagina:?int,fragmento:?string,posible_tercero:bool}>
     */
    public static function detectar(Client $cliente, array $paginas): array
    {
        $despacho = Despacho::datos();
        $propios = [
            'nif' => [NifEspanol::limpiar($despacho['nif'])],
            'email' => [mb_strtolower((string) $despacho['email'])],
            'telefono' => [self::digitos($despacho['telefono'])],
        ];

        $candidatos = ['nif' => [], 'email' => [], 'telefono' => []];
        foreach ($paginas as $i => $texto) {
            $pag = $i + 1;
            foreach ([['nif', self::NIF], ['email', self::EMAIL], ['telefono', self::TELEFONO]] as [$campo, $regex]) {
                if (! preg_match_all($regex, $texto, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($m[$campo === 'telefono' ? 1 : 0] as [$crudo, $pos]) {
                    $valor = self::normalizar($campo, $crudo);
                    if ($valor === null || in_array($campo === 'telefono' ? self::digitos($valor) : $valor, array_filter($propios[$campo]), true)) {
                        continue;
                    }
                    $candidatos[$campo][$valor] ??= ['pagina' => $pag, 'fragmento' => self::fragmento($texto, (int) $pos, strlen($crudo))];
                }
            }
        }

        $resultado = [];
        foreach ($candidatos as $campo => $encontrados) {
            if (! $encontrados) {
                continue;
            }
            $actual = trim((string) $cliente->{$campo}) ?: null;
            $varios = count($encontrados) > 1;
            $actualAparece = $actual && array_key_exists(self::comparable($campo, $actual), array_combine(array_map(fn ($v) => self::comparable($campo, $v), array_keys($encontrados)), array_keys($encontrados)));
            foreach ($encontrados as $valor => $info) {
                if ($actual === null) {
                    $tipo = 'propuesta';
                } elseif (self::comparable($campo, $actual) === self::comparable($campo, $valor) || $actualAparece) {
                    continue;   // coincide con la ficha, o la ficha aparece en el documento: nada que proponer
                } else {
                    $tipo = 'contradiccion';
                }
                $resultado[] = [
                    'tipo' => $tipo, 'campo' => $campo, 'valor' => $valor, 'valor_actual' => $actual,
                    'confianza' => $varios ? 35 : 70, 'pagina' => $info['pagina'], 'fragmento' => $info['fragmento'], 'posible_tercero' => $varios,
                ];
            }
        }

        return $resultado;
    }

    private static function normalizar(string $campo, string $crudo): ?string
    {
        if ($campo === 'nif') {
            $v = NifEspanol::limpiar($crudo);

            return NifEspanol::valido($v) ? $v : null;
        }
        if ($campo === 'telefono') {
            return DatosCliente::normalizar('telefono', trim(preg_replace('/[\s.\-]+/', ' ', $crudo)));
        }

        return DatosCliente::normalizar('email', $crudo);
    }

    private static function comparable(string $campo, string $valor): string
    {
        return $campo === 'telefono' ? self::digitos($valor) : ($campo === 'email' ? mb_strtolower(trim($valor)) : NifEspanol::limpiar($valor));
    }

    private static function digitos(?string $v): string
    {
        $d = preg_replace('/\D/', '', (string) $v);

        return strlen($d) > 9 ? substr($d, -9) : $d;
    }

    private static function fragmento(string $texto, int $pos, int $largo): string
    {
        $ini = max(0, $pos - 40);
        $f = substr($texto, $ini, $largo + 80);

        return trim(preg_replace('/\s+/', ' ', mb_convert_encoding($f, 'UTF-8', 'UTF-8')));
    }
}
