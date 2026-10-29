<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'property_images' para almacenar las fotos de los inmuebles.
     */
    public function up(): void
    {
        Schema::create('property_images', function (Blueprint $table) {
            $table->id(); // Identificador único
            
            // Relación con el inmueble
            $table->foreignId('property_id')
                  ->constrained('properties')
                  ->onDelete('cascade');

            $table->string('ruta'); // Ruta pública o del storage para acceder a la imagen
            $table->integer('orden')->default(0); // Orden de visualización en la galería
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_images');
    }
};
