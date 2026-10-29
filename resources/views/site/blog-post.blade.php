@extends('layouts.site')

@section('title', ($post->meta_title ?: $post->titulo).' | Nortia')
@section('description', $post->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($post->excerpt ?: $post->contenido), 155))
@section('nav', 'blog')

@section('content')
<article class="section article">
  <div class="container container--narrow">
    <a class="article__back" href="/blog">← Volver al blog</a>
    @if ($post->category)
      <div class="blog-card__cat article__cat">{{ $post->category->nombre }}</div>
    @endif
    <h1 class="article__title">{{ $post->titulo }}</h1>
    @if ($post->fecha_publicacion)
      <p class="article__date"><x-icono n="cal" /> {{ $post->fecha_publicacion->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</p>
    @endif

    @if ($post->imagen_portada)
      <img class="article__image" src="{{ asset('storage/'.$post->imagen_portada) }}" alt="{{ $post->titulo }}" loading="eager">
    @endif

    {{-- El contenido se muestra como texto (nunca como HTML): cada párrafo en blanco separa un bloque --}}
    @foreach (preg_split('/\R{2,}/', trim($post->contenido)) as $paragraph)
      <p class="article__text">{!! nl2br(e($paragraph)) !!}</p>
    @endforeach

    <div class="article__cta">
      <h2>¿Tienes una consulta sobre este tema?</h2>
      <p>Cuéntanos tu situación y revisaremos el punto de partida con criterio prudente.</p>
      <a href="/contacto" class="btn btn--primary">Contactar con el despacho</a>
    </div>
  </div>
</article>
@endsection
