<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adapta el esquema al modelo de datos real del despacho (listas de SharePoint):
 * clientes sin NIF, expedientes sin servicio, catalogo de servicios con su documentacion,
 * modelos de documento, comunidades con propietarios y documentos enlazados a SharePoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('nif')->nullable()->change();
            $table->string('codigo')->nullable()->unique()->after('id');
            $table->string('email')->nullable()->after('razon_social');
            $table->string('ruta_sharepoint', 1000)->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->change();
            $table->string('codigo')->nullable()->unique()->after('id');
            $table->string('tipo_cliente')->nullable();
            $table->string('tipo_cobro')->nullable();
            $table->string('periodicidad')->nullable();
            $table->decimal('iva', 5, 2)->nullable();
            $table->boolean('requiere_validacion')->default(false);
            $table->boolean('facturable')->default(true);
            $table->boolean('genera_documento')->default(false);
            $table->text('documentacion_requerida')->nullable();
            $table->text('accion_sugerida')->nullable();
        });

        Schema::table('expedients', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->change();
            $table->date('fecha_apertura')->nullable()->change();
            $table->string('codigo')->nullable()->unique()->after('id');
            $table->string('ruta_sharepoint', 1000)->nullable();
        });

        Schema::table('expedient_documents', function (Blueprint $table) {
            $table->string('ruta')->nullable()->change();
            $table->string('url_externa', 2000)->nullable();
            $table->string('tipo_documento')->nullable();
            $table->string('origen')->nullable();
            $table->string('codigo_origen')->nullable()->unique();
        });

        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('codigo')->nullable();
            $table->text('descripcion')->nullable();
            // null = sin normalizar todavia: no se sabe si es obligatorio
            $table->boolean('obligatorio')->nullable();
            $table->string('condicion')->nullable();
            $table->unsignedSmallInteger('orden')->nullable();
            $table->boolean('normalizado')->default(false);
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('tipo_documento')->nullable();
            $table->string('nombre');
            $table->string('ruta', 1000)->nullable();
            $table->string('version')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('service_document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('prioridad')->nullable();
            $table->boolean('obligatorio')->default(false);
            $table->unique(['service_id', 'document_template_id']);
        });

        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('nombre');
            $table->string('cif')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ruta_sharepoint', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellidos')->nullable();
            $table->string('dni')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->string('codigo_origen')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('community_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('unidad')->nullable();
            $table->decimal('coeficiente', 8, 4)->nullable();
            $table->decimal('porcentaje', 8, 4)->nullable();
            $table->boolean('activo')->default(true);
            $table->string('codigo_origen')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_owners');
        Schema::dropIfExists('persons');
        Schema::dropIfExists('communities');
        Schema::dropIfExists('service_document_templates');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_requirements');

        Schema::table('expedient_documents', function (Blueprint $table) {
            $table->dropColumn(['url_externa', 'tipo_documento', 'origen', 'codigo_origen']);
        });
        Schema::table('expedients', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'ruta_sharepoint']);
        });
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'tipo_cliente', 'tipo_cobro', 'periodicidad', 'iva', 'requiere_validacion', 'facturable', 'genera_documento', 'documentacion_requerida', 'accion_sugerida']);
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'email', 'ruta_sharepoint', 'activo']);
        });
    }
};
