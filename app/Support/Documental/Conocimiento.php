<?php

namespace App\Support\Documental;

use App\Models\Client;
use App\Models\DocumentoIndexado;
use App\Models\Hallazgo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Mapa de conocimiento de UN cliente: qué dice su ficha, qué expedientes tiene, qué exige el servicio de cada uno, qué
 * modelos hay para generar y qué documentos suyos parecen cubrir cada requisito. Las coincidencias son solo pistas
 * («posible»), por nombre y texto del documento: las confirma una persona. Nunca mira documentos de otros clientes.
 */
final class Conocimiento
{
    private const CAMPOS_FICHA = ['nif' => 'NIF / CIF', 'email' => 'Correo', 'telefono' => 'Teléfono', 'direccion' => 'Dirección'];
    private const IGNORAR = ['para', 'como', 'esta', 'este', 'copia', 'documento', 'documentos', 'cliente', 'sobre', 'entre', 'desde', 'hasta', 'cada', 'otros', 'otras', 'segun', 'caso'];

    /** @return array<string, mixed> */
    public static function de(Client $c): array
    {
        $documentos = DocumentoIndexado::where('client_id', $c->id)->orderByDesc('id')->get();
        $analizados = $documentos->where('estado', 'analizado');
        $c->loadMissing('expedients.service.requirements', 'expedients.service.templates');

        $expedientes = $c->expedients->map(function ($e) use ($analizados) {
            $s = $e->service;
            $requisitos = $s ? $s->requirements->where('normalizado', true)->where('obligatorio', true)->values() : collect();

            return [
                'expediente' => $e,
                'servicio' => $s,
                'plantillas' => $s ? $s->templates->where('activo', true)->pluck('nombre')->all() : [],
                'requisitos' => $requisitos->map(fn ($r) => ['texto' => $r->descripcion, 'posibles' => self::posibles((string) $r->descripcion, $analizados)])->all(),
            ];
        })->all();

        return [
            'ficha' => collect(self::CAMPOS_FICHA)->map(fn ($etiqueta, $campo) => ['etiqueta' => $etiqueta, 'valor' => $c->{$campo}])->all(),
            'documentos' => [
                'total' => $documentos->count(),
                'por_estado' => $documentos->countBy('estado')->all(),
                'recientes' => $documentos->take(25),
            ],
            'hallazgos' => [
                'pendientes' => Hallazgo::where('client_id', $c->id)->where('estado', 'pendiente')->count(),
                'resueltos' => Hallazgo::where('client_id', $c->id)->whereIn('estado', ['aceptado', 'corregido', 'rechazado'])->count(),
            ],
            'expedientes' => $expedientes,
        ];
    }

    /** Documentos analizados cuyo nombre o texto contienen las palabras distintivas del requisito. @return array<int, string> nombres */
    public static function posibles(string $requisito, Collection $analizados): array
    {
        $palabras = collect(preg_split('/[^a-z0-9]+/', Str::ascii(mb_strtolower($requisito)), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($p) => strlen($p) >= 5 && ! in_array($p, self::IGNORAR, true))->unique()->values();
        if ($palabras->isEmpty()) {
            return [];
        }
        $exigidas = $palabras->count() === 1 ? 1 : 2;

        return $analizados->filter(function ($d) use ($palabras, $exigidas) {
            $pajar = Str::ascii(mb_strtolower($d->nombre.' '.mb_substr((string) $d->texto, 0, 60000)));

            return $palabras->filter(fn ($p) => str_contains($pajar, $p))->count() >= $exigidas;
        })->take(3)->pluck('nombre')->values()->all();
    }
}
