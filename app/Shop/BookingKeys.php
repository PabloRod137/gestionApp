<?php

namespace App\Shop;

use App\Models\BookingKey;
use App\Models\Order;

/** Claves de reserva de las consultas pagadas. */
class BookingKeys
{
    /**
     * La clave se deriva del pedido y de su posición con la clave de la aplicación: en la base solo está su hash
     * y se puede volver a calcular para mostrar el enlace a quien pagó.
     */
    private static function derive(string $orderId, int $index): string
    {
        $raw = hash_hmac('sha256', "booking_key:$orderId:$index", config('app.key'), true);

        return substr(rtrim(strtr(base64_encode($raw), '+/', '-_'), '='), 0, 32);
    }

    private static function hash(string $key): string
    {
        return hash_hmac('sha256', 'booking_key:'.$key, config('app.key'));
    }

    /** El parámetro «consulta» solo ayuda a la interfaz a preseleccionar el servicio; el servidor lo vuelve a comprobar. */
    public static function url(string $orderId, string $key, ?string $productId = null): string
    {
        $query = ['pedido' => $orderId, 'clave' => $key] + ($productId ? ['consulta' => $productId] : []);

        return url('/reservar-cita').'?'.http_build_query($query);
    }

    /** Emite una clave por cada consulta comprada. Es idempotente: repetirlo devuelve las mismas claves. */
    public static function issue(Order $order): array
    {
        abort_unless($order->status === 'paid', 409, 'El pedido no está pagado.');
        $products = [];
        foreach ($order->items as $item) {
            if ($item->kind === 'consulta') {
                array_push($products, ...array_fill(0, $item->quantity, $item->product_id));
            }
        }
        $expires = now()->addDays(config('shop.booking_key_days'));

        $keys = [];
        foreach ($products as $index => $productId) {
            $key = self::derive($order->id, $index);
            BookingKey::firstOrCreate(
                ['order_id' => $order->id, 'idx' => $index],
                ['key_hash' => self::hash($key), 'product_id' => $productId, 'expires_at' => $expires]
            );
            $keys[] = ['key' => $key, 'productId' => $productId];
        }

        return $keys;
    }

    /** Enlaces de reserva todavía utilizables de un pedido pagado. */
    public static function links(Order $order): array
    {
        return BookingKey::where('order_id', $order->id)->whereNull('used_at')->where('expires_at', '>', now())
            ->orderBy('idx')->get()->map(fn (BookingKey $row) => [
                'productId' => $row->product_id,
                'name' => $order->items->firstWhere('product_id', $row->product_id)?->name ?? $row->product_id,
                'url' => self::url($order->id, self::derive($order->id, $row->idx), $row->product_id),
            ])->all();
    }

    /**
     * Consume la clave de un solo uso para una consulta concreta. Devuelve true o el motivo del rechazo.
     * Debe llamarse dentro de la transacción que guarda la cita: si esta falla, la clave vuelve a estar libre.
     */
    public static function consume(string $orderId, string $key, string $productId): bool|string
    {
        $row = BookingKey::where('key_hash', self::hash($key))->lockForUpdate()->first();
        if (! $row || $row->order_id !== $orderId) {
            return 'invalid';
        }
        if (Order::whereKey($orderId)->value('status') !== 'paid') {
            return 'not_paid';
        }
        if ($row->product_id !== $productId) {
            return 'service_mismatch';
        }
        if ($row->used_at) {
            return 'used';
        }
        if ($row->expires_at->isPast()) {
            return 'expired';
        }
        $row->update(['used_at' => now()]);

        return true;
    }
}
