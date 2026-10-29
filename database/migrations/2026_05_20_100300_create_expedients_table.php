<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'expedients' para gestionar los expedientes de clientes.
     */
    public function up(): void
    {
        Schema::create('expedients', function (Blueprint $table) {
            $table->id(); // Identificador del expediente
            
            // Cliente dueño del expediente
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->onDelete('cascade');
                  
            // Servicio asociado al expediente
            $table->foreignId('service_id')
                  ->constrained('services')
                  ->onDelete('cascade');
                  
            // Trabajador responsable del seguimiento
            $table->foreignId('worker_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->string('titulo'); // Título descriptivo (ej. 'Constitución de S.L. García y Hijos')
            
            // Estado del expediente: abierto, en_proceso, pendiente_cliente, finalizado, cancelado
            $table->string('estado')->default('abierto'); 
            
            $table->date('fecha_apertura'); // Fecha de inicio de los trámites
            $table->date('fecha_cierre')->nullable(); // Fecha de finalización/archivo
            $table->text('notas_internas')->nullable(); // Anotaciones y notas internas del despacho
            
            $table->softDeletes(); // Borrado lógico para seguridad de la información
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('expedients');
    }
};
