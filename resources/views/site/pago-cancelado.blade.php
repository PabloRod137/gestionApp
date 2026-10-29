@extends('layouts.site')

@section('title', 'Pago cancelado | Nortia')
@section('description', '')
@section('robots', 'noindex, nofollow')
@section('nav', 'tienda')

@section('content')
<section class="product-hero">
  <div class="container">
    <h1>Pago cancelado</h1>
    <p>No se ha realizado ningún cobro. Tu carrito sigue guardado.</p>
  </div>
</section>
<section class="section">
  <div class="container container--narrow">
    <p><a class="btn btn--primary" href="/carrito">Volver al carrito</a> <a class="btn btn--outline" href="/contacto">Necesito ayuda</a></p>
  </div>
</section>
@endsection
