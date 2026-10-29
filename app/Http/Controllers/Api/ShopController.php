<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Shop\BookingKeys;
use App\Shop\Catalog;
use App\Shop\Orders;
use App\Shop\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/** Tienda: catálogo, checkout con Stripe y estado de un pedido. El precio se calcula siempre en el servidor. */
class ShopController extends Controller
{
    /** Stripe exige que la sesión de Checkout dure entre 30 min y 24 h. */
    private const SESSION_MINUTES = 31;

    public static function paymentsEnabled(): bool
    {
        return config('shop.payments.enabled') && config('shop.payments.provider') === 'stripe';
    }

    public function products(): JsonResponse
    {
        $products = array_map(fn (array $product) => array_intersect_key($product, array_flip(
            ['id', 'kind', 'name', 'description', 'priceCents', 'vatIncluded', 'serviceId', 'mode']
        )), Catalog::products());

        return response()->json(['ok' => true, 'products' => $products, 'paymentsEnabled' => self::paymentsEnabled()]);
    }

    /** Estado de un pedido: lo ve su cliente (sesión) o quien tenga la clave que recibió al pagar. */
    public function order(Request $request, string $id): JsonResponse
    {
        $token = is_string($request->query('clave')) ? substr($request->query('clave'), 0, 80) : null;
        $order = preg_match('/^PED-\d{8}-[0-9A-F]{8}$/', $id)
            ? Orders::find($id, $request->user()?->client?->id, $token)
            : null;
        if (! $order) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }

        return response()->json([
            'ok' => true,
            'order' => self::publicOrder($order),
            'bookingLinks' => $order->status === 'paid' ? BookingKeys::links($order) : [],
        ]);
    }

    public function checkout(Request $request, StripeGateway $stripe): JsonResponse
    {
        if (! self::paymentsEnabled()) {
            return response()->json(['ok' => false, 'error' => 'payments_disabled', 'message' => 'Los pagos online no están disponibles por ahora.'], 503);
        }
        $validator = Validator::make($request->only(['email', 'note']) + ['acceptTerms' => $request->boolean('acceptTerms')], [
            'email' => 'required|email|max:180',
            'note' => 'nullable|string|max:500',
            'acceptTerms' => 'accepted',
        ], [
            'email' => 'Escribe un correo electrónico válido.',
            'acceptTerms.accepted' => 'Debes aceptar las condiciones de contratación.',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'error' => 'validation_error', 'message' => $validator->errors()->first(),
                'fields' => collect($validator->errors()->toArray())->flatMap(fn ($messages, $field) => array_map(fn ($message) => ['field' => $field, 'message' => $message], $messages))->values()], 400);
        }

        try {
            Orders::normalizeItems($request->input('items'));
        } catch (InvalidArgumentException) {
            return response()->json(['ok' => false, 'error' => 'invalid_items', 'message' => 'El carrito no es válido. Revisa los productos y las cantidades.'], 400);
        }

        [$order, $token] = Orders::create(
            $request->input('email'), $request->input('items'), (string) $request->input('note', ''),
            $request->user()?->client?->id, $request->user()?->id,
        );

        try {
            $session = $stripe->createCheckoutSession([
                'mode' => 'payment',
                'locale' => 'es',
                'customer_email' => $order->email,
                'client_reference_id' => $order->id,
                'metadata' => ['order_id' => $order->id],
                // Los reembolsos llegan como eventos del cobro: el id del pedido viaja también ahí.
                'payment_intent_data' => ['metadata' => ['order_id' => $order->id]],
                'line_items' => $order->items->map(fn ($item) => [
                    'quantity' => $item->quantity,
                    'price_data' => ['currency' => $order->currency, 'unit_amount' => $item->unit_price_cents, 'product_data' => ['name' => $item->name]],
                ])->all(),
                'expires_at' => now()->addMinutes(self::SESSION_MINUTES)->timestamp,
                'success_url' => url('/pago-completado').'?'.http_build_query(['pedido' => $order->id, 'clave' => $token]),
                'cancel_url' => url('/pago-cancelado'),
            ], ['idempotency_key' => $order->id]);
            $order->update(['stripe_session_id' => $session->id]);

            return response()->json(['ok' => true, 'url' => $session->url, 'orderId' => $order->id]);
        } catch (\Throwable $e) {
            // Nunca se devuelve el detalle del proveedor (podría contener claves o datos internos).
            logger()->error('checkout_failed '.$order->id.': '.preg_replace('/sk_(live|test)_\w+/', 'sk_***', $e->getMessage()));
            Orders::markExpired($order->id);

            return response()->json(['ok' => false, 'error' => 'payment_provider_error', 'message' => 'No hemos podido iniciar el pago. Inténtalo de nuevo en unos minutos.'], 502);
        }
    }

    /** Al navegador solo llega lo necesario: nunca el correo del comprador ni identificadores internos. */
    private static function publicOrder(Order $order): array
    {
        return [
            'id' => $order->id, 'status' => $order->status, 'amountCents' => $order->amount_cents, 'currency' => $order->currency,
            'createdAt' => $order->created_at?->toIso8601String(), 'paidAt' => $order->paid_at?->toIso8601String(),
            'items' => $order->items->map(fn ($item) => ['name' => $item->name, 'quantity' => $item->quantity, 'unitPriceCents' => $item->unit_price_cents])->all(),
        ];
    }
}
