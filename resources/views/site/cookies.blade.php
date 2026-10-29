@extends('layouts.site')

@section('title', 'Política de Cookies — Nortia')
@section('description', 'Información sobre el uso de cookies en el sitio web de Nortia.')
@section('nav', '')

@section('content')
<section class="product-hero">
  <div class="container"><h1>Política de Cookies</h1><p>Última actualización: junio 2026</p></div>
</section>

<section class="section">
  <div class="container container--narrow">
    <h2>1. ¿Qué son las cookies?</h2>
    <p>Las cookies son pequeños archivos de texto que los sitios web almacenan en el dispositivo del usuario durante su navegación, con el fin de recopilar información sobre la actividad realizada en el sitio web.</p>

    <h2>2. Tipos de cookies utilizadas</h2>
    <p><strong>Cookies técnicas o necesarias:</strong> imprescindibles para el correcto funcionamiento del sitio web, como la protección CSRF de formularios. No requieren consentimiento.</p>
<ul>
<li><strong>lg_csrf</strong>: protege los formularios frente a envíos no deseados. Dura 2 horas.</li>
<li><strong>lg_client_session</strong>: mantiene abierta tu sesión en el Área Cliente, solo si inicias sesión. Dura 8 horas o hasta que cierres sesión.</li>
</ul>
    <p>No se instalan cookies analíticas, publicitarias, de personalización ni otros recursos opcionales por defecto.</p>

    <h2>3. Gestión de cookies</h2>
    <p>Puede bloquear o eliminar las cookies técnicas desde la configuración del navegador, aunque algunas funciones, como el envío seguro de formularios, podrían dejar de funcionar correctamente.</p>

    <h2>4. Cookies de terceros</h2>
    <p>No se instala analítica ni cookies no esenciales por defecto. Cualquier integración futura que requiera cookies no esenciales debera activarse solo tras consentimiento previo.</p>
  </div>
</section>
@endsection
