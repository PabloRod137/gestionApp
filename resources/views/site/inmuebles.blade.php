@extends('layouts.site')

@section('title', 'Portal inmobiliario | Nortia')
@section('description', 'Portal inmobiliario de Nortia: propiedades y acompañamiento legal, fiscal y documental para operaciones inmobiliarias en Cantabria.')
@section('nav', 'inmuebles')

@section('content')
  <section class="product-hero">
    <div class="container">
      <span class="badge">Portal inmobiliario</span>
      <h1>Portal inmobiliario</h1>
      <p>Propiedades, revisión documental y acompañamiento jurídico y fiscal antes de firmar.</p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section__header" data-animate>
        <span class="badge section__eyebrow">Selección inmobiliaria</span>
        <h2 class="section__title">Propiedades en revisión</h2>
        <p class="section__desc">La disponibilidad, el precio y la documentación deben confirmarse con Nortia antes de tomar cualquier decisión.</p>
      </div>
      <div class="filter-tabs" data-animate>
        <button class="filter-tab active" data-cat="all" type="button">Todos</button>
        <button class="filter-tab" data-cat="pisos" type="button">Pisos</button>
        <button class="filter-tab" data-cat="terrenos" type="button">Terrenos</button>
      </div>
      <div class="blog-grid" data-animate>
        <article class="blog-card" data-cat="pisos">
          <a class="blog-card__img" href="/blog/piso-centro-urbano-santander"><img src="/assets/external/nortia-image-06.webp" alt="Piso en el centro urbano de Santander" loading="lazy"></a>
          <div class="blog-card__body">
            <div class="blog-card__cat">Piso · información a confirmar</div>
            <h3 class="blog-card__title"><a href="/blog/piso-centro-urbano-santander">Piso en pleno centro urbano de Santander</a></h3>
            <p>Céntrica vivienda en Santander con servicios, comercios y transporte próximos.</p>
            <a href="/blog/piso-centro-urbano-santander" class="btn btn--primary btn--sm">Ver ficha</a>
          </div>
        </article>
        <article class="blog-card" data-cat="pisos">
          <a class="blog-card__img" href="/blog/piso-edificio-emblematico-la-magdalena"><img src="/assets/external/nortia-image-07.webp" alt="Piso en edificio singular en zona residencial de Santander" loading="lazy"></a>
          <div class="blog-card__body">
            <div class="blog-card__cat">Piso · información a confirmar</div>
            <h3 class="blog-card__title"><a href="/blog/piso-edificio-emblematico-la-magdalena">Piso en edificio singular en zona residencial de Santander</a></h3>
            <p>Vivienda singular de ejemplo en una de las zonas más reconocidas de Santander.</p>
            <a href="/blog/piso-edificio-emblematico-la-magdalena" class="btn btn--primary btn--sm">Ver ficha</a>
          </div>
        </article>
        <article class="blog-card" data-cat="terrenos">
          <a class="blog-card__img" href="/blog/terreno-urbano-playa-cuberris"><img src="/assets/external/nortia-image-09.webp" alt="Terreno urbano cerca de la costa, Cantabria" loading="lazy"></a>
          <div class="blog-card__body">
            <div class="blog-card__cat">Terreno · información a confirmar</div>
            <h3 class="blog-card__title"><a href="/blog/terreno-urbano-playa-cuberris">Terreno urbano cerca de la costa, Cantabria</a></h3>
            <p>Terreno de ejemplo en un municipio costero, próximo a la playa y comunicado con Santander.</p>
            <a href="/blog/terreno-urbano-playa-cuberris" class="btn btn--primary btn--sm">Ver ficha</a>
          </div>
        </article>
      </div>

      <div class="portal-promo" data-animate>
        <div class="portal-promo__title">Compra segura de vivienda</div>
        <div class="portal-promo__text">Revisamos titularidad, cargas, contratos, fiscalidad y documentación antes de formalizar la operación.</div>
        <a href="/servicios#inmobiliario" class="btn btn--primary btn--sm">Ver servicio inmobiliario</a>
      </div>
    </div>
  </section>
@endsection
