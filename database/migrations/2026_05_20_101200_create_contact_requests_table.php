<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'contact_requests' para gestionar los leads y consultas de la web pública.
     */
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id(); // Identificador único
            $table->string('nombre'); // Nombre completo del remitente
            $table->string('email'); // Dirección de email
            $table->string('telefono')->nullable(); // Teléfono opcional
            $table->string('servicio_interes')->nullable(); // Nombre del servicio consultado o seleccionado
            $table->text('mensaje'); // Contenido de la consulta o mensaje
            
            // Estado de gestión: nuevo, gestionado
            $table->string('estado')->default('nuevo'); 
            
            // Trabajador al que se le ha asignado el seguimiento
            $table->foreignId('asignado_a')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
                  
            $table->timestamps(); // Created_at (fecha de solicitud) y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
