<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un avance del panel que debe reflejarse en SharePoint (por Power Automate). pendiente -> exportado -> confirmado. */
class SyncEvent extends Model
{
    protected $guarded = [];

    protected $casts = ['datos' => 'array', 'ocurrido_en' => 'datetime', 'exportado_en' => 'datetime', 'confirmado_en' => 'datetime'];

    public function expedient(): BelongsTo
    {
        return $this->belongsTo(Expedient::class, 'expedient_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /** Los datos de ficha de un cliente van aparte de la actividad de los expedientes. */
    public function esDeCliente(): bool
    {
        return $this->client_id !== null;
    }

    /** Fila con las columnas de la lista «Registro_Actividad_Expediente» de SharePoint. */
    public function comoFila(): array
    {
        return [
            'ID_Evento' => $this->evento_id,
            'Codigo_Expediente' => $this->expedient->codigo,
            'Actividad' => $this->actividad,
            'Tipo_Actividad' => $this->tipo,
            'Descripcion' => $this->descripcion,
            'Actor' => $this->actor,
            'Origen' => $this->origen,
            'FechaHora_Actividad' => $this->ocurrido_en->toIso8601String(),
            'Documento_Referencia' => $this->documento_referencia,
            'Estado_Anterior' => $this->estado_anterior,
            'Estado_Nuevo' => $this->estado_nuevo,
        ];
    }

    /** Datos aportados para la ficha de un cliente: solo van los campos que se completaron. */
    public function comoFilaCliente(): array
    {
        $d = $this->datos ?? [];

        return [
            'ID_Evento' => $this->evento_id,
            'Codigo_Cliente' => $this->client->codigo,
            'NIF' => $d['nif'] ?? '',
            'Email' => $d['email'] ?? '',
            'Telefono' => $d['telefono'] ?? '',
            'Direccion' => $d['direccion'] ?? '',
            'Actor' => $this->actor,
            'Origen' => $this->origen,
            'FechaHora_Actividad' => $this->ocurrido_en->toIso8601String(),
        ];
    }
}
