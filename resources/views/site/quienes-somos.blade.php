@extends('layouts.site')

@section('title', 'Quiénes Somos — Nortia | Asesoría Legal Santander')
@section('description', 'Conoce al equipo de Nortia: más de 15 años de experiencia en derecho mercantil, fiscal, laboral y compliance en Santander, Cantabria.')
@section('nav', 'quienes-somos')

@section('content')
<section class="product-hero">
  <div class="container">
    <span class="badge">Desde hace más de 15 años</span>
    <h1>Quiénes somos</h1>
    <p>Un equipo multidisciplinar al servicio de tu tranquilidad legal y fiscal.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="about-grid" data-animate>
      <div class="about-img">
        <img src="/assets/external/nortia-image-03.webp" alt="Equipo Nortia" loading="lazy" width="600" height="800">
        <div class="about-img__badge"><span class="about-img__badge-num">150+</span><span class="about-img__badge-text">clientes activos</span></div>
      </div>
      <div>
        <span class="badge section__eyebrow">Nuestra historia</span>
        <h2 class="section__title">No solo resolvemos problemas, los prevenimos</h2>
        <p>Nortia nace de la fusión de la experiencia de Roldán Asesores y Roldán Abogados, dos despachos con más de 15 años de trayectoria en Cantabria, especializados en dar respuesta integral a familias, PYMEs y autónomos.</p>
        <p>Creemos en la prevención frente a la reacción. Por eso nuestro modelo de trabajo combina asesoramiento jurídico, fiscal y laboral con herramientas digitales que agilizan cada gestión, desde la reserva de cita hasta el seguimiento de tu expediente.</p>
        <p>Hoy gestionamos más de 150 clientes activos en seis áreas de práctica: jurídico-mercantil, fiscal, laboral, protección de datos, inmobiliario y administración de comunidades de propietarios.</p>
      </div>
    </div>
  </div>
</section>

<section class="section section--grey">
  <div class="container">
    <div class="section__header section__header--center" data-animate>
      <span class="badge section__eyebrow">Equipo</span>
      <h2 class="section__title">Profesionales a tu disposición</h2>
    </div>
    <div class="services-grid" data-animate>
      <article class="service-card">
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/></svg></div>
        <h3 class="service-card__title">Diego Roldán Mesa</h3>
        <p class="service-card__desc">Director y responsable de estrategia. Especialista en derecho mercantil, compliance y digitalización de despachos legales.</p>
      </article>
      <article class="service-card">
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><path d="M4 19V5M4 19h16M8 16v-4M12 16V8M16 16v-7"/></svg></div>
        <h3 class="service-card__title">Departamento Fiscal</h3>
        <p class="service-card__desc">Equipo especializado en IRPF, IVA, Impuesto de Sociedades y planificación fiscal para autónomos y PYMEs.</p>
      </article>
      <article class="service-card">
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M16.5 13.5a4 4 0 0 1 4 4V21"/></svg></div>
        <h3 class="service-card__title">Departamento Laboral</h3>
        <p class="service-card__desc">Gestión de nóminas, contratación, despidos y representación en conciliaciones laborales.</p>
      </article>
      <article class="service-card">
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01M9 15h.01M15 15h.01M10 21v-3h4v3"/></svg></div>
        <h3 class="service-card__title">Administración de Fincas</h3>
        <p class="service-card__desc">Gestión profesional de comunidades de propietarios, desde convocatorias hasta reclamación de impagos.</p>
      </article>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="container">
    <h2 class="cta-band__title">¿Quieres conocernos en persona?</h2>
    <p class="cta-band__desc">Reserva tu primera consulta y cuéntanos tu caso.</p>
    <div class="cta-band__actions">
      <a href="/reservar-cita" class="btn btn--primary btn--lg"><x-icono n="cal" /> Reservar cita</a>
    </div>
  </div>
</section>
@endsection
