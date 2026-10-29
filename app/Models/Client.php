<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    // Campos rellenables en masa
    protected $fillable = [
        'user_id',
        'nif',
        'razon_social',
        'tipo',
        'telefono',
        'direccion',
        'notas',
        'codigo',
        'email',
        'ruta_sharepoint',
        'activo'
    ];

    protected $casts = ['activo' => 'boolean'];

    /**
     * Relación: Un cliente pertenece a un usuario (acceso de login opcional).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación: Un cliente puede tener muchos expedientes.
     */
    public function expedients(): HasMany
    {
        return $this->hasMany(Expedient::class, 'client_id');
    }

    /**
     * Relación: Un cliente puede tener muchas citas reservadas.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    /**
     * Relación: Un cliente tiene múltiples facturas de cobro.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }
}
