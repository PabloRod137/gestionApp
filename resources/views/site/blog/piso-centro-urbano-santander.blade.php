@extends('layouts.site')

@section('title', 'Piso en pleno centro urbano de Santander — Nortia')
@section('description', 'Calle del Progreso, 12 — 148.000 € (ejemplo). Céntrico piso en pleno centro urbano de Santander.')
@section('nav', 'inmuebles')

@section('content')
<article class="section article">
<div class="container container--narrow">
<a class="article__back" href="/inmuebles">← Volver al área inmobiliaria</a>
<div class="blog-card__cat article__cat">Inmobiliaria</div>
<h1 class="article__title">Piso en pleno centro urbano de Santander</h1>
<p class="article__date"><x-icono n="cal" /> 19 de abril de 2026</p>
<img class="article__image" src="/assets/external/nortia-image-06.webp" alt="Piso en pleno centro urbano de Santander" loading="eager">
<p class="article__lead"><strong>Calle del Progreso, 12, Bloque 2, Santander — 148.000 € (ejemplo)</strong></p>
<p class="article__text">Céntrico piso de ejemplo en pleno centro urbano de Santander, con fácil acceso a todos los servicios, comercios y transporte público de la ciudad. Ficha de muestra con datos inventados.</p>
<p class="article__text">Antes de formalizar la compra, te recomendamos verificar cargas, hipotecas y la situación registral con nuestro servicio de <a href="/servicios#inmobiliario">servicio inmobiliario</a>.</p>
<div class="article__cta">
<h2>¿Interesado en esta vivienda?</h2>
<p>Contacta con nosotros para más información o para solicitar una visita.</p>
<a href="/contacto" class="btn btn--primary">Contactar ahora</a>
</div>
</div>
</article>
@endsection
