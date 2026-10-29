<?php

namespace App\Exports;

use App\Models\Expedient;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Clase de exportación de expedientes a Excel.
 */
class ExpedientsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * Retorna la colección de expedientes con relaciones cargadas para optimizar.
     */
    public function collection()
    {
        return Expedient::with(['client', 'worker', 'service'])->get();
    }

    /**
     * Define los encabezados del Excel.
     */
    public function headings(): array
    {
        return [
            'ID Expediente',
            'Referencia',
            'Título / Asunto',
            'Cliente',
            'Asesor Asignado',
            'Servicio Contratado',
            'Estado',
            'Fecha Apertura',
            'Fecha Cierre',
            'Descripción'
        ];
    }

    /**
     * Mapea cada expediente a la fila de Excel.
     *
     * @param Expedient $expedient
     */
    public function map($expedient): array
    {
        return [
            $expedient->id,
            '#EXP-' . str_pad($expedient->id, 5, '0', STR_PAD_LEFT),
            $expedient->titulo,
            $expedient->client->razon_social ?? '—',
            $expedient->worker->name ?? 'Sin asignar',
            $expedient->service->nombre ?? 'Sin servicio',
            strtoupper($expedient->estado),
            $expedient->fecha_apertura ? $expedient->fecha_apertura->format('d/m/Y') : '—',
            $expedient->fecha_cierre ? $expedient->fecha_cierre->format('d/m/Y') : '—',
            $expedient->descripcion ?? '—'
        ];
    }
}
