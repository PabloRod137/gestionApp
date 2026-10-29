<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cuota periódica que el despacho cobra a un cliente (mensual, trimestral o anual). */
class CuotaCliente extends Model
{
    protected $table = 'cuotas_clientes';

    protected $fillable = ['client_id', 'service_id', 'concepto', 'importe', 'iva', 'periodicidad', 'desde', 'hasta', 'activa'];

    protected $attributes = ['activa' => true, 'periodicidad' => 'mensual', 'iva' => 21];

    protected $casts = ['importe' => 'decimal:2', 'iva' => 'decimal:2', 'desde' => 'date', 'hasta' => 'date', 'activa' => 'boolean'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** ¿Toca facturarla en el mes dado (AAAA-MM)? Mensual siempre; trimestral en enero, abril, julio y octubre; anual en el mes de «desde» (o enero). */
    public function tocaEn(\Carbon\CarbonInterface $mes): bool
    {
        if (! $this->activa) {
            return false;
        }
        $inicio = $mes->copy()->startOfMonth();
        $fin = $mes->copy()->endOfMonth();
        if ($this->desde && $this->desde->gt($fin)) {
            return false;
        }
        if ($this->hasta && $this->hasta->lt($inicio)) {
            return false;
        }

        return match ($this->periodicidad) {
            'mensual' => true,
            'trimestral' => in_array($mes->month, [1, 4, 7, 10], true),
            'anual' => $mes->month === ($this->desde?->month ?? 1),
            default => false,
        };
    }
}
