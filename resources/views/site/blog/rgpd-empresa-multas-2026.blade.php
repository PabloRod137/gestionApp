@extends('layouts.site')

@section('title', 'RGPD: riesgos frecuentes que conviene revisar - Nortia')
@section('description', 'Revisión prudente de riesgos habituales en protección de datos, sujeta siempre a auditoría concreta.')
@section('nav', 'blog')

@section('content')
<article class="section article">
  <div class="container container--narrow">
    <a class="article__back" href="/blog">← Volver al blog</a>
    <div class="blog-card__cat article__cat">Protección de Datos</div>
    <h1 class="article__title">RGPD: riesgos frecuentes que conviene revisar</h1>
    <p class="article__date"><x-icono n="cal" /> 10 de abril de 2026</p>

    <img class="article__image" src="/assets/external/nortia-image-08.webp" alt="RGPD: riesgos frecuentes que conviene revisar" loading="eager">

    <p class="article__text">En protección de datos, los riesgos dependen del tipo de información tratada, proveedores, formularios, contratos y medidas internas existentes.</p>
    <p class="article__text">Nortia puede realizar una auditoría inicial para detectar puntos de mejora y priorizar actuaciones sin prometer resultados garantizados.</p>

    <div class="article__cta">
      <h2>Solicitar auditoría inicial</h2>
      <p>Revisamos el punto de partida RGPD con enfoque práctico y documentado.</p>
      <a href="/contacto" class="btn btn--primary">Contactar con el despacho</a>
    </div>
  </div>
</article>
@endsection
