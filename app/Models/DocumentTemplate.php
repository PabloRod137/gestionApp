<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DocumentTemplate extends Model
{
    protected $fillable = ['codigo', 'tipo_documento', 'nombre', 'ruta', 'archivo', 'version', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_document_templates');
    }
}
