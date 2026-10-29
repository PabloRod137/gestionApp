<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los datos que aporta un cliente no pertenecen a un expediente: el evento cuelga del cliente y lleva los valores.
        Schema::table('sync_events', function (Blueprint $table) {
            $table->foreignId('expedient_id')->nullable()->change();
            $table->foreignId('client_id')->nullable()->after('expedient_id')->constrained('clients')->cascadeOnDelete();
            $table->json('datos')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('sync_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('datos');
        });
    }
};
