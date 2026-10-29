<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use App\Support\Sync\PuenteSharePoint;
use Illuminate\Database\QueryException;

/**
 * Datos de la ficha del cliente que pueden faltar y que el propio cliente puede aportar desde su área.
 * Regla única (la usan también los documentos): un dato en blanco se rellena; un dato ya guardado NUNCA se pisa.
 * Para cambiar uno ya guardado, el cliente se lo pide a su asesor.
 */
class DatosCliente
{
    private const REGLAS = [
        'nif' => ['NIF / CIF', '/^([0-9XYZ]\d{7}[A-Z]|[A-HJNPQRSUVW]\d{7}[0-9A-J])$/', 'Escribe un NIF, NIE o CIF válido (por ejemplo 12345678Z).'],
        'email' => ['Correo electrónico', null, 'Escribe un correo electrónico válido.'],
        'telefono' => ['Teléfono', '/^\+?[0-9 ]{9,15}$/', 'Escribe un teléfono de 9 a 15 cifras.'],
        'direccion' => ['Dirección', null, 'Escribe la dirección completa (calle, número, código postal y localidad).'],
    ];

    /** Etiquetas de los datos que se pueden aportar: campo => etiqueta. */
    public static function etiquetas(): array
    {
        return array_map(fn ($r) => $r[0], self::REGLAS);
    }

    /** Campos de la ficha que están en blanco: campo => etiqueta. */
    public static function faltan(Client $cliente): array
    {
        return array_filter(self::etiquetas(), fn ($_, $campo) => blank($cliente->{$campo}), ARRAY_FILTER_USE_BOTH);
    }

    /** Valor limpio y válido de un campo, o null si no sirve. */
    public static function normalizar(string $campo, mixed $valor): ?string
    {
        if (! isset(self::REGLAS[$campo]) || ! is_scalar($valor)) {
            return null;
        }
        $valor = trim((string) $valor);
        $valor = match ($campo) {
            'nif' => strtoupper(str_replace([' ', '-', '.'], '', $valor)),
            'telefono' => trim(preg_replace('/[\s.-]+/', ' ', $valor)),
            'email' => strtolower($valor),
            default => $valor,
        };
        $ok = match ($campo) {
            'email' => strlen($valor) <= 180 && filter_var($valor, FILTER_VALIDATE_EMAIL),
            'direccion' => mb_strlen($valor) >= 5 && mb_strlen($valor) <= 250,
            default => (bool) preg_match(self::REGLAS[$campo][1], $valor),
        };

        return $ok ? $valor : null;
    }

    public static function mensajeError(string $campo): string
    {
        return self::REGLAS[$campo][2] ?? 'Dato no válido.';
    }

    /**
     * Rellena en la ficha los datos que faltaban y son válidos. Devuelve los campos guardados.
     * Un valor en blanco no hace nada, uno ya guardado no se cambia y un NIF que ya usa otro cliente no se guarda.
     *
     * @param  array<string, mixed>  $valores campo => valor escrito
     * @return array<int, string>
     */
    public static function rellenar(Client $cliente, array $valores): array
    {
        $guardados = [];
        $nuevos = [];
        foreach (array_keys(self::faltan($cliente)) as $campo) {
            $valor = self::normalizar($campo, $valores[$campo] ?? null);
            if ($valor === null) {
                continue;
            }
            if ($campo === 'nif' && Client::withTrashed()->where('nif', $valor)->where('id', '!=', $cliente->id)->exists()) {
                continue;
            }
            $cliente->{$campo} = $valor;
            $nuevos[$campo] = $valor;
        }
        if (! $nuevos) {
            return [];
        }
        try {
            $cliente->save();
        } catch (QueryException) {
            return [];
        }

        return array_keys($nuevos);
    }

    /** Lo hace el cliente desde su área: se guarda, queda en la auditoría (solo los campos) y sale hacia SharePoint. */
    public static function aportar(Client $cliente, User $usuario, array $valores, ?string $ip = null): array
    {
        $guardados = self::rellenar($cliente, $valores);
        if ($guardados) {
            AuditLog::create([
                'user_id' => $usuario->id, 'accion' => 'datos_aportados_por_cliente', 'modelo' => 'Client', 'modelo_id' => $cliente->id,
                'datos_nuevos' => ['campos' => $guardados], 'ip' => $ip,
            ]);
            PuenteSharePoint::datosDeCliente($cliente, $guardados, $usuario);
        }

        return $guardados;
    }
}
