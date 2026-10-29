<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactRequest extends Model
{
    use HasFactory;

    // Campos rellenables en masa
    protected $fillable = [
        'nombre',
        'email',
        'telefono',
        'servicio_interes',
        'mensaje',
        'estado',
        'asignado_a'
    ];

    /**
     * Relación: La solicitud de contacto puede estar asignada a un profesional del despacho (User).
     */
    public function assignedWorker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }
}
