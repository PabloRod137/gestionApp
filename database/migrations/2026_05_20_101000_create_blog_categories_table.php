<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'blog_categories' para las categorías del blog jurídico/fiscal.
     */
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id(); // Identificador único
            $table->string('nombre'); // Nombre de la categoría (ej. 'Novedades Fiscales')
            $table->string('slug')->unique(); // Slug único para URL amigable
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
