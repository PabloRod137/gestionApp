<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>{{ $attributes->get('title', 'Área Cliente') }} | Nortia</title>
@php
    $asset = fn (string $path) => asset($path).'?v='.substr(md5_file(public_path($path)), 0, 10);
@endphp
<link rel="preload" href="/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/source-serif-4-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
@vite('resources/css/app.css')
<link rel="icon" type="image/png" href="/assets/logo-mark.png">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
</head>
<body data-nav="area-cliente" class="font-sans text-gray-700 bg-gray-50 antialiased max-w-full overflow-x-hidden">
@include('site.partials.header')
<main id="content">
    <section class="product-hero client-hero">
        <div class="container">
            <span class="badge">{{ $attributes->get('badge', 'Acceso privado') }}</span>
            <h1>{{ $attributes->get('heading', 'Área Cliente') }}</h1>
            @if ($attributes->has('lead'))
                <p>{{ $attributes->get('lead') }}</p>
            @endif
        </div>
    </section>

    <section class="section section--grey">
        <div class="container">
            <div class="client-auth-shell">
                <section class="client-auth-card">
                    {{ $slot }}
                </section>
            </div>
        </div>
    </section>
</main>
@include('site.partials.footer')
<div id="toast" class="toast" role="status" aria-live="polite"></div>
<script type="module" src="{{ $asset('js/main.js') }}"></script>
</body>
</html>
