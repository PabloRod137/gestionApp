<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'audit_logs' para auditar las acciones del personal del despacho.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); // Identificador único
            
            // Usuario que realiza la acción (puede ser nulo en caso de acciones automatizadas del sistema)
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->string('accion'); // Acción auditada (creado, modificado, eliminado)
            $table->string('modelo'); // Nombre del modelo afectado (ej. 'App\Models\Client')
            $table->unsignedBigInteger('modelo_id'); // ID del registro afectado
            
            $table->json('datos_anteriores')->nullable(); // Captura del estado anterior en formato JSON
            $table->json('datos_nuevos')->nullable(); // Captura del estado nuevo en formato JSON
            
            $table->string('ip', 45)->nullable(); // IP desde la cual se realizó la petición
            $table->timestamps(); // Created_at (fecha de la acción) y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
