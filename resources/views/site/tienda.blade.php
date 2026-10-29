@extends('layouts.site')

@section('title', 'Tienda | Nortia')
@section('description', 'Compra consultas de asesoría legal, fiscal, laboral y de comunidades. Pago seguro y cita a tu medida.')
@section('nav', 'tienda')

@section('content')
<section class="product-hero">
  <div class="container">
    <span class="badge">Tienda online</span>
    <h1>Tienda</h1>
    <p>Compra tu consulta y reserva la cita cuando te venga mejor. El pago se realiza en una página segura de Stripe: Nortia no ve ni guarda los datos de tu tarjeta.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div id="shop-status" class="form-status shop-status" role="status" hidden></div>
    <div class="section__header">
      <h2 class="section__title">Consultas</h2>
      <p class="section__desc">Precios con IVA incluido. Tras el pago recibirás un enlace para elegir día y hora.</p>
    </div>
    <div class="shop-grid" id="shop-products" aria-live="polite"></div>
    <div class="shop-quote">
      <div>
        <h3>¿Necesitas que llevemos todo el caso?</h3>
        <p>Cuéntanos tu situación y te preparamos un presupuesto a medida, sin compromiso.</p>
      </div>
      <a class="btn btn--primary btn--lg" href="/contacto">Solicitar presupuesto</a>
    </div>
  </div>
</section>
@endsection
