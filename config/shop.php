<?php

// Catálogo de consultas, horario de citas y pagos de la web pública.
// El precio se calcula siempre en el servidor a partir de este catálogo: el navegador solo envía identificadores.
$modes = fn (int $telematicaCents, int $presencialCents) => [
    'telematica' => ['label' => 'Telemática por Teams', 'price_cents' => $telematicaCents],
    'presencial' => ['label' => 'Presencial en local', 'price_cents' => $presencialCents],
];

return [
    'currency' => 'eur',
    'time_zone' => env('BUSINESS_TIME_ZONE', 'Europe/Madrid'),

    // Una cita no puede reservarse con menos de estos minutos de antelación.
    'min_notice_minutes' => 120,
    'slot_step_minutes' => 30,
    // Franjas de atención (lunes a viernes). Pendiente de confirmar con el despacho.
    'business_windows' => [['09:00', '12:00'], ['16:00', '18:30']],

    'areas' => [
        ['id' => 'legal', 'name' => 'Legal - consulta inicial', 'category' => 'Legal', 'duration_minutes' => 45, 'modes' => $modes(8000, 9500)],
        ['id' => 'fiscal', 'name' => 'Fiscal - consulta inicial', 'category' => 'Fiscal', 'duration_minutes' => 45, 'modes' => $modes(8000, 9500)],
        ['id' => 'laboral', 'name' => 'Laboral - consulta inicial', 'category' => 'Laboral', 'duration_minutes' => 45, 'modes' => $modes(8000, 9500)],
        ['id' => 'comunidades', 'name' => 'Comunidades - revisión inicial', 'category' => 'Comunidades', 'duration_minutes' => 45, 'modes' => $modes(5000, 6500)],
        ['id' => 'inmobiliario', 'name' => 'Inmobiliario - consulta inicial', 'category' => 'Inmobiliario', 'duration_minutes' => 45, 'modes' => $modes(8000, 9500)],
    ],

    'max_cart_lines' => 10,
    'max_line_quantity' => 5,
    // Días de validez del enlace de reserva que se entrega al pagar una consulta.
    'booking_key_days' => 90,

    'payments' => [
        'enabled' => (bool) env('PAYMENTS_ENABLED', false),
        'provider' => env('PAYMENTS_PROVIDER', ''),
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY', ''),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        ],
    ],
];
