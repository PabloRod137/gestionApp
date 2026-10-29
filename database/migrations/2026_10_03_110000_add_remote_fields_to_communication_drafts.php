<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_drafts', function (Blueprint $table) {
            // resultado de la conexion con el correo de Microsoft 365 (solo borradores)
            $table->string('remote_id', 500)->nullable();
            $table->string('remote_web_link', 1000)->nullable();
            $table->timestamp('remote_verified_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('communication_drafts', function (Blueprint $table) {
            $table->dropColumn(['remote_id', 'remote_web_link', 'remote_verified_at', 'attempts', 'last_error']);
        });
    }
};
