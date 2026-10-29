@extends('layouts.site')

@section('title', 'Compliance para PYMEs: revisión inicial prudente - Nortia')
@section('description', 'Pautas generales para revisar riesgos de cumplimiento sin prometer resultados ni sustituir una evaluación profesional.')
@section('nav', 'blog')

@section('content')
<article class="section article">
  <div class="container container--narrow">
    <a class="article__back" href="/blog">← Volver al blog</a>
    <div class="blog-card__cat article__cat">Compliance</div>
    <h1 class="article__title">Compliance para PYMEs: revisión inicial prudente</h1>
    <p class="article__date"><x-icono n="cal" /> 15 de abril de 2026</p>

    <img class="article__image" src="/assets/external/nortia-image-04.webp" alt="Compliance para PYMEs: revisión inicial prudente" loading="eager">

    <p class="article__text">El cumplimiento normativo debe valorarse según la actividad, tamaño y riesgos concretos de cada empresa. Esta guía resume puntos habituales que conviene revisar antes de tomar decisiones.</p>
    <p class="article__text">Nortia puede realizar un diagnóstico inicial para ordenar riesgos, documentación y siguientes pasos sin prometer resultados garantizados.</p>

    <div class="article__cta">
      <h2>Solicitar diagnóstico inicial</h2>
      <p>Cuéntanos tu situación y revisaremos el punto de partida con criterio prudente.</p>
      <a href="/contacto" class="btn btn--primary">Contactar con el despacho</a>
    </div>
  </div>
</article>
@endsection
