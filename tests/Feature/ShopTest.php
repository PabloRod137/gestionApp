<?php

namespace Tests\Feature;

use App\Mail\OrderPaid;
use App\Models\Appointment;
use App\Models\Order;
use App\Shop\BookingKeys;
use App\Shop\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeStripeGateway;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/nortia';

    private FakeStripeGateway $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stripe = new FakeStripeGateway();
        $this->app->instance(StripeGateway::class, $this->stripe);
        config(['shop.payments.enabled' => true, 'shop.payments.provider' => 'stripe', 'shop.payments.stripe.webhook_secret' => 'whsec_test']);
    }

    private function checkout(array $overrides = [])
    {
        return $this->postJson(self::BASE.'/shop/checkout', array_merge([
            'items' => [['productId' => 'consulta-legal-telematica', 'quantity' => 1]],
            'email' => 'Comprador@Example.test', 'acceptTerms' => true, 'note' => '',
        ], $overrides));
    }

    /** Crea un pedido y devuelve [pedido, clave de acceso en claro]. */
    private function orderWithToken(?array $items = null): array
    {
        $json = $this->checkout($items ? ['items' => $items] : [])->assertOk()->json();
        $url = $this->stripe->created[count($this->stripe->created) - 1]['params']['success_url'];
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return [Order::findOrFail($json['orderId']), $query['clave']];
    }

    private function webhook(array $event, string $signature = 'valid')
    {
        return $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], json_encode($event));
    }

    private function paidEvent(Order $order, array $overrides = []): array
    {
        return ['id' => 'evt_'.uniqid(), 'type' => 'checkout.session.completed', 'data' => ['object' => array_merge([
            'id' => $order->stripe_session_id, 'client_reference_id' => $order->id, 'payment_status' => 'paid',
            'amount_total' => $order->amount_cents, 'currency' => 'eur', 'metadata' => ['order_id' => $order->id],
        ], $overrides)]];
    }

    private function businessDate(): string
    {
        $date = Carbon::now('Europe/Madrid')->addDays(7);
        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date->toDateString();
    }

    public function test_el_catalogo_publica_diez_consultas_y_si_los_pagos_estan_activos(): void
    {
        $json = $this->getJson(self::BASE.'/shop/products')->assertOk()->json();

        $this->assertCount(10, $json['products']);
        $this->assertTrue($json['paymentsEnabled']);
        $product = collect($json['products'])->firstWhere('id', 'consulta-comunidades-presencial');
        $this->assertSame(6500, $product['priceCents']);
        $this->assertSame('comunidades', $product['serviceId']);
        $this->assertArrayNotHasKey('active', $product);
    }

    public function test_sin_pagos_activos_el_checkout_responde_503(): void
    {
        config(['shop.payments.enabled' => false]);

        $this->checkout()->assertStatus(503)->assertJsonPath('error', 'payments_disabled');
        $this->assertSame(0, Order::count());
    }

    public function test_el_checkout_crea_el_pedido_con_el_precio_del_servidor_y_devuelve_la_url_de_stripe(): void
    {
        $response = $this->checkout(['items' => [['productId' => 'consulta-legal-telematica', 'quantity' => 2], ['productId' => 'consulta-legal-telematica', 'quantity' => 1]]])
            ->assertOk();

        $order = Order::with('items')->firstOrFail();
        $this->assertSame(24000, $order->amount_cents); // 3 × 80 €, las líneas repetidas se juntan
        $this->assertSame('comprador@example.test', $order->email);
        $this->assertSame('pending', $order->status);
        $this->assertSame(3, $order->items->first()->quantity);
        $this->assertStringStartsWith('https://checkout.stripe.test/', $response->json('url'));
        $this->assertMatchesRegularExpression('/^PED-\d{8}-[0-9A-F]{8}$/', $response->json('orderId'));
        $this->assertSame($order->id, $this->stripe->created[0]['options']['idempotency_key']);
        $this->assertSame(8000, $this->stripe->created[0]['params']['line_items'][0]['price_data']['unit_amount']);
    }

    public function test_el_navegador_no_puede_imponer_un_precio_ni_productos_inexistentes(): void
    {
        $this->checkout(['items' => [['productId' => 'consulta-legal-telematica', 'quantity' => 1, 'priceCents' => 1]]])->assertOk();
        $this->assertSame(8000, Order::first()->amount_cents);

        $this->checkout(['items' => [['productId' => 'inventado', 'quantity' => 1]]])->assertStatus(400)->assertJsonPath('error', 'invalid_items');
        $this->checkout(['items' => [['productId' => 'consulta-legal-telematica', 'quantity' => 6]]])->assertStatus(400);
        $this->checkout(['items' => [['productId' => 'consulta-legal-telematica', 'quantity' => 0]]])->assertStatus(400);
        $this->checkout(['items' => []])->assertStatus(400);
        $this->checkout(['items' => 'x'])->assertStatus(400);
        $this->assertSame(1, Order::count());
    }

    public function test_el_checkout_exige_correo_valido_y_aceptar_las_condiciones(): void
    {
        $this->checkout(['acceptTerms' => false])->assertStatus(400);
        $this->checkout(['email' => 'no-es-correo'])->assertStatus(400);
        $this->assertSame(0, Order::count());
    }

    public function test_si_stripe_falla_no_se_filtra_el_detalle_y_el_pedido_queda_caducado(): void
    {
        $this->stripe->fail = true;

        $response = $this->checkout()->assertStatus(502);

        $this->assertStringNotContainsString('sk_test', $response->getContent());
        $this->assertSame('expired', Order::first()->status);
    }

    public function test_un_pedido_solo_se_ve_con_su_clave_de_acceso(): void
    {
        [$order, $token] = $this->orderWithToken();

        $this->getJson(self::BASE."/shop/orders/{$order->id}")->assertNotFound();
        $this->getJson(self::BASE."/shop/orders/{$order->id}?clave=incorrecta")->assertNotFound();
        $this->getJson(self::BASE."/shop/orders/PED-malformado?clave=$token")->assertNotFound();
        $json = $this->getJson(self::BASE."/shop/orders/{$order->id}?clave=$token")->assertOk()->json();

        $this->assertSame('pending', $json['order']['status']);
        $this->assertArrayNotHasKey('email', $json['order']);
        $this->assertSame([], $json['bookingLinks']);
    }

    public function test_el_webhook_rechaza_firmas_invalidas_o_ausentes(): void
    {
        [$order] = $this->orderWithToken();

        $this->webhook($this->paidEvent($order), 'falsa')->assertStatus(400);
        $this->call('POST', '/api/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($this->paidEvent($order)))->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_el_pago_confirmado_marca_el_pedido_envia_correos_y_emite_las_claves(): void
    {
        Mail::fake();
        [$order, $token] = $this->orderWithToken();

        $this->webhook($this->paidEvent($order))->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        Mail::assertSent(OrderPaid::class, 2);
        $links = $this->getJson(self::BASE."/shop/orders/{$order->id}?clave=$token")->json('bookingLinks');
        $this->assertCount(1, $links);
        $this->assertStringContainsString("pedido={$order->id}", $links[0]['url']);
    }

    public function test_un_evento_repetido_no_duplica_correos(): void
    {
        Mail::fake();
        [$order] = $this->orderWithToken();
        $event = $this->paidEvent($order);

        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk()->assertJsonPath('duplicate', true);

        Mail::assertSent(OrderPaid::class, 2);
    }

    public function test_un_importe_distinto_no_da_el_pedido_por_pagado(): void
    {
        [$order] = $this->orderWithToken();

        $this->webhook($this->paidEvent($order, ['amount_total' => 100]))->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_un_pago_asincrono_pendiente_no_cobra_hasta_confirmarse(): void
    {
        [$order] = $this->orderWithToken();

        $this->webhook($this->paidEvent($order, ['payment_status' => 'unpaid']))->assertOk();
        $this->assertSame('pending', $order->fresh()->status);

        $event = $this->paidEvent($order);
        $event['type'] = 'checkout.session.async_payment_succeeded';
        $this->webhook($event)->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_caducar_o_reembolsar_cambia_el_estado_sin_retroceder(): void
    {
        Mail::fake();
        [$order] = $this->orderWithToken();
        $this->webhook(['id' => 'evt_exp', 'type' => 'checkout.session.expired', 'data' => ['object' => ['id' => $order->stripe_session_id, 'client_reference_id' => $order->id]]])->assertOk();
        $this->assertSame('expired', $order->fresh()->status);

        // un pago que llega tras caducar sí se acepta
        $this->webhook($this->paidEvent($order))->assertOk();
        $this->assertSame('paid', $order->fresh()->status);

        // caducar no deshace un pedido pagado
        $this->webhook(['id' => 'evt_exp2', 'type' => 'checkout.session.expired', 'data' => ['object' => ['id' => $order->stripe_session_id, 'client_reference_id' => $order->id]]])->assertOk();
        $this->assertSame('paid', $order->fresh()->status);

        // un reembolso parcial no cambia nada; el total sí
        $this->webhook(['id' => 'evt_r1', 'type' => 'charge.refunded', 'data' => ['object' => ['refunded' => false, 'metadata' => ['order_id' => $order->id]]]])->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        $this->webhook(['id' => 'evt_r2', 'type' => 'charge.refunded', 'data' => ['object' => ['refunded' => true, 'metadata' => ['order_id' => $order->id]]]])->assertOk();
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_un_fallo_de_correo_no_pierde_el_cobro(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        [$order] = $this->orderWithToken();

        $this->webhook($this->paidEvent($order))->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_una_consulta_pagada_se_reserva_una_sola_vez_con_su_clave(): void
    {
        Mail::fake();
        [$order] = $this->orderWithToken();
        $this->webhook($this->paidEvent($order))->assertOk();
        $link = BookingKeys::links($order->fresh('items'))[0]['url'];
        parse_str(parse_url($link, PHP_URL_QUERY), $query);
        $payload = [
            'service' => 'legal', 'mode' => 'telematica', 'name' => 'Comprador', 'email' => 'comprador@example.test',
            'date' => $this->businessDate(), 'time' => '10:00', 'privacy' => true,
            'pedido' => $query['pedido'], 'clave' => $query['clave'],
        ];

        $this->postJson(self::BASE.'/reservations', $payload)->assertCreated()->assertJsonPath('payment', 'paid');
        $this->assertSame($order->id, Appointment::first()->order_id);

        $this->postJson(self::BASE.'/reservations', array_merge($payload, ['time' => '11:00']))->assertStatus(409)->assertJsonPath('error', 'booking_key_used');
        $this->assertSame(1, Appointment::count());
    }

    public function test_la_clave_no_vale_para_otra_consulta_ni_si_la_reserva_falla(): void
    {
        Mail::fake();
        [$order] = $this->orderWithToken();
        $this->webhook($this->paidEvent($order))->assertOk();
        parse_str(parse_url(BookingKeys::links($order->fresh('items'))[0]['url'], PHP_URL_QUERY), $query);
        $date = $this->businessDate();
        $payload = [
            'service' => 'legal', 'mode' => 'telematica', 'name' => 'Comprador', 'email' => 'comprador@example.test',
            'date' => $date, 'time' => '10:00', 'privacy' => true, 'pedido' => $query['pedido'], 'clave' => $query['clave'],
        ];

        // otro servicio o modalidad que el comprado
        $this->postJson(self::BASE.'/reservations', array_merge($payload, ['service' => 'fiscal']))->assertStatus(400)->assertJsonPath('error', 'invalid_booking_key');
        $this->postJson(self::BASE.'/reservations', array_merge($payload, ['mode' => 'presencial']))->assertStatus(400);
        // clave inventada
        $this->postJson(self::BASE.'/reservations', array_merge($payload, ['clave' => 'inventada']))->assertStatus(400);

        // hueco ocupado: la clave no se gasta
        $this->postJson(self::BASE.'/reservations', array_merge($payload, ['pedido' => null, 'clave' => null, 'name' => 'Otra persona']))->assertCreated();
        $this->postJson(self::BASE.'/reservations', $payload)->assertStatus(409)->assertJsonPath('error', 'slot_occupied');
        $this->postJson(self::BASE.'/reservations', array_merge($payload, ['time' => '16:00']))->assertCreated();
    }

    public function test_una_reserva_con_clave_de_un_pedido_sin_pagar_se_rechaza(): void
    {
        [$order] = $this->orderWithToken();
        // sin pago confirmado no hay claves emitidas
        $this->postJson(self::BASE.'/reservations', [
            'service' => 'legal', 'mode' => 'telematica', 'name' => 'X', 'email' => 'x@example.test',
            'date' => $this->businessDate(), 'time' => '10:00', 'privacy' => true, 'pedido' => $order->id, 'clave' => 'cualquiera',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_booking_key');
    }
}
