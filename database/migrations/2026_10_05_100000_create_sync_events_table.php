<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bandeja de salida hacia SharePoint: cada avance del panel es un evento con identificador estable (ID_Evento).
        Schema::create('sync_events', function (Blueprint $table) {
            $table->id();
            $table->string('evento_id')->nullable()->unique();
            $table->foreignId('expedient_id')->constrained('expedients')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('actividad');
            $table->text('descripcion')->nullable();
            $table->string('actor')->nullable();
            $table->string('origen')->default('Nortia');
            $table->string('documento_referencia')->nullable();
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo')->nullable();
            $table->timestamp('ocurrido_en');
            $table->string('estado')->default('pendiente'); // pendiente -> exportado -> confirmado
            $table->timestamp('exportado_en')->nullable();
            $table->string('archivo')->nullable();
            $table->timestamp('confirmado_en')->nullable();
            $table->timestamps();
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_events');
    }
};
