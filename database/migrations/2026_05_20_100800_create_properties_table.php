<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'properties' para la gestión inmobiliaria.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id(); // Identificador único
            $table->string('titulo'); // Título del inmueble (ej. 'Piso céntrico frente a la bahía')
            $table->string('slug')->unique(); // Slug único para URL amigable
            $table->text('descripcion'); // Descripción comercial detallada del inmueble
            $table->decimal('precio', 12, 2); // Precio de venta/alquiler
            $table->decimal('superficie', 8, 2); // Superficie construida/útil en m2
            $table->string('localidad'); // Ciudad/Pueblo (ej. 'Santander', 'Torrelavega')
            
            // Tipo de propiedad: piso, terreno, local
            $table->string('tipo')->default('piso'); 
            
            $table->boolean('activo')->default(true); // Indica si está en cartelera activa
            $table->string('coordenadas')->nullable(); // Coordenadas geográficas (latitud, longitud)
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
