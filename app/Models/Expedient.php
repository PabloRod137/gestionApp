<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expedient extends Model
{
    use HasFactory, SoftDeletes;

    // Campos rellenables en masa
    protected $fillable = [
        'client_id',
        'service_id',
        'worker_id',
        'titulo',
        'estado',
        'fecha_apertura',
        'fecha_cierre',
        'notas_internas',
        'codigo',
        'ruta_sharepoint'
    ];

    // Castear campos a fechas o tipos
    protected $casts = [
        'fecha_apertura' => 'date',
        'fecha_cierre' => 'date',
    ];

    /**
     * Relación: El expediente pertenece a un cliente específico.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Relación: El expediente está asociado a un tipo de servicio.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Relación: El expediente tiene un profesional responsable del despacho.
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    /**
     * Relación: Un expediente contiene múltiples documentos adjuntos.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ExpedientDocument::class, 'expedient_id');
    }

    /**
     * Relación: Un expediente contiene un hilo de conversación / mensajes entre el cliente y el trabajador.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'expedient_id')->orderBy('created_at', 'asc');
    }

    /**
     * Propuestas de actuación del circuito de trabajo (propuesta, documento, revisión, comunicación).
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class)->latest('id');
    }

    /**
     * Historial de actividad del expediente, con quién hizo cada cosa.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CaseActivity::class)->latest('id');
    }
}
