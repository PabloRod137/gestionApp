<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InvoiceLine extends Model
{
    use HasFactory;

    /** Calificación de la operación (Veri*Factu): S1 sujeta y no exenta; N1 no sujeta (p. ej. suplidos); E1..E6 exentas. */
    public const CALIFICACIONES = [
        'S1' => 'Sujeta y no exenta',
        'N1' => 'No sujeta (suplidos, provisiones de fondos)',
        'E1' => 'Exenta, artículo 20 LIVA',
        'E2' => 'Exenta, artículo 21 LIVA (exportaciones)',
        'E3' => 'Exenta, artículo 22 LIVA',
        'E4' => 'Exenta, artículos 23 y 24 LIVA',
        'E5' => 'Exenta, artículo 25 LIVA',
        'E6' => 'Exenta por otros motivos',
    ];

    protected $fillable = ['invoice_id', 'concepto', 'cantidad', 'precio_unitario', 'subtotal', 'tipo_iva', 'calificacion'];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tipo_iva' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        $guardar = function (self $linea) {
            $factura = $linea->invoice_id ? Invoice::find($linea->invoice_id) : null;
            if ($factura && ! $factura->esBorrador()) {
                throw new LogicException('Las líneas de una factura emitida son inalterables.');
            }
        };
        static::creating($guardar);
        static::updating($guardar);
        static::deleting($guardar);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
