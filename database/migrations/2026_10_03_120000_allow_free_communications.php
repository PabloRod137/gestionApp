<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Una comunicación puede prepararse sin documento (pedir documentación, recordatorios...): se vincula al expediente. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_drafts', function (Blueprint $table) {
            $table->unsignedBigInteger('proposal_id')->nullable()->change();
            $table->foreignId('expedient_id')->nullable()->after('proposal_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('communication_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expedient_id');
            $table->dropColumn('tipo');
        });
    }
};
