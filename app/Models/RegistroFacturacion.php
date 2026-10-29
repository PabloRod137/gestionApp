<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro de facturación de Veri*Factu (alta o anulación). Se escribe una vez y queda encadenado por su huella;
 * lo único que cambia después es el estado de su envío a la AEAT. Además del modelo, la base de datos lo impide
 * con disparadores (ver la migración).
 */
class RegistroFacturacion extends Model
{
    protected $table = 'registros_facturacion';

    protected $guarded = [];

    protected $casts = [
        'enviado_at' => 'datetime',
        'ultimo_intento_at' => 'datetime',
        'intentos' => 'integer',
    ];

    /** Lo único que puede cambiar tras crearse. */
    private const MUTABLES = ['estado_envio', 'intentos', 'ultimo_intento_at', 'enviado_at', 'csv', 'codigo_error', 'descripcion_error', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (self $registro) {
            if (array_diff(array_keys($registro->getDirty()), self::MUTABLES)) {
                throw new LogicException('Los registros de facturación son inalterables.');
            }
        });
        static::deleting(function () {
            throw new LogicException('Los registros de facturación no se pueden borrar.');
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function esAlta(): bool
    {
        return $this->tipo === 'alta';
    }
}
