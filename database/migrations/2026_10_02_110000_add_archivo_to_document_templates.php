<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            // archivo Word subido al panel (almacenamiento privado); 'ruta' sigue siendo la de SharePoint
            $table->string('archivo', 500)->nullable()->after('ruta');
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('archivo');
        });
    }
};
