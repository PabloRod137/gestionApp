<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Clase de exportación de facturas emitidas a Excel.
 */
class InvoicesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * Retorna la colección de facturas cargando relaciones.
     */
    public function collection()
    {
        return Invoice::with(['client', 'expedient'])->where('emision', '!=', Invoice::BORRADOR)->orderBy('id')->get();
    }

    /**
     * Define los encabezados del Excel.
     */
    public function headings(): array
    {
        return [
            'ID Factura',
            'Número Factura',
            'Cliente / Razón Social',
            'NIF Cliente',
            'Expediente Relacionado',
            'Fecha Emisión',
            'Base Imponible (€)',
            'Cuota IVA (€)',
            'Retención IRPF (€)',
            'Total Facturado (€)',
            'Estado Cobro',
            'Situación'
        ];
    }

    /**
     * Mapea cada factura a la fila de Excel.
     *
     * @param Invoice $invoice
     */
    public function map($invoice): array
    {
        return [
            $invoice->id,
            $invoice->numero,
            $invoice->client->razon_social ?? '—',
            $invoice->client->nif ?? '—',
            $invoice->expedient->titulo ?? 'Sin expediente',
            $invoice->fecha ? $invoice->fecha->format('d/m/Y') : '—',
            $invoice->base_imponible,
            $invoice->cuota_iva,
            $invoice->retencion_importe,
            $invoice->total,
            strtoupper($invoice->estado),
            strtoupper($invoice->emision),
        ];
    }
}
