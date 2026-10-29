@extends('layouts.site')

@section('title', 'Terreno urbano cerca de la costa, Cantabria — Nortia')
@section('description', 'Barrio de ejemplo, Cantabria. 280.000 € (ejemplo). Terreno urbano en venta a pocos minutos andando de la playa.')
@section('nav', 'inmuebles')

@section('content')
<article class="section article">
<div class="container container--narrow">
<a class="article__back" href="/inmuebles">← Volver al área inmobiliaria</a>
<div class="blog-card__cat article__cat">Inmobiliaria</div>
<h1 class="article__title">Terreno urbano cerca de la costa, Cantabria</h1>
<p class="article__date"><x-icono n="cal" /> 25 de abril de 2026</p>
<img class="article__image" src="/assets/external/nortia-image-09.webp" alt="Terreno urbano cerca de la costa, Cantabria" loading="eager">
<p class="article__lead"><strong>Barrio de ejemplo, municipio costero de Cantabria — 280.000 € (ejemplo)</strong></p>
<p class="article__text">Ficha de muestra con datos inventados. Terreno urbano en venta en un municipio costero de Cantabria, a tan solo unos minutos andando de la playa. Una oportunidad ideal para construir tu vivienda en un entorno tranquilo y muy bien comunicado con Santander.</p>
<p class="article__text">Si necesitas asesoramiento legal sobre la situación urbanística del terreno, cargas o trámites de compraventa, nuestro equipo puede revisarlo todo antes de que firmes.</p>
<div class="article__cta">
<h2>¿Interesado en este terreno?</h2>
<p>Contacta con nosotros para más información o para solicitar una visita.</p>
<a href="/contacto" class="btn btn--primary">Contactar ahora</a>
</div>
</div>
</article>
@endsection
