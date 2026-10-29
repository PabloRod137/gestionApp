@extends('layouts.site')

@section('title', 'Contacto — Nortia | Asesoría Legal en Santander, Cantabria')
@section('description', 'Contacta con Nortia. Asesoría jurídica, fiscal y laboral en Santander. Teléfono, email y formulario de contacto. Respuesta en menos de 24h.')
@section('nav', 'contacto')

@section('content')
<section class="product-hero">
  <div class="container">
    <span class="badge">Estamos aquí para ayudarte</span>
    <h1>Contacta con nosotros</h1>
    <p>Respondemos en menos de 24 horas. Primera orientación telefónica sin compromiso.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div id="contact-layout">
      <!-- Info contacto -->
      <div data-animate>
        <span class="badge section__eyebrow">Datos de contacto</span>
        <h2>Hablemos de tu caso</h2>
        <p>Elige el canal que prefieras. Para consultas urgentes, la llamada telefónica es la vía más rápida.</p>

        <div class="valores-list">
          <div class="valor">
            <div class="valor__icon"><x-icono n="tel" /></div>
            <div>
              <div class="valor__title">Teléfono</div>
              <div class="valor__desc"><a href="tel:{{ config('site.phone_tel') }}">{{ config('site.phone') }}</a></div>
            </div>
          </div>
          <div class="valor">
            <div class="valor__icon"><x-icono n="mail" /></div>
            <div>
              <div class="valor__title">Email</div>
              <div class="valor__desc"><a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a></div>
            </div>
          </div>
          <div class="valor">
            <div class="valor__icon"><x-icono n="pin" /></div>
            <div>
              <div class="valor__title">Oficina</div>
              <div class="valor__desc">{{ config('site.address') }}</div>
            </div>
          </div>
          <div class="valor">
            <div class="valor__icon"><x-icono n="reloj" /></div>
            <div>
              <div class="valor__title">Horario</div>
              <div class="valor__desc">{{ config('site.hours') }}</div>
            </div>
          </div>
        </div>

        <a href="/reservar-cita" class="btn btn--dark"><x-icono n="cal" /> Prefiero reservar cita online</a>
      </div>

      <!-- Formulario -->
      <div data-animate>
        <div class="form-panel">
          <h3>Envíanos un mensaje</h3>
          <form id="contact-form">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="c-name">Nombre completo *</label>
                <input type="text" class="form-control" id="c-name" name="name" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="c-phone">Teléfono</label>
                <input type="tel" class="form-control" id="c-phone" name="phone">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="c-email">Email *</label>
              <input type="email" class="form-control" id="c-email" name="email" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="c-service">Área de interés</label>
              <select class="form-control" id="c-service" name="service">
                <option>Legal</option>
                <option>Fiscal</option>
                <option>Laboral</option>
                <option>Comunidades</option>
                <option>Inmobiliario</option>
                <option>Seguros</option>
                <option>Otro</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="c-msg">Mensaje *</label>
              <textarea class="form-control" id="c-msg" name="message" required placeholder="Cuéntanos brevemente tu consulta..."></textarea>
            </div>
            <div class="form-check form-group">
              <input type="checkbox" id="c-privacy" name="privacy" required>
              <label for="c-privacy">He leído y acepto la <a href="/politica-de-privacidad">Política de Privacidad</a>. *</label>
            </div>
            <button type="submit" class="btn btn--primary">Enviar mensaje</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section section--grey" aria-label="Mapa de ubicación">
  <div class="container" data-animate>
    <div class="map-embed" data-map-src="https://www.openstreetmap.org/export/embed.html?bbox={{ config('site.map_lng') - 0.03 }}%2C{{ config('site.map_lat') - 0.02 }}%2C{{ config('site.map_lng') + 0.03 }}%2C{{ config('site.map_lat') + 0.02 }}&layer=mapnik&marker={{ config('site.map_lat') }}%2C{{ config('site.map_lng') }}">
      <div>
        <p class="map-embed__note">El mapa lo ofrece OpenStreetMap. Al mostrarlo tu navegador se conectará con sus servidores y les compartirá tu dirección IP.</p>
        <button type="button" class="btn btn--dark" data-map-show>Mostrar mapa</button>
      </div>
    </div>
  </div>
</section>
@endsection
