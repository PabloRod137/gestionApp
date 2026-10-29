<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expedient_documents', function (Blueprint $table) {
            $table->string('categoria')->nullable();
            $table->string('categoria_confianza')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expedient_documents', function (Blueprint $table) {
            $table->dropColumn(['categoria', 'categoria_confianza']);
        });
    }
};
