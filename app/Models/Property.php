<?php

namespace App\Models;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory, HasSlug;

    // Campos rellenables en masa
    protected $fillable = [
        'titulo',
        'slug',
        'descripcion',
        'precio',
        'superficie',
        'localidad',
        'tipo',
        'activo',
        'coordenadas'
    ];

    // Castear tipos
    protected $casts = [
        'activo' => 'boolean',
        'precio' => 'decimal:2',
        'superficie' => 'decimal:2'
    ];

    /**
     * Configuración del slug automático desde el título de la propiedad.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('titulo')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Relación: Una propiedad tiene muchas imágenes asociadas.
     */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class, 'property_id')->orderBy('orden', 'asc');
    }
}
