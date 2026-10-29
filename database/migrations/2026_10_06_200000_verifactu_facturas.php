<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación conforme a Veri*Factu (RD 1007/2023): facturas en borrador que se emiten una vez, con numeración
 * correlativa por serie, y registros de facturación encadenados (huella) que no se pueden alterar ni borrar.
 * Las facturas anteriores a este cambio quedan como «historica»: emitidas fuera de este sistema, solo lectura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('emision', 12)->default('borrador')->index();      // borrador | emitida | anulada | historica
            $table->string('serie', 20)->nullable();
            $table->unsignedInteger('secuencia')->nullable();
            $table->string('tipo_factura', 2)->default('F1');                  // F1 completa, R1..R5 rectificativas
            $table->string('tipo_rectificativa', 1)->nullable();               // S sustitución, I diferencias
            $table->foreignId('rectifica_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('motivo_rectificacion')->nullable();

            $table->string('emisor_nif', 20)->nullable();
            $table->string('emisor_nombre', 120)->nullable();
            $table->string('emisor_direccion')->nullable();
            $table->string('receptor_nif', 20)->nullable();
            $table->string('receptor_nombre', 120)->nullable();
            $table->string('receptor_direccion')->nullable();
            $table->string('descripcion', 500)->nullable();                    // DescripcionOperacion del registro
            $table->date('fecha_operacion')->nullable();

            $table->decimal('cuota_iva', 12, 2)->default(0);
            $table->decimal('retencion_irpf', 5, 2)->default(0);
            $table->decimal('retencion_importe', 12, 2)->default(0);

            $table->timestamp('emitida_at')->nullable();
            $table->foreignId('emitida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulada_at')->nullable();
            $table->string('anulada_motivo')->nullable();
            $table->text('qr_url')->nullable();

            $table->unique(['serie', 'secuencia']);
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('tipo_iva', 5, 2)->default(21);
            $table->string('calificacion', 3)->default('S1');                  // S1 sujeta, N1 no sujeta (suplidos), E1..E6 exenta
        });

        DB::table('invoices')->update(['emision' => 'historica']);

        Schema::create('series_facturas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();                            // p. ej. F2026, R2026
            $table->unsignedInteger('ultimo')->default(0);
            $table->timestamps();
        });

        Schema::create('registros_facturacion', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10);                                        // alta | anulacion
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('emisor_nif', 20);
            $table->string('num_serie', 60);
            $table->string('fecha_expedicion', 10);                            // DD-MM-AAAA tal y como se hashea
            $table->string('tipo_factura', 2)->nullable();
            $table->string('cuota_total', 16)->nullable();
            $table->string('importe_total', 16)->nullable();
            $table->string('huella_anterior', 64)->nullable();
            $table->string('fecha_hora_huso', 40);                             // ISO 8601 con huso, tal y como se hashea
            $table->string('huella', 64)->unique();
            $table->longText('xml');                                           // el registro exacto que se envía

            // Estado del envío a la AEAT: lo único que cambia después de crearse el registro
            $table->string('estado_envio', 20)->default('pendiente')->index(); // pendiente | aceptado | aceptado_errores | rechazado
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->timestamp('ultimo_intento_at')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->string('csv', 40)->nullable();
            $table->string('codigo_error', 10)->nullable();
            $table->text('descripcion_error')->nullable();
            $table->timestamps();
        });

        $this->protegerRegistros();
    }

    /** Un registro de facturación se escribe una vez: ni se borra ni cambia nada salvo el estado del envío. */
    private function protegerRegistros(): void
    {
        $columnas = ['tipo', 'invoice_id', 'emisor_nif', 'num_serie', 'fecha_expedicion', 'tipo_factura', 'cuota_total',
            'importe_total', 'huella_anterior', 'fecha_hora_huso', 'huella', 'xml'];
        $mensaje = 'Los registros de facturación son inalterables';

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER registros_facturacion_sin_borrar BEFORE DELETE ON registros_facturacion BEGIN SELECT RAISE(ABORT, '$mensaje'); END");
            DB::unprepared('CREATE TRIGGER registros_facturacion_sin_cambios BEFORE UPDATE OF '.implode(', ', $columnas)." ON registros_facturacion BEGIN SELECT RAISE(ABORT, '$mensaje'); END");

            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER registros_facturacion_sin_borrar BEFORE DELETE ON registros_facturacion FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '$mensaje'");
            $cambios = implode(' OR ', array_map(fn ($c) => "NOT (OLD.$c <=> NEW.$c)", $columnas));
            DB::unprepared("CREATE TRIGGER registros_facturacion_sin_cambios BEFORE UPDATE ON registros_facturacion FOR EACH ROW BEGIN IF $cambios THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '$mensaje'; END IF; END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_facturacion');
        Schema::dropIfExists('series_facturas');
        Schema::table('invoice_lines', fn (Blueprint $t) => $t->dropColumn(['tipo_iva', 'calificacion']));
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['serie', 'secuencia']);
            $table->dropConstrainedForeignId('rectifica_invoice_id');
            $table->dropConstrainedForeignId('emitida_por');
            $table->dropColumn(['emision', 'serie', 'secuencia', 'tipo_factura', 'tipo_rectificativa', 'motivo_rectificacion',
                'emisor_nif', 'emisor_nombre', 'emisor_direccion', 'receptor_nif', 'receptor_nombre', 'receptor_direccion',
                'descripcion', 'fecha_operacion', 'cuota_iva', 'retencion_irpf', 'retencion_importe',
                'emitida_at', 'anulada_at', 'anulada_motivo', 'qr_url']);
        });
    }
};
