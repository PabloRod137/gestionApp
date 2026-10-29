<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityOwner extends Model
{
    protected $fillable = ['community_id', 'person_id', 'unidad', 'coeficiente', 'porcentaje', 'activo', 'codigo_origen'];

    protected $casts = ['activo' => 'boolean'];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
