<?php

namespace App\Support\Verifactu;

use App\Models\RegistroFacturacion;
use App\Support\Despacho;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Envía a la AEAT los registros de facturación pendientes, en el orden de la cadena y respetando el control de
 * flujo (espera entre envíos y máximo de registros por envío). Un fallo de red o del servicio no pierde nada: los
 * registros siguen pendientes y se reintentan. Es la única parte que habla con la AEAT; no envía correos.
 */
class EnviadorAeat
{
    public function __construct(private readonly ClienteAeat $cliente = new ClienteAeat())
    {
    }

    /** @return array{enviados:int,aceptados:int,con_errores:int,rechazados:int,mensaje:?string} */
    public function enviarPendientes(bool $forzar = false): array
    {
        $resultado = ['enviados' => 0, 'aceptados' => 0, 'con_errores' => 0, 'rechazados' => 0, 'mensaje' => null];

        if (! config('verifactu.activo')) {
            return ['mensaje' => 'El envío a la AEAT está apagado (VERIFACTU_ACTIVO).'] + $resultado;
        }
        $emisor = Despacho::datos();
        $nif = NifEspanol::limpiar($emisor['nif']);
        if (! NifEspanol::valido($nif)) {
            return ['mensaje' => 'Falta el NIF válido del despacho.'] + $resultado;
        }

        $max = (int) config('verifactu.max_registros_envio');
        $pendientes = RegistroFacturacion::where('estado_envio', 'pendiente')->where('emisor_nif', $nif)->orderBy('id')->limit($max)->get();
        if ($pendientes->isEmpty()) {
            return $resultado;
        }

        // Control de flujo: esperar el tiempo indicado por la AEAT, salvo que ya haya un envío completo acumulado
        $espera = (int) Cache::get('verifactu:espera', config('verifactu.espera_envio'));
        $ultimo = Cache::get('verifactu:ultimo_envio');
        if (! $forzar && $ultimo && $pendientes->count() < $max && Carbon::parse($ultimo)->addSeconds($espera)->isFuture()) {
            return ['mensaje' => 'En espera entre envíos ('.$espera.' s).'] + $resultado;
        }

        $ahora = now();
        Cache::forever('verifactu:ultimo_envio', $ahora->toIso8601String());

        try {
            $r = $this->cliente->enviar($nif, $emisor['nombre'], $pendientes->pluck('xml')->all());
        } catch (Throwable $e) {
            $this->anotarFallo($pendientes, mb_substr($e->getMessage(), 0, 500), $ahora);

            return ['mensaje' => 'No se pudo enviar: '.$e->getMessage()] + $resultado;
        }

        if ($r['error']) {
            $this->anotarFallo($pendientes, $r['error'], $ahora);

            return ['mensaje' => $r['error']] + $resultado;
        }
        if ($r['espera']) {
            Cache::forever('verifactu:espera', $r['espera']);
        }

        foreach ($pendientes as $registro) {
            $linea = collect($r['lineas'])->first(fn ($l) => $l['num_serie'] === $registro->num_serie && strtolower($l['operacion']) === $registro->tipo);
            $registro->intentos++;
            $registro->ultimo_intento_at = $ahora;
            if (! $linea) {
                // La AEAT no devolvió este registro (p. ej. rechazo de todo el envío): sigue pendiente con el motivo
                $registro->descripcion_error = 'Sin respuesta para este registro (estado del envío: '.($r['estado'] ?? '—').').';
                $registro->save();

                continue;
            }
            $this->aplicar($registro, $linea, $r['csv'], $ahora);
            $resultado['enviados']++;
            match ($registro->estado_envio) {
                'aceptado' => $resultado['aceptados']++,
                'aceptado_errores' => $resultado['con_errores']++,
                'rechazado' => $resultado['rechazados']++,
                default => null,
            };
        }

        return $resultado;
    }

    /** @param array<string,mixed> $linea */
    private function aplicar(RegistroFacturacion $registro, array $linea, ?string $csv, Carbon $ahora): void
    {
        // Duplicado: la AEAT ya lo tenía (p. ej. se perdió la respuesta de un envío anterior); vale su estado
        if ($linea['duplicado']) {
            $estado = $linea['duplicado'] === 'AceptadaConErrores' ? 'aceptado_errores' : 'aceptado';
        } else {
            $estado = match ($linea['estado']) {
                'Correcto' => 'aceptado',
                'AceptadoConErrores' => 'aceptado_errores',
                default => 'rechazado',
            };
        }

        $registro->estado_envio = $estado;
        $registro->enviado_at = $ahora;
        $registro->csv = $csv ? mb_substr($csv, 0, 40) : $registro->csv;
        $registro->codigo_error = $linea['duplicado'] ? null : $linea['codigo'];
        $registro->descripcion_error = $linea['duplicado'] ? null : ($linea['descripcion'] ? mb_substr($linea['descripcion'], 0, 1500) : null);
        $registro->save();
    }

    private function anotarFallo($registros, string $motivo, Carbon $ahora): void
    {
        foreach ($registros as $registro) {
            $registro->intentos++;
            $registro->ultimo_intento_at = $ahora;
            $registro->descripcion_error = $motivo;
            $registro->save();
        }
    }
}
