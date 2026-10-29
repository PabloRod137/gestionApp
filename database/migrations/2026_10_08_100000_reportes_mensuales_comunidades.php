<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cifras y texto del reporte mensual de cada comunidad (una fila por comunidad y mes). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('periodo', 7);                                   // AAAA-MM
            $table->decimal('ingresos', 12, 2)->nullable();
            $table->decimal('gastos', 12, 2)->nullable();
            $table->decimal('saldo', 12, 2)->nullable();                    // saldo a cierre del periodo
            $table->decimal('cuotas_pendientes', 12, 2)->nullable();
            $table->text('incidencias')->nullable();
            $table->text('proximas_actuaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['community_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_mensuales');
    }
};
