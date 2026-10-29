<?php

namespace App\Shop;

use App\Models\Order;
use App\Models\StripeEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Pedidos de la tienda. El precio y el nombre se copian del catálogo del servidor en el momento de comprar. */
class Orders
{
    /** Valida las líneas del carrito (solo identificador y cantidad) y junta las repetidas. */
    public static function normalizeItems(mixed $items): array
    {
        $invalid = fn () => new InvalidArgumentException('invalid_items');
        if (! is_array($items) || ! array_is_list($items) || count($items) < 1 || count($items) > config('shop.max_cart_lines')) {
            throw $invalid();
        }
        $merged = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['productId'] ?? null) || ! is_int($item['quantity'] ?? null) || $item['quantity'] < 1) {
                throw $invalid();
            }
            if (! Catalog::product($item['productId'])) {
                throw $invalid();
            }
            $merged[$item['productId']] = ($merged[$item['productId']] ?? 0) + $item['quantity'];
            if ($merged[$item['productId']] > config('shop.max_line_quantity')) {
                throw $invalid();
            }
        }

        return array_map(fn ($productId, $quantity) => ['productId' => $productId, 'quantity' => $quantity], array_keys($merged), $merged);
    }

    public static function accessHash(string $token): string
    {
        return hash_hmac('sha256', 'order_access:'.$token, config('app.key'));
    }

    /** Crea el pedido «pending». Devuelve el pedido y, solo en esta llamada, la clave de acceso en claro. */
    public static function create(string $email, array $items, string $note = '', ?int $clientId = null, ?int $userId = null): array
    {
        $lines = array_map(fn ($line) => $line + ['product' => Catalog::product($line['productId'])], self::normalizeItems($items));
        $amount = array_sum(array_map(fn ($line) => $line['product']['priceCents'] * $line['quantity'], $lines));
        $token = bin2hex(random_bytes(24));

        $order = DB::transaction(function () use ($email, $lines, $amount, $note, $clientId, $userId, $token) {
            $order = Order::create([
                'id' => 'PED-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(4))),
                'client_id' => $clientId, 'user_id' => $userId, 'email' => strtolower($email), 'status' => 'pending',
                'amount_cents' => $amount, 'currency' => config('shop.currency'), 'note' => Str::limit($note, 500, ''),
                'access_hash' => self::accessHash($token),
            ]);
            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['productId'], 'kind' => $line['product']['kind'], 'name' => $line['product']['name'],
                    'unit_price_cents' => $line['product']['priceCents'], 'quantity' => $line['quantity'],
                ]);
            }

            return $order->load('items');
        });

        return [$order, $token];
    }

    /** Un pedido se lee con la sesión de su cliente o con su clave de acceso; sin ninguna de las dos, nunca. */
    public static function find(string $id, ?int $clientId = null, ?string $accessToken = null): ?Order
    {
        $order = Order::with('items')->find($id);
        if (! $order) {
            return null;
        }
        if ($clientId && $order->client_id === $clientId) {
            return $order;
        }
        if ($accessToken && hash_equals($order->access_hash, self::accessHash($accessToken))) {
            return $order;
        }

        return null;
    }

    /** Los estados nunca retroceden: paid no vuelve a pending/expired y refunded no vuelve a paid. */
    public static function markPaid(string $id, ?string $sessionId = null): array
    {
        return DB::transaction(function () use ($id, $sessionId) {
            $order = Order::with('items')->lockForUpdate()->find($id);
            $changed = $order && in_array($order->status, ['pending', 'expired'], true);
            if ($changed) {
                $order->update(['status' => 'paid', 'paid_at' => now(), 'stripe_session_id' => $order->stripe_session_id ?? $sessionId]);
            }

            return [$order, (bool) $changed];
        });
    }

    public static function markExpired(string $id): void
    {
        Order::whereKey($id)->where('status', 'pending')->update(['status' => 'expired']);
    }

    public static function markRefunded(string $id): void
    {
        Order::whereKey($id)->where('status', 'paid')->update(['status' => 'refunded']);
    }

    /** Idempotencia del webhook: true solo la primera vez que se ve un evento. */
    public static function recordStripeEvent(string $eventId, string $type): bool
    {
        return StripeEvent::insertOrIgnore(['event_id' => $eventId, 'type' => $type, 'received_at' => now()]) === 1;
    }

    /** Si el procesamiento de un evento falla, se olvida para que el reintento de Stripe lo vuelva a procesar. */
    public static function forgetStripeEvent(string $eventId): void
    {
        StripeEvent::whereKey($eventId)->delete();
    }
}
