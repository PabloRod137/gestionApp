<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirement extends Model
{
    protected $fillable = ['service_id', 'codigo', 'descripcion', 'obligatorio', 'condicion', 'orden', 'normalizado'];

    protected $casts = ['obligatorio' => 'boolean', 'normalizado' => 'boolean'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
