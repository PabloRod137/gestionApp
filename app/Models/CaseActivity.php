<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseActivity extends Model
{
    public $timestamps = false;

    protected $fillable = ['expedient_id', 'proposal_id', 'user_id', 'action', 'detail', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function expedient(): BelongsTo { return $this->belongsTo(Expedient::class); }

    public static function registrar(Expedient|int $expediente, string $accion, ?string $detalle = null, ?User $usuario = null, ?Proposal $propuesta = null): self
    {
        $actividad = self::create([
            'expedient_id' => $expediente instanceof Expedient ? $expediente->id : $expediente,
            'proposal_id' => $propuesta?->id,
            'user_id' => $usuario?->id,
            'action' => $accion,
            'detail' => $detalle,
            'created_at' => now(),
        ]);
        // los avances importantes se preparan para reflejarse en SharePoint (el puente decide cuáles)
        \App\Support\Sync\PuenteSharePoint::desdeActividad($actividad);

        return $actividad;
    }
}
