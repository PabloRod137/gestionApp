<?php

// Datos públicos del despacho que aparecen en muchas páginas. Se pueden sobrescribir con variables de entorno.
$phone = env('SITE_PHONE', '600 000 000');

return [
    'phone' => $phone,
    'phone_tel' => '+34'.preg_replace('/\D/', '', $phone),
    'email' => env('SITE_EMAIL', 'info@nortia-demo.test'),
    'hours' => env('SITE_HOURS', 'Lunes a viernes, 9:00 – 19:00'),
    // Oficina del despacho. Las coordenadas solo sitúan el mapa (se cargan solo si la persona lo pide).
    'address' => env('SITE_ADDRESS', 'Avenida de Calvo Sotelo 24, 39002 Santander, Cantabria'),
    'map_lat' => (float) env('SITE_MAP_LAT', 43.4623),
    'map_lng' => (float) env('SITE_MAP_LNG', -3.8099),
    // Opiniones de clientes reales y con su permiso: [['texto' => '…', 'autor' => 'Nombre', 'detalle' => 'Cargo — Empresa']]. Vacío = la sección no se muestra.
    'testimonials' => [],
    'copyright' => env('SITE_COPYRIGHT', 'Nortia — Roldán Asesores'),
];
