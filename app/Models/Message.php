<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    // Campos rellenables en masa
    protected $fillable = [
        'expedient_id',
        'from_user_id',
        'to_user_id',
        'contenido',
        'leido'
    ];

    // Castear leido a boolean
    protected $casts = [
        'leido' => 'boolean'
    ];

    /**
     * Relación: El mensaje pertenece a un expediente.
     */
    public function expedient(): BelongsTo
    {
        return $this->belongsTo(Expedient::class, 'expedient_id');
    }

    /**
     * Relación: El mensaje es enviado por un usuario (from_user_id).
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * Relación: El mensaje está dirigido a un usuario (to_user_id).
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
