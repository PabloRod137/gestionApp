<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyImage extends Model
{
    use HasFactory;

    // Campos rellenables en masa
    protected $fillable = [
        'property_id',
        'ruta',
        'orden'
    ];

    /**
     * Relación: La imagen pertenece a una propiedad.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
