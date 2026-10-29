<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'messages' para la comunicación interna por expediente.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id(); // Identificador del mensaje
            
            // Expediente sobre el cual se habla
            $table->foreignId('expedient_id')
                  ->constrained('expedients')
                  ->onDelete('cascade');
                  
            // Remitente del mensaje (Cliente o Trabajador)
            $table->foreignId('from_user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
                  
            // Destinatario del mensaje
            $table->foreignId('to_user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->text('contenido'); // Contenido en texto del mensaje
            $table->boolean('leido')->default(false); // Indica si el receptor ha leído el mensaje
            $table->timestamps(); // Created_at (fecha de envío) y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
