<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conocimiento documental local: inventario de los documentos de las carpetas de cada cliente, propuestas
 * («hallazgos») que salen de ellos y el historial de cada decisión humana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_indexados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('ruta', 700);                                   // relativa a la raíz documental
            $table->string('nombre');
            $table->string('extension', 8);
            $table->unsignedBigInteger('tamano')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('modificado_ts')->default(0);       // fecha de modificación del archivo (para detectar cambios)
            $table->string('estado', 16)->default('pendiente')->index();   // pendiente | procesando | analizado | omitido | error | desaparecido
            $table->string('motivo', 300)->nullable();
            $table->string('metodo', 16)->nullable();                      // texto | office | pdf | ocr
            $table->unsignedSmallInteger('paginas')->nullable();
            $table->longText('texto')->nullable();                         // texto extraído (limitado); solo se usa localmente
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('analizado_at')->nullable();
            $table->timestamps();
            $table->unique('ruta');
        });

        Schema::create('hallazgos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('documento_id')->constrained('documentos_indexados')->cascadeOnDelete();
            $table->string('tipo', 16);                                    // propuesta (rellena un hueco) | contradiccion (difiere de la ficha)
            $table->string('campo', 20);                                   // nif | email | telefono
            $table->string('valor');
            $table->string('valor_actual')->nullable();                    // lo que hay hoy en la ficha
            $table->unsignedTinyInteger('confianza');                      // 0-100
            $table->string('metodo', 16)->default('regla');
            $table->unsignedSmallInteger('pagina')->nullable();
            $table->string('fragmento', 240)->nullable();
            $table->string('sha256', 64)->nullable();                      // huella del documento del que sale
            $table->boolean('posible_tercero')->default(false);
            $table->string('estado', 16)->default('pendiente')->index();  // pendiente | aceptado | corregido | rechazado | obsoleto
            $table->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidido_at')->nullable();
            $table->timestamps();
            $table->unique(['documento_id', 'campo', 'valor']);
        });

        Schema::create('hallazgo_decisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hallazgo_id')->constrained('hallazgos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 16);                                  // aceptar | corregir | rechazar | reabrir
            $table->string('valor_antes')->nullable();
            $table->string('valor_despues')->nullable();
            $table->string('nota', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hallazgo_decisiones');
        Schema::dropIfExists('hallazgos');
        Schema::dropIfExists('documentos_indexados');
    }
};
