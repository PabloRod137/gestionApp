@extends('layouts.site')

@section('title', 'Pago | Nortia')
@section('description', '')
@section('robots', 'noindex, nofollow')
@section('nav', 'tienda')

@section('content')
<section class="product-hero">
  <div class="container">
    <h1>Estado de tu pedido</h1>
  </div>
</section>
<section class="section">
  <div class="container container--narrow">
    <p id="order-status" role="status" aria-live="polite">Confirmando tu pago…</p>
    <div id="order-summary"></div>
    <div id="order-booking" hidden>
      <h2>Reserva tu cita</h2>
      <p>Usa estos enlaces para elegir día y hora. Cada uno sirve para una consulta y solo se puede usar una vez; también te los hemos enviado por correo.</p>
      <ul id="order-booking-links"></ul>
    </div>
    <p><a class="btn btn--outline" href="/tienda">Volver a la tienda</a></p>
  </div>
</section>
@endsection
