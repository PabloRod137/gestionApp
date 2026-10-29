<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preparado para facturar por eventos: cuándo se factura cada servicio y las cuotas periódicas de cada cliente
 * (se rellenarán cuando haya datos reales; hasta entonces el sistema funciona sin ellas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('momento_facturacion', 12)->nullable();   // apertura | finalizado | null = el de config/facturacion.php
        });

        Schema::create('cuotas_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('concepto');
            $table->decimal('importe', 10, 2);                         // base imponible por periodo
            $table->decimal('iva', 5, 2)->default(21);
            $table->string('periodicidad', 12)->default('mensual');    // mensual | trimestral | anual
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas_clientes')->nullOnDelete();
            $table->string('periodo', 7)->nullable();                  // AAAA-MM de la cuota que se factura
            $table->unique(['cuota_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['cuota_id', 'periodo']);
            $table->dropConstrainedForeignId('cuota_id');
            $table->dropColumn('periodo');
        });
        Schema::dropIfExists('cuotas_clientes');
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn('momento_facturacion'));
    }
};
