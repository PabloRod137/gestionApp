<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'appointments' para gestionar las citas de asesoramiento.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id(); // Identificador único
            
            // Relación con el cliente (puede ser nulo si es una solicitud inicial externa antes de crear la ficha de cliente)
            $table->foreignId('client_id')
                  ->nullable()
                  ->constrained('clients')
                  ->onDelete('cascade');
                  
            // Relación con el servicio de interés
            $table->foreignId('service_id')
                  ->constrained('services')
                  ->onDelete('cascade');
                  
            // Relación con el trabajador/profesional asignado
            $table->foreignId('worker_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->date('fecha'); // Fecha reservada para la cita
            $table->time('hora'); // Hora de la cita
            
            // Estado de la cita: pendiente, confirmada, realizada, cancelada
            $table->string('estado')->default('pendiente'); 
            
            $table->text('notas')->nullable(); // Notas sobre la reunión o requerimientos del cliente
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
