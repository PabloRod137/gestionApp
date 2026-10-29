<?php

namespace App\Support;

use App\Models\Expedient;
use App\Models\Service;
use Illuminate\Support\Str;

/**
 * Sugiere el servicio probable de un expediente a partir de su asunto, sin IA:
 *  - palabras clave de la tarifa del despacho y nombre del servicio, y
 *  - lo que el despacho ya clasificó (asuntos de expedientes con servicio asignado).
 * Es solo una sugerencia ordenada: una persona la confirma.
 */
class ClasificadorServicio
{
    private const VACIAS = ['de', 'del', 'la', 'las', 'el', 'los', 'en', 'y', 'o', 'a', 'al', 'por', 'para', 'con', 'sin', 'un', 'una', 'su', 'se', 'que', 'cp', 'sl', 'slu'];

    /** @return array<int, array{servicio: Service, puntos: float, motivo: string, claro: bool}> */
    public function sugerir(string $asunto, ?int $excluirExpedienteId = null, int $max = 5): array
    {
        $tokens = $this->tokens($asunto);
        if (! $tokens) {
            return [];
        }
        $servicios = Service::where('activo', true)->get();
        $puntos = [];
        $motivos = [];

        // 1) palabras clave de la tarifa y nombre del servicio
        foreach ($servicios as $s) {
            foreach (array_filter(array_map('trim', explode(',', str_replace("\n", ',', (string) $s->palabras_clave)))) as $clave) {
                $kt = $this->tokens($clave);
                if ($kt && ! array_diff($kt, $tokens)) {
                    $puntos[$s->id] = ($puntos[$s->id] ?? 0) + count($kt);
                    $motivos[$s->id][] = $clave;
                }
            }
            $solapeNombre = array_intersect($this->tokens($s->nombre), $tokens);
            if ($solapeNombre) {
                $puntos[$s->id] = ($puntos[$s->id] ?? 0) + 0.5 * count($solapeNombre);
                $motivos[$s->id][] = implode(' ', $solapeNombre);
            }
        }

        // 2) lo ya clasificado por el despacho: una palabra pesa más cuanto menos servicios la usan
        $porServicio = [];
        Expedient::whereNotNull('service_id')->when($excluirExpedienteId, fn ($q) => $q->where('id', '!=', $excluirExpedienteId))
            ->get(['id', 'titulo', 'service_id'])->each(function ($e) use (&$porServicio) {
                foreach ($this->tokens((string) $e->titulo) as $t) {
                    $porServicio[$t][$e->service_id] = ($porServicio[$t][$e->service_id] ?? 0) + 1;
                }
            });
        $totalServicios = max(1, $servicios->count());
        foreach ($tokens as $t) {
            $usa = $porServicio[$t] ?? [];
            if (! $usa || count($usa) > 3) {
                continue;
            }
            $idf = log($totalServicios / count($usa)) * 0.8 / count($usa);
            foreach ($usa as $sid => $veces) {
                // cuantas más veces lo clasificó el despacho así, más pesa
                $puntos[$sid] = ($puntos[$sid] ?? 0) + $idf * (1 + 0.5 * ($veces - 1) + 0.5);
                $motivos[$sid][] = "«{$t}» en expedientes parecidos";
            }
        }

        arsort($puntos);
        $porId = $servicios->keyBy('id');
        $r = [];
        foreach ($puntos as $sid => $p) {
            if (isset($porId[$sid]) && $p > 0) {
                $r[] = ['servicio' => $porId[$sid], 'puntos' => round($p, 2), 'motivo' => implode('; ', array_unique($motivos[$sid])), 'claro' => false];
            }
            if (count($r) >= $max) {
                break;
            }
        }
        // claro: lo primero saca ventaja suficiente sobre lo segundo y pesa lo bastante
        if ($r) {
            $r[0]['claro'] = $r[0]['puntos'] >= 2 && (! isset($r[1]) || $r[0]['puntos'] >= 1.5 * $r[1]['puntos']);
        }

        return $r;
    }

    /** @return array<int, string> palabras sin tildes, en minúscula y reducidas a su raíz (certificados = certificado) */
    private function tokens(string $texto): array
    {
        $limpio = Str::of($texto)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
        $out = [];
        foreach (explode(' ', $limpio) as $p) {
            if ($p === '' || in_array($p, self::VACIAS, true) || (strlen($p) < 3 && ! ctype_digit($p))) {
                continue;
            }
            $p = strlen($p) > 4 ? rtrim($p, 's') : $p;
            $out[$p] = $p;
        }

        return array_values($out);
    }
}
