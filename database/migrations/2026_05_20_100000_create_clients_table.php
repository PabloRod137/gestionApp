<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'clients' para gestionar la información de los clientes.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id(); // Identificador único incremental
            
            // Relación con la tabla 'users' (autenticación)
            // Es nullable porque un cliente podría crearse en el sistema antes de asignarle un acceso de login
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null'); // Si se borra el usuario, el cliente permanece

            $table->string('nif')->unique()->index(); // NIF/CIF del cliente, indexado y único
            $table->string('razon_social'); // Nombre completo o razón social de la empresa
            
            // Tipo de cliente (autónomo, empresa, particular)
            $table->string('tipo')->default('particular'); 
            
            $table->string('telefono')->nullable(); // Teléfono de contacto
            $table->string('direccion')->nullable(); // Dirección fiscal/postal
            $table->text('notas')->nullable(); // Notas internas privadas del despacho
            
            $table->softDeletes(); // Borrado lógico para no perder información histórica
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
