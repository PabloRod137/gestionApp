@extends('layouts.site')

@section('title', 'Carrito | Nortia')
@section('description', '')
@section('robots', 'noindex, nofollow')
@section('nav', 'tienda')

@section('content')
<section class="product-hero">
  <div class="container">
    <h1>Tu carrito</h1>
    <p>Revisa tu compra y paga de forma segura con Stripe.</p>
  </div>
</section>
<section class="section">
  <div class="container container--narrow">
    <div id="cart-empty" hidden>
      <p>Tu carrito está vacío.</p>
      <a class="btn btn--primary" href="/tienda">Ver la tienda</a>
    </div>
    <form id="cart-form" hidden novalidate>
      <div id="cart-lines"></div>
      <p class="cart-total">Total: <strong id="cart-total"></strong> (IVA incluido)</p>
      <div class="form-group">
        <label class="form-label" for="cart-email">Correo electrónico</label>
        <input class="form-control" id="cart-email" name="email" type="email" autocomplete="email" maxlength="180" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="cart-note">Nota para el despacho (opcional)</label>
        <textarea class="form-control" id="cart-note" name="note" maxlength="500" placeholder="Sin datos personales sensibles."></textarea>
      </div>
      <div class="form-check form-group">
        <input type="checkbox" id="cart-terms" name="acceptTerms">
        <label for="cart-terms">He leído y acepto las <a href="/condiciones-de-contratacion">condiciones de contratación</a> y la <a href="/politica-de-privacidad">política de privacidad</a>.</label>
      </div>
      <button type="submit" class="btn btn--primary" id="cart-pay">Pagar</button>
    </form>
  </div>
</section>
@endsection
