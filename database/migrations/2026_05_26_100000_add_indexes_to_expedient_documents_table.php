<?php
// database/migrations/2026_05_26_100000_add_indexes_to_expedient_documents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Añade índices de rendimiento a la tabla expedient_documents.
 *
 * Estos índices mejoran el rendimiento de las consultas más frecuentes del panel
 * administrativo y del módulo de ingesta inteligente de Power Automate:
 *   - Filtrar documentos por canal de entrada (email, scanner, whatsapp, power_automate)
 *   - Filtrar documentos de un cliente por canal
 *   - Estadísticas de clasificación por categoría
 *   - Búsqueda de documentos sin expediente asignado (requires_review)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedient_documents', function (Blueprint $table) {
            // Índice para filtrar por canal de entrada en el panel de administración
            // Permite consultas como: WHERE canal_entrada = 'power_automate'
            $table->index('canal_entrada', 'idx_expedient_docs_canal');

            // Índice compuesto para consultas de documentos de un cliente por canal
            // Permite: WHERE client_id = ? AND canal_entrada = ?
            $table->index(['client_id', 'canal_entrada'], 'idx_expedient_docs_client_canal');

            // Índice para estadísticas y filtros por categoría IA
            // Permite: WHERE categoria = 'nomina' (informes de clasificación)
            $table->index('categoria', 'idx_expedient_docs_categoria');

            // Índice para localizar documentos sin expediente asignado (revisión manual)
            // Permite: WHERE expedient_id IS NULL AND canal_entrada = 'power_automate'
            $table->index(['expedient_id', 'canal_entrada'], 'idx_expedient_docs_expedient_canal');
        });
    }

    public function down(): void
    {
        Schema::table('expedient_documents', function (Blueprint $table) {
            $table->dropIndex('idx_expedient_docs_canal');
            $table->dropIndex('idx_expedient_docs_client_canal');
            $table->dropIndex('idx_expedient_docs_categoria');
            $table->dropIndex('idx_expedient_docs_expedient_canal');
        });
    }
};
