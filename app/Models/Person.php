<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    protected $table = 'persons';

    protected $fillable = ['nombre', 'apellidos', 'dni', 'email', 'telefono', 'direccion', 'codigo_origen'];

    public function ownerships(): HasMany
    {
        return $this->hasMany(CommunityOwner::class);
    }
}
