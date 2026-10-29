<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'invoice_lines' para las partidas individuales de las facturas.
     */
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id(); // Identificador único de línea
            
            // Factura a la que pertenece la línea
            $table->foreignId('invoice_id')
                  ->constrained('invoices')
                  ->onDelete('cascade');

            $table->string('concepto'); // Descripción del servicio o concepto facturado
            $table->integer('cantidad')->default(1); // Cantidad de servicios/horas
            $table->decimal('precio_unitario', 10, 2)->default(0.00); // Precio por unidad
            $table->decimal('subtotal', 10, 2)->default(0.00); // Subtotal de la línea (cantidad * precio_unitario)
            
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
