@extends('layouts.site')

@section('title', 'Declaración de la Renta: revisión antes de presentar - Nortia')
@section('description', 'Revisión prudente de documentación y posibles deducciones antes de presentar la declaración.')
@section('nav', 'blog')

@section('content')
<article class="section article">
  <div class="container container--narrow">
    <a class="article__back" href="/blog">← Volver al blog</a>
    <div class="blog-card__cat article__cat">Fiscal</div>
    <h1 class="article__title">Declaración de la Renta: revisión antes de presentar</h1>
    <p class="article__date"><x-icono n="cal" /> 5 de abril de 2026</p>

    <img class="article__image" src="/assets/external/nortia-image-05.webp" alt="Declaración de la Renta: revisión antes de presentar" loading="eager">

    <p class="article__text">Antes de presentar la declaración conviene revisar documentación, datos fiscales, circunstancias personales y posibles deducciones aplicables.</p>
    <p class="article__text">Nortia puede ayudarte a revisar la declaración con prudencia, sin prometer ahorros ni resultados garantizados.</p>

    <div class="article__cta">
      <h2>Solicitar revisión</h2>
      <p>Pide una revisión inicial antes de presentar o confirmar tu borrador.</p>
      <a href="/contacto" class="btn btn--primary">Contactar con el despacho</a>
    </div>
  </div>
</article>
@endsection
