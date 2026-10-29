<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un dato que el programa propone a partir de un documento. Nada pasa a la ficha hasta que una persona lo acepta. */
class Hallazgo extends Model
{
    protected $table = 'hallazgos';

    protected $guarded = [];

    protected $casts = ['posible_tercero' => 'boolean', 'decidido_at' => 'datetime', 'confianza' => 'integer', 'pagina' => 'integer'];

    public const CAMPOS = ['nif' => 'NIF / CIF', 'email' => 'Correo', 'telefono' => 'Teléfono'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoIndexado::class, 'documento_id');
    }

    public function decisiones(): HasMany
    {
        return $this->hasMany(HallazgoDecision::class);
    }

    public function decididoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidido_por');
    }
}
