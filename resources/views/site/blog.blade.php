@extends('layouts.site')

@section('title', 'Blog — Actualidad Legal y Fiscal | Nortia Santander')
@section('description', 'Artículos sobre derecho, fiscalidad, compliance, protección de datos e inmobiliaria en Cantabria. Actualidad explicada por Nortia.')
@section('nav', 'blog')

@section('content')
<section class="product-hero">
  <div class="container">
    <span class="badge">Actualidad Nortia</span>
    <h1>Blog jurídico y fiscal</h1>
    <p>Análisis legal y fiscal prudente. Las fichas de propiedades se mantienen en un área inmobiliaria separada para que la información sea más clara.</p>
    <p><a href="/inmuebles" class="btn btn--outline btn--sm">Ver área inmobiliaria</a></p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="filter-tabs" data-animate>
      <button class="filter-tab active" data-blogcat="all">Todos</button>
      <button class="filter-tab" data-blogcat="Compliance">Compliance</button>
      <button class="filter-tab" data-blogcat="Protección de Datos">Protección de Datos</button>
      <button class="filter-tab" data-blogcat="Fiscal">Fiscal</button>
    </div>
    <div class="blog-grid" id="blog-grid-full" data-animate></div>
  </div>
</section>

<section class="cta-band">
  <div class="container">
    <h2 class="cta-band__title">¿Tienes una consulta sobre algún artículo?</h2>
    <p class="cta-band__desc">Resuélvela directamente con nuestro equipo.</p>
    <div class="cta-band__actions">
      <a href="/contacto" class="btn btn--primary btn--lg">Contactar ahora</a>
    </div>
  </div>
</section>
@endsection
