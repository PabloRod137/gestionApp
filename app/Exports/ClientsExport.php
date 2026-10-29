<?php

namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Clase de exportación de clientes a Excel.
 */
class ClientsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * Retorna la colección de todos los clientes.
     */
    public function collection()
    {
        return Client::all();
    }

    /**
     * Define los encabezados de las columnas del archivo Excel.
     */
    public function headings(): array
    {
        return [
            'ID',
            'NIF/CIF',
            'Razón Social',
            'Tipo',
            'Teléfono',
            'Dirección',
            'Notas',
            'Fecha de Registro'
        ];
    }

    /**
     * Mapea cada registro a las celdas correspondientes.
     *
     * @param Client $client
     */
    public function map($client): array
    {
        return [
            $client->id,
            $client->nif,
            $client->razon_social,
            $client->tipo,
            $client->telefono ?? '—',
            $client->direccion ?? '—',
            $client->notas ?? '—',
            $client->created_at ? $client->created_at->format('d/m/Y H:i') : '—'
        ];
    }
}
