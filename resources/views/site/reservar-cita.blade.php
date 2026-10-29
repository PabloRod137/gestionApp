@extends('layouts.site')

@section('title', 'Reservar cita — Nortia | Asesoría Legal en Santander, Cantabria')
@section('description', 'Reserva una cita con Nortia. Elige servicio, modalidad, fecha y hora para una consulta jurídica, fiscal o administrativa en Santander.')
@section('nav', '')

@section('content')
<section class="product-hero">
  <div class="container">
    <span class="badge">Disponibilidad inmediata</span>
    <h1>Reserva tu cita online</h1>
    <p>Elige area de servicio, modalidad, fecha y hora. Usaremos el canal indicado para confirmar los detalles de la cita.</p>
  </div>
</section>

<section class="section section--grey" id="reserva" aria-label="Reserva de cita">
  <div class="container">
    <div class="section__header section__header--center" data-animate>
      <span class="badge section__eyebrow">Cita previa</span>
      <h2 class="section__title">Formulario de reserva</h2>
      <p class="section__desc">Selecciona el servicio, elige modalidad y marca un hueco disponible en el calendario.</p>
    </div>

    <div class="booking-layout" data-animate>
      <div>
        <div class="form-panel">
          <div>
            <h3>Tus datos y la solicitud</h3>
          </div>
          <form id="booking-form">
            <div class="form-group">
              <label class="form-label" for="booking-service">Tipo de consulta</label>
              <select class="form-control" id="booking-service" name="service">
                <option value="">Cargando servicios…</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Importe previsto</label>
              <div id="booking-price-summary">
                80,00 €
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="booking-mode">Modalidad</label>
              <select class="form-control" id="booking-mode" name="mode">
                <option value="telematica">Telemática por Teams</option>
                <option value="presencial">Presencial en local</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="booking-name">Nombre completo</label>
              <input type="text" class="form-control" id="booking-name" name="name" required placeholder="María García López">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="booking-email">Email o canal de confirmación</label>
                <input type="email" class="form-control" id="booking-email" name="email" placeholder="maria@empresa.com">
              </div>
              <div class="form-group">
                <label class="form-label" for="booking-phone">Teléfono</label>
                <input type="tel" class="form-control" id="booking-phone" name="phone" placeholder="600 000 000">
              </div>
            </div>
            <p>Indica teléfono o correo electrónico. Usaremos ese mismo canal para confirmar lo acordado.</p>
            <div class="form-group">
              <label class="form-label">Fecha y hora seleccionada</label>
              <div id="booking-summary">
                Selecciona primero dia y hora en el calendario.
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="booking-notes">Describe brevemente tu consulta</label>
              <textarea class="form-control" id="booking-notes" name="notes" placeholder="Opcional. Resume el asunto sin incluir documentación sensible."></textarea>
            </div>
            <div class="form-check form-group">
              <input type="checkbox" id="booking-privacy" name="privacy" required>
              <label for="booking-privacy">He leido y acepto la <a href="/politica-de-privacidad">Política de Privacidad</a> y consiento el tratamiento de mis datos.</label>
            </div>
            <button type="submit" id="booking-submit" class="btn btn--primary" disabled>Enviar solicitud de cita</button>
          </form>
        </div>

        <div class="booking-alt">
          <p>¿Prefieres llamar?</p>
          <a href="tel:{{ config('site.phone_tel') }}"><x-icono n="tel" /> {{ config('site.phone') }}</a>
          <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>
        </div>
      </div>

      <div>
        <div class="calendar-widget">
          <div class="calendar-header">
            <button class="cal-nav" data-action="calendar-prev" aria-label="Mes anterior">‹</button>
            <h3 id="cal-title">Junio 2026</h3>
            <button class="cal-nav" data-action="calendar-next" aria-label="Mes siguiente">›</button>
          </div>
          <div class="calendar-grid">
            <div class="calendar-days" id="calendar-days" aria-label="Calendario de disponibilidad">
              <div class="calendar-day-name">Lun</div>
              <div class="calendar-day-name">Mar</div>
              <div class="calendar-day-name">Mie</div>
              <div class="calendar-day-name">Jue</div>
              <div class="calendar-day-name">Vie</div>
              <div class="calendar-day-name">Sab</div>
              <div class="calendar-day-name">Dom</div>
            </div>
          </div>
          <div class="time-slots" id="time-slots">
            <h4>1. Elige tu hora - <span id="slots-date"></span></h4>
            <div class="slots-grid" id="slots-grid"></div>
          </div>
          <div class="calendar-legend">
            <span><span></span>Disponible</span>
            <span><span></span>Seleccionado</span>
            <span><span></span>No disponible</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
