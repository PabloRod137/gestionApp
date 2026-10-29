<?php

namespace App\Shop;

/**
 * Lo único que la tienda necesita de Stripe. En las pruebas se sustituye por un doble para que
 * la suite nunca dependa de la red.
 */
interface StripeGateway
{
    /** Crea la sesión de Checkout. Devuelve un objeto con `id` y `url`. */
    public function createCheckoutSession(array $params, array $options = []): object;

    /** Verifica la firma del webhook sobre el cuerpo SIN procesar. Lanza una excepción si no es válida. */
    public function constructEvent(string $rawBody, string $signature, string $secret): object;
}
