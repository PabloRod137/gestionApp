<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'settings' para almacenar parámetros y configuración global de Nortia.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id(); // Identificador único
            $table->string('clave')->unique()->index(); // Clave identificadora única (ej. 'contact_email')
            $table->text('valor')->nullable(); // Valor asignado a la clave
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
