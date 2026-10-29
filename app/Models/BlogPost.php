<?php

namespace App\Models;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPost extends Model
{
    use HasFactory, HasSlug;

    // Campos rellenables en masa
    protected $fillable = [
        'category_id',
        'titulo',
        'slug',
        'contenido',
        'excerpt',
        'imagen_portada',
        'publicado',
        'fecha_publicacion',
        'meta_title',
        'meta_description'
    ];

    // Castear tipos
    protected $casts = [
        'publicado' => 'boolean',
        'fecha_publicacion' => 'datetime',
    ];

    /**
     * Configuración del slug automático desde el título de la publicación.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('titulo')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Relación: La publicación pertenece a una categoría específica.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }
}
