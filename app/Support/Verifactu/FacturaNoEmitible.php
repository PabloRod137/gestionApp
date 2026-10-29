<?php

namespace App\Support\Verifactu;

use DomainException;

/** La factura no cumple lo necesario para emitirse (o anularse); `motivos` lista qué falta, en lenguaje llano. */
class FacturaNoEmitible extends DomainException
{
    /** @param array<int, string> $motivos */
    public function __construct(public readonly array $motivos)
    {
        parent::__construct(implode(' ', $motivos));
    }
}
