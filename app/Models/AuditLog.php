<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    // Campos rellenables en masa
    protected $fillable = [
        'user_id',
        'accion',
        'modelo',
        'modelo_id',
        'datos_anteriores',
        'datos_nuevos',
        'ip'
    ];

    // Castear capturas JSON a arrays asociativos
    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array'
    ];

    /**
     * Relación: El log de auditoría pertenece a un usuario (trabajador).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
