<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'services' para almacenar el catálogo de servicios de Nortia.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id(); // Identificador único
            $table->string('nombre'); // Nombre del servicio (ej. 'Consultoría RGPD')
            $table->string('slug')->unique(); // Slug único para URLs amigables
            $table->text('descripcion'); // Descripción detallada del servicio
            $table->decimal('precio_base', 10, 2)->default(0.00); // Precio base de referencia
            
            // Tipo de servicio: con_cita (necesita cita previa) o sin_cita (gestión directa)
            $table->string('tipo')->default('sin_cita'); 
            
            $table->boolean('activo')->default(true); // Indica si el servicio está activo para el público
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
