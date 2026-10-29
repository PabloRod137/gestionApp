<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Community extends Model
{
    protected $fillable = ['client_id', 'nombre', 'cif', 'direccion', 'ruta_sharepoint'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(CommunityOwner::class);
    }
}
