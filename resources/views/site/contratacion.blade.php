@extends('layouts.site')

@section('title', 'Condiciones de contratación | Nortia')
@section('description', 'Condiciones generales de solicitud, reserva y contratación de servicios profesionales en Nortia.')
@section('nav', '')

@section('content')
<section class="product-hero">
  <div class="container"><h1>Condiciones de contratación</h1><p>Última actualización: junio 2026</p></div>
</section>

<section class="section">
  <div class="container container--narrow">
    <h2>1. Objeto</h2>
    <p>Estas condiciones regulan las solicitudes de información, reservas de cita y encargos profesionales solicitados a traves de Nortia. La relación profesional definitiva podra requerir presupuesto, hoja de encargo o confirmación expresa del despacho.</p>

    <h2>2. Solicitudes, precios y forma de pago</h2>
    <p>Los importes mostrados en la web son orientativos o previstos para la modalidad seleccionada, salvo indicación expresa en contrario. En esta version no se realiza cobro online automático desde la web. La solicitud queda registrada y el despacho confirmara condiciones, documentación necesaria y forma de pago si procede.</p>

    <h2>3. Reserva de citas</h2>
    <p>La reserva enviada desde la web no implica por si sola aceptación definitiva del encargo profesional. Nortia revisara la solicitud y confirmara la cita por correo, teléfono u otro canal indicado por el cliente.</p>

    <h2>4. Cancelaciones y modificaciones</h2>
    <p>El cliente puede solicitar la modificación o cancelación de una cita contactando con {{ config('site.email') }} o llamando al {{ config('site.phone') }}. Cuando exista pago previo o encargo formalizado, se aplicaran las condiciones concretas aceptadas en la hoja de encargo o presupuesto.</p>

    <h2>5. Derecho de desistimiento</h2>
    <p>Cuando resulte aplicable la normativa de consumidores y usuarios, se informara al cliente de sus derechos antes de iniciar la prestación del servicio. El derecho de desistimiento puede no resultar aplicable una vez comenzada o completada la prestación con consentimiento del cliente, conforme a la normativa vigente.</p>

    <h2>6. Responsabilidad profesional</h2>
    <p>Nortia presta sus servicios con diligencia profesional. Cada encargo se delimitara según la información facilitada por el cliente, el presupuesto aceptado y, en su caso, la hoja de encargo correspondiente.</p>
  </div>
</section>
@endsection
