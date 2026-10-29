<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     * Crea la tabla 'invoices' para la facturación a clientes.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id(); // Identificador único
            
            // Cliente al que se le emite la factura
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->onDelete('cascade');
                  
            // Expediente opcional asociado a la factura
            $table->foreignId('expedient_id')
                  ->nullable()
                  ->constrained('expedients')
                  ->onDelete('set null');

            $table->string('numero')->unique()->index(); // Número correlativo de factura (ej. 'FACT-2026-0001')
            $table->date('fecha'); // Fecha de emisión de la factura
            
            $table->decimal('base_imponible', 10, 2)->default(0.00); // Base imponible
            $table->decimal('iva', 5, 2)->default(21.00); // Porcentaje de IVA aplicado (ej. 21.00)
            $table->decimal('total', 10, 2)->default(0.00); // Importe total (Base + IVA)
            
            // Estado de la factura: pendiente (por pagar), pagada, cancelada (rectificativa o anulada)
            $table->string('estado')->default('pendiente'); 
            
            $table->timestamps(); // Created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
