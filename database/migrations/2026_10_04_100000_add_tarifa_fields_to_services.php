<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // de la tarifa del despacho: sirven para reconocer el servicio por el asunto y para saber si hay que presupuestar
            $table->text('palabras_clave')->nullable();
            $table->text('notas')->nullable();
            $table->string('tipo_servicio')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['palabras_clave', 'notas', 'tipo_servicio']);
        });
    }
};
