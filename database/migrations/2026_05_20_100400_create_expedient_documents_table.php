<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'expedient_documents' para el almacenamiento de archivos asociados a expedientes.
     */
    public function up(): void
    {
        Schema::create('expedient_documents', function (Blueprint $table) {
            $table->id(); // Identificador único
            
            // Expediente al que pertenece el documento (nullable si es general del cliente)
            $table->foreignId('expedient_id')
                  ->nullable()
                  ->constrained('expedients')
                  ->onDelete('cascade');

            // Cliente al que pertenece el documento
            $table->foreignId('client_id')
                  ->nullable()
                  ->constrained('clients')
                  ->onDelete('cascade');

            $table->string('nombre'); // Nombre descriptivo del archivo (ej. 'Escritura Notarial.pdf')
            $table->string('ruta'); // Ruta física segura en el disco de almacenamiento privado
            $table->string('tipo_mime')->nullable(); // Tipo MIME del archivo (ej. 'application/pdf')
            
            // Usuario que subió el documento
            $table->foreignId('subido_por')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            // Visibilidad del documento: 'cliente' (visible en el portal del cliente) o 'interno' (solo trabajadores)
            $table->string('visibilidad')->default('interno'); 

            // Canal por el que ingresó el documento: 'portal', 'email', 'whatsapp', 'escaner'
            $table->string('canal_entrada')->default('portal'); 
            
            $table->softDeletes(); // Borrado lógico
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('expedient_documents');
    }
};
