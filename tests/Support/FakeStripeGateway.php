<?php

namespace Tests\Support;

use App\Shop\StripeGateway;
use RuntimeException;

/** Doble de Stripe para las pruebas: la firma válida es la cadena «valid» y no hay red. */
class FakeStripeGateway implements StripeGateway
{
    public array $created = [];

    public bool $fail = false;

    public function createCheckoutSession(array $params, array $options = []): object
    {
        if ($this->fail) {
            throw new RuntimeException('Invalid API Key provided: sk_test_SECRETA123');
        }
        $this->created[] = ['params' => $params, 'options' => $options];

        return (object) ['id' => 'cs_test_'.count($this->created), 'url' => 'https://checkout.stripe.test/pay/cs_test_'.count($this->created)];
    }

    public function constructEvent(string $rawBody, string $signature, string $secret): object
    {
        if ($signature !== 'valid') {
            throw new RuntimeException('Firma no válida');
        }

        return json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
    }
}
