<?php

namespace App\Shop;

use Stripe\StripeClient;
use Stripe\Webhook;

/** Pasarela real: Stripe Checkout alojado. Las claves salen de la configuración y nunca se registran. */
class StripeSdkGateway implements StripeGateway
{
    private ?StripeClient $client = null;

    public function __construct(private string $secretKey)
    {
    }

    public function createCheckoutSession(array $params, array $options = []): object
    {
        $this->client ??= new StripeClient($this->secretKey);

        return $this->client->checkout->sessions->create($params, $options);
    }

    public function constructEvent(string $rawBody, string $signature, string $secret): object
    {
        return Webhook::constructEvent($rawBody, $signature, $secret);
    }
}
