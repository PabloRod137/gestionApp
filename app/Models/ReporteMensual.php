<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reporte mensual de una comunidad: cifras del periodo y texto que lleva el documento. */
class ReporteMensual extends Model
{
    protected $table = 'reportes_mensuales';

    protected $fillable = ['community_id', 'periodo', 'ingresos', 'gastos', 'saldo', 'cuotas_pendientes', 'incidencias', 'proximas_actuaciones', 'user_id'];

    protected $casts = ['ingresos' => 'decimal:2', 'gastos' => 'decimal:2', 'saldo' => 'decimal:2', 'cuotas_pendientes' => 'decimal:2'];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
