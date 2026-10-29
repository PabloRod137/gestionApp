<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderPaid;
use App\Models\Order;
use App\Shop\BookingKeys;
use App\Shop\Orders;
use App\Shop\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * POST /api/stripe/webhook. Es la única fuente de verdad del pago: no depende de la sesión ni del navegador.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeGateway $stripe): JsonResponse
    {
        $signature = $request->header('Stripe-Signature');
        $secret = config('shop.payments.stripe.webhook_secret');
        if (! $signature || ! $secret) {
            return $this->rejected();
        }
        try {
            $event = $stripe->constructEvent($request->getContent(), $signature, $secret);
        } catch (\Throwable) {
            return $this->rejected();
        }

        if (! Orders::recordStripeEvent($event->id, $event->type)) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }
        try {
            $this->apply($event);
        } catch (\Throwable $e) {
            // Se olvida el evento para que Stripe lo reintente.
            Orders::forgetStripeEvent($event->id);
            throw $e;
        }

        return response()->json(['received' => true]);
    }

    private function rejected(): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => 'invalid_signature'], 400);
    }

    private function apply(object $event): void
    {
        $object = $event->data->object ?? (object) [];
        switch ($event->type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $this->settle($object);
                break;
            case 'checkout.session.expired':
            case 'checkout.session.async_payment_failed':
                if ($order = $this->findOrder($object)) {
                    Orders::markExpired($order->id);
                }
                break;
            case 'charge.refunded':
                // Solo un reembolso total cambia el pedido; los parciales se gestionan a mano.
                $orderId = $object->metadata->order_id ?? null;
                if ($orderId && ($object->refunded ?? false) === true) {
                    Orders::markRefunded($orderId);
                }
                break;
        }
    }

    private function findOrder(object $session): ?Order
    {
        $orderId = $session->client_reference_id ?? ($session->metadata->order_id ?? null);
        $order = $orderId ? Order::find($orderId) : null;

        return $order ?: (isset($session->id) ? Order::where('stripe_session_id', $session->id)->first() : null);
    }

    /** Da el pedido por cobrado solo si Stripe lo confirma y el importe coincide con el del servidor. */
    private function settle(object $session): void
    {
        $order = $this->findOrder($session);
        if (! $order) {
            logger()->warning('stripe_unknown_order '.($session->id ?? ''));

            return;
        }
        if (($session->payment_status ?? null) !== 'paid') {
            return; // pago asíncrono: se espera a async_payment_succeeded
        }
        if (($session->amount_total ?? null) !== $order->amount_cents || strtolower((string) ($session->currency ?? '')) !== $order->currency) {
            logger()->error("order_amount_mismatch {$order->id} esperado {$order->amount_cents} recibido ".($session->amount_total ?? '?'));

            return;
        }
        [$paid, $changed] = Orders::markPaid($order->id, $session->id ?? null);
        if (! $changed) {
            return;
        }
        // El cumplimiento (claves de reserva y correos) no debe perder el cobro ni provocar reintentos.
        try {
            $keys = BookingKeys::issue($paid);
            $links = BookingKeys::links($paid);
            Mail::to($paid->email)->send(new OrderPaid($paid, $links, forOffice: false));
            Mail::to(config('site.email'))->send(new OrderPaid($paid, $links, forOffice: true));
        } catch (\Throwable $e) {
            logger()->error("order_fulfillment_failed {$order->id}: ".$e->getMessage());
        }
    }
}
