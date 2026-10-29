<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Datos propios de las citas que se reservan desde la web pública
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('modalidad')->nullable()->after('service_id'); // telematica | presencial
            $table->unsignedSmallInteger('duracion_minutos')->nullable()->after('hora');
            $table->string('order_id')->nullable()->after('estado'); // pedido con el que se pagó la consulta
            $table->index(['fecha', 'estado']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary(); // PED-AAAAMMDD-XXXXXXXX
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('status')->default('pending'); // pending | paid | expired | refunded
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('eur');
            $table->string('stripe_session_id')->nullable()->unique();
            $table->text('note')->nullable();
            $table->string('access_hash'); // hash de la clave con la que el comprador sin cuenta ve su pedido
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->string('product_id');
            $table->string('kind');
            $table->string('name'); // nombre y precio copiados del catálogo en el momento de comprar
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('quantity');
        });

        Schema::create('booking_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key_hash')->unique(); // la clave nunca se guarda en claro
            $table->string('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->string('product_id');
            $table->unsignedSmallInteger('idx');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unique(['order_id', 'idx']);
        });

        Schema::create('stripe_events', function (Blueprint $table) {
            $table->string('event_id')->primary(); // idempotencia del webhook
            $table->string('type');
            $table->timestamp('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('booking_keys');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['fecha', 'estado']);
            $table->dropColumn(['modalidad', 'duracion_minutos', 'order_id']);
        });
    }
};
