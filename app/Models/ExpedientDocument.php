<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpedientDocument extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    // Campos rellenables en masa
    protected $fillable = [
        'expedient_id',
        'client_id',
        'nombre',
        'ruta',
        'tipo_mime',
        'subido_por',
        'visibilidad',
        'canal_entrada',
        'categoria',
        'categoria_confianza',
        'url_externa',
        'tipo_documento',
        'origen',
        'codigo_origen'
    ];

    /**
     * Relación: El documento pertenece a un expediente.
     */
    public function expedient(): BelongsTo
    {
        return $this->belongsTo(Expedient::class, 'expedient_id');
    }

    /**
     * Relación: El documento pertenece a un cliente de forma general.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Relación: El documento fue subido por un usuario (trabajador o cliente).
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
