<?php

namespace App\Models;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory, HasSlug;

    // Campos permitidos para asignación en masa
    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'precio_base',
        'tipo',
        'activo',
        'codigo',
        'tipo_cliente',
        'tipo_cobro',
        'periodicidad',
        'iva',
        'requiere_validacion',
        'facturable',
        'genera_documento',
        'documentacion_requerida',
        'accion_sugerida',
        'palabras_clave',
        'notas',
        'tipo_servicio',
        'momento_facturacion',
    ];

    // Castear campos a tipos específicos
    protected $casts = [
        'activo' => 'boolean',
        'precio_base' => 'decimal:2',
        'requiere_validacion' => 'boolean',
        'facturable' => 'boolean',
        'genera_documento' => 'boolean',
    ];

    /**
     * Configuración del slug automático desde el nombre del servicio.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('nombre')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate(); // Mantiene la URL estable aunque cambie el título comercial
    }

    /**
     * Relación: Un servicio puede estar asociado a múltiples expedientes de clientes.
     */
    public function expedients(): HasMany
    {
        return $this->hasMany(Expedient::class, 'service_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class)->orderBy('orden');
    }

    public function templates(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DocumentTemplate::class, 'service_document_templates')->withPivot(['prioridad', 'obligatorio'])->orderByPivot('prioridad');
    }

    /**
     * Relación: Un servicio puede estar asociado a múltiples citas.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }
}
