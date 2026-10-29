<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    // Campos rellenables en masa
    protected $fillable = [
        'client_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'service_id',
        'modalidad',
        'worker_id',
        'fecha',
        'hora',
        'duracion_minutos',
        'estado',
        'order_id',
        'notas'
    ];

    /**
     * Relación: La cita pertenece a un cliente.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Relación: La cita pertenece a un servicio.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Relación: La cita está asignada a un trabajador (User).
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }
}
