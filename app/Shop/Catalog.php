<?php

namespace App\Shop;

/**
 * Catálogo de consultas de la web pública (fuente única de nombres, duraciones y precios).
 * Se lee de config/shop.php; el navegador solo envía identificadores.
 */
class Catalog
{
    public static function areas(): array
    {
        return config('shop.areas');
    }

    public static function area(string $id): ?array
    {
        foreach (self::areas() as $area) {
            if ($area['id'] === $id) {
                return $area;
            }
        }

        return null;
    }

    /** Servicios tal y como los consume el calendario de reservas. */
    public static function publicServices(): array
    {
        return array_map(fn (array $area) => [
            'id' => $area['id'],
            'name' => $area['name'],
            'category' => $area['category'],
            'durationMinutes' => $area['duration_minutes'],
            'modes' => array_map(fn (array $mode) => ['label' => $mode['label'], 'priceCents' => $mode['price_cents']], $area['modes']),
        ], self::areas());
    }

    /** Productos comprables: una consulta por área y modalidad. */
    public static function products(): array
    {
        $products = [];
        foreach (self::areas() as $area) {
            foreach ($area['modes'] as $mode => $detail) {
                $products[] = [
                    'id' => "consulta-{$area['id']}-{$mode}",
                    'kind' => 'consulta',
                    'serviceId' => $area['id'],
                    'mode' => $mode,
                    'name' => $area['name'].' ('.($mode === 'telematica' ? 'telemática' : $mode).')',
                    'description' => "Consulta de {$area['duration_minutes']} minutos. Tras el pago recibirás un enlace para elegir día y hora.",
                    'priceCents' => $detail['price_cents'],
                    'vatIncluded' => true,
                ];
            }
        }

        return $products;
    }

    public static function product(string $id): ?array
    {
        foreach (self::products() as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }

        return null;
    }
}
