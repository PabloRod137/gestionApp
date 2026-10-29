@php
    $titulo = trim($__env->yieldContent('title', 'Nortia'));
    $descripcion = trim($__env->yieldContent('description'));
    $imagen = url(trim($__env->yieldContent('og_image')) ?: \App\Support\Seo::IMAGEN);
@endphp
@hasSection('robots')
<meta name="robots" content="@yield('robots')">
@endif
<meta property="og:site_name" content="Nortia">
<meta property="og:locale" content="es_ES">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $titulo }}">
@if ($descripcion !== '')
<meta property="og:description" content="{{ $descripcion }}">
<meta name="twitter:description" content="{{ $descripcion }}">
@endif
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ $imagen }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $titulo }}">
<meta name="twitter:image" content="{{ $imagen }}">
<script type="application/ld+json">{!! json_encode(\App\Support\Seo::fichaLocal(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
