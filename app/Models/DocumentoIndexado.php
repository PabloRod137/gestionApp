<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un documento de la carpeta de un cliente, con el resultado de leerlo en local. */
class DocumentoIndexado extends Model
{
    protected $table = 'documentos_indexados';

    protected $guarded = [];

    protected $casts = ['analizado_at' => 'datetime', 'modificado_ts' => 'integer'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function hallazgos(): HasMany
    {
        return $this->hasMany(Hallazgo::class, 'documento_id');
    }
}
