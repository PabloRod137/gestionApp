<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'blog_posts' para las entradas de actualidad del despacho.
     */
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id(); // Identificador único
            
            // Categoría a la que pertenece el artículo
            $table->foreignId('category_id')
                  ->constrained('blog_categories')
                  ->onDelete('cascade');

            $table->string('titulo'); // Título del artículo (ej. 'Novedades sobre la Renta 2026')
            $table->string('slug')->unique(); // Slug para URL amigable
            $table->longText('contenido'); // Contenido completo del post (HTML/Texto enriquecido)
            $table->text('excerpt')->nullable(); // Resumen breve para tarjetas o listados
            $table->string('imagen_portada')->nullable(); // Ruta de la imagen destacada
            
            $table->boolean('publicado')->default(false); // Flag de publicación activa
            $table->timestamp('fecha_publicacion')->nullable(); // Fecha y hora de lanzamiento efectivo
            
            // Campos específicos para metadatos de SEO
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
