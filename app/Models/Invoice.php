<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Factura. Nace como borrador (se puede editar y borrar). Al emitirla (App\Support\Verifactu\Facturacion) recibe su
 * número correlativo y su registro de facturación, y desde entonces es inalterable: solo cambia el cobro (`estado`)
 * y puede anularse con un registro de anulación. Una emitida se corrige con una factura rectificativa.
 * Las anteriores a Veri*Factu quedan como «historica» (solo lectura).
 */
class Invoice extends Model
{
    use HasFactory;

    public const BORRADOR = 'borrador';
    public const EMITIDA = 'emitida';
    public const ANULADA = 'anulada';
    public const HISTORICA = 'historica';

    protected $fillable = [
        'client_id', 'expedient_id', 'numero', 'fecha', 'base_imponible', 'iva', 'total', 'estado',
        'emision', 'serie', 'secuencia', 'tipo_factura', 'tipo_rectificativa', 'rectifica_invoice_id', 'motivo_rectificacion',
        'emisor_nif', 'emisor_nombre', 'emisor_direccion', 'receptor_nif', 'receptor_nombre', 'receptor_direccion',
        'descripcion', 'fecha_operacion', 'cuota_iva', 'retencion_irpf', 'retencion_importe',
        'emitida_at', 'emitida_por', 'anulada_at', 'anulada_motivo', 'qr_url', 'cuota_id', 'periodo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_operacion' => 'date',
        'base_imponible' => 'decimal:2',
        'iva' => 'decimal:2',
        'cuota_iva' => 'decimal:2',
        'retencion_irpf' => 'decimal:2',
        'retencion_importe' => 'decimal:2',
        'total' => 'decimal:2',
        'emitida_at' => 'datetime',
        'anulada_at' => 'datetime',
    ];

    /** Lo único que puede cambiar en una factura que ya no es borrador: el cobro y su anulación. */
    private const MUTABLES_TRAS_EMITIR = ['estado', 'emision', 'anulada_at', 'anulada_motivo', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (self $factura) {
            if ($factura->getOriginal('emision') === self::BORRADOR) {
                return;
            }
            if (array_diff(array_keys($factura->getDirty()), self::MUTABLES_TRAS_EMITIR)) {
                throw new LogicException('Una factura emitida es inalterable: se corrige con una factura rectificativa.');
            }
            if ($factura->isDirty('emision') && ! ($factura->getOriginal('emision') === self::EMITIDA && $factura->emision === self::ANULADA)) {
                throw new LogicException('Solo una factura emitida puede pasar a anulada.');
            }
        });
        static::deleting(function (self $factura) {
            if ($factura->emision !== self::BORRADOR) {
                throw new LogicException('Una factura emitida no se puede borrar: se anula con un registro de anulación.');
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function expedient(): BelongsTo
    {
        return $this->belongsTo(Expedient::class, 'expedient_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(RegistroFacturacion::class, 'invoice_id')->orderBy('id');
    }

    public function rectificada(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rectifica_invoice_id');
    }

    public function esBorrador(): bool
    {
        return $this->emision === self::BORRADOR;
    }

    public function esRectificativa(): bool
    {
        return str_starts_with((string) $this->tipo_factura, 'R');
    }

    /** Facturas que el cliente puede ver: nunca los borradores. */
    public function scopeVisiblesAlCliente(Builder $consulta): Builder
    {
        return $consulta->where('emision', '!=', self::BORRADOR);
    }

    /** Facturas que cuentan como deuda del cliente: emitidas (o anteriores a Veri*Factu) y sin cobrar; nunca borradores ni anuladas. */
    public function scopePendientesDeCobro(Builder $consulta): Builder
    {
        return $consulta->where('estado', 'pendiente')->whereIn('emision', [self::EMITIDA, self::HISTORICA]);
    }

    /** Importe que el cliente paga: el total de la factura menos la retención de IRPF, si la hay. */
    public function getTotalAPagarAttribute(): string
    {
        return number_format((float) $this->total - (float) $this->retencion_importe, 2, '.', '');
    }
}
