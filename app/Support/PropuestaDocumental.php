<?php

namespace App\Support;

use App\Models\Expedient;

/**
 * Propuesta documental de un expediente a partir de su servicio. Son dos listas independientes:
 *  - lo que debe aportar el cliente (requisitos del servicio) y
 *  - los documentos que genera el despacho (modelos asociados al servicio).
 * Un requisito sin normalizar no se da por opcional: se muestra aparte como texto a revisar.
 */
class PropuestaDocumental
{
    /** @return array{estado: string, aportar: \Illuminate\Support\Collection, revisar: ?string, generar: \Illuminate\Support\Collection, accion: ?string} */
    public static function para(Expedient $expediente): array
    {
        $servicio = $expediente->service;
        if (! $servicio) {
            return ['estado' => 'sin_servicio', 'aportar' => collect(), 'revisar' => null, 'generar' => collect(), 'accion' => null];
        }

        $requisitos = $servicio->requirements;
        $normalizados = $requisitos->where('normalizado', true);

        return [
            'estado' => $servicio->genera_documento ? 'propuesta' : 'sin_documentos',
            // primero los obligatorios
            'aportar' => $normalizados->sortByDesc('obligatorio')->values(),
            'revisar' => $normalizados->isEmpty() ? ($servicio->documentacion_requerida ?: null) : null,
            'generar' => $servicio->genera_documento ? $servicio->templates()->where('document_templates.activo', true)->get() : collect(),
            'accion' => $servicio->accion_sugerida,
        ];
    }
}
