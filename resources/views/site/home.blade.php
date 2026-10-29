@extends('layouts.site')

@section('title', 'Nortia — Asesoría legal, fiscal e inmobiliaria en Santander')
@section('description', 'Asesoría jurídica, fiscal e inmobiliaria en Santander (Cantabria). Servicios legales, fiscales, laborales, de comunidades, inmobiliarios y de seguros con cita previa.')
@section('nav', 'inicio')

@section('content')
<section class="hero" aria-label="Presentación principal">
  <div class="container">
    <div class="hero__inner">
      <!-- Texto -->
      <div class="hero__content">
        <div class="hero__eyebrow">
          <span class="badge">Santander · Cantabria</span>
        </div>
        <h1 class="hero__title">
          Protección legal integral para <em>Familias, PYMEs</em> y Autónomos
        </h1>
        <p class="hero__desc">
          Asesoría jurídica, fiscal e inmobiliaria. Reserva una cita y revisamos el punto de partida con criterio profesional.
        </p>
        <div class="hero__actions">
          <a href="/reservar-cita" class="btn btn--primary btn--lg">Reservar cita</a>
          <a href="/servicios" class="btn btn--outline btn--lg">Ver servicios</a>
        </div>
        <div class="hero__stats">
          <div>
            <span class="hero__stat-num">+150</span>
            <span class="hero__stat-label">clientes activos</span>
          </div>
          <div>
            <span class="hero__stat-num">15+</span>
            <span class="hero__stat-label">años de experiencia</span>
          </div>
          <div>
            <span class="hero__stat-num">6</span>
            <span class="hero__stat-label">áreas de práctica</span>
          </div>
        </div>
      </div>

      <!-- Áreas: enlaces a cada servicio -->
      <nav class="hero__visual" aria-label="Áreas de práctica">
        <a class="hero__card" href="/servicios#legal">
          <span class="hero__card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3v18M5 7l-3 7a4 4 0 0 0 8 0l-3-7zm14 0l-3 7a4 4 0 0 0 8 0l-3-7zM5 7h3M16 7h3M5 21h14"/></svg></span>
          <span><span class="hero__card-title">Asesoría jurídica</span><span class="hero__card-desc">Contratos, compliance, datos y defensa legal</span></span>
        </a>
        <a class="hero__card" href="/servicios#fiscal">
          <span class="hero__card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19V5M4 19h16M8 16v-4M12 16V8M16 16v-7"/></svg></span>
          <span><span class="hero__card-title">Impuestos y revisión</span><span class="hero__card-desc">Particulares, autónomos y empresas</span></span>
        </a>
        <a class="hero__card" href="/servicios#laboral">
          <span class="hero__card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M16.5 13.5a4 4 0 0 1 4 4V21"/></svg></span>
          <span><span class="hero__card-title">Gestión laboral</span><span class="hero__card-desc">Contratos, nóminas y Seguridad Social</span></span>
        </a>
        <a class="hero__card" href="/servicios#seguros">
          <span class="hero__card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/></svg></span>
          <span><span class="hero__card-title">Siniestros y orientación</span><span class="hero__card-desc">Gestión con la aseguradora</span></span>
        </a>
      </nav>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════
     SERVICIOS
══════════════════════════════════════════ -->
<section class="section" aria-label="Nuestros servicios" id="servicios">
  <div class="container">
    <div class="section__header" data-animate>
      <span class="badge section__eyebrow">Nuestros servicios</span>
      <h2 class="section__title">Soluciones a medida en seis áreas</h2>
      <p class="section__desc">Desde la asesoría fiscal hasta la gestión inmobiliaria, cubrimos las necesidades jurídicas, fiscales y documentales de tu familia, empresa o comunidad.</p>
    </div>
    <div class="services-grid services-grid--home">
      <article class="service-card" data-animate>
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><path d="M12 3v18M5 7l-3 7a4 4 0 0 0 8 0l-3-7zm14 0l-3 7a4 4 0 0 0 8 0l-3-7zM5 7h3M16 7h3M5 21h14"/></svg></div>
        <h3 class="service-card__title">Legal</h3>
        <p class="service-card__desc">Asesoramiento jurídico, contratos, reclamaciones, compliance y protección de datos dentro del área legal.</p>
        <div class="service-card__footer"><a href="/servicios#legal" class="link-arrow">Ver Legal</a></div>
      </article>
      <article class="service-card" data-animate>
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><path d="M4 19V5M4 19h16M8 16v-4M12 16V8M16 16v-7"/></svg></div>
        <h3 class="service-card__title">Fiscal</h3>
        <p class="service-card__desc">Declaraciones, planificación fiscal, impuestos periódicos y revisión de obligaciones tributarias.</p>
        <div class="service-card__footer"><a href="/servicios#fiscal" class="link-arrow">Ver Fiscal</a></div>
      </article>
      <article class="service-card" data-animate>
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M16.5 13.5a4 4 0 0 1 4 4V21"/></svg></div>
        <h3 class="service-card__title">Laboral</h3>
        <p class="service-card__desc">Contratos, nóminas, altas y bajas, despidos, conciliaciones y soporte laboral recurrente.</p>
        <div class="service-card__footer"><a href="/servicios#laboral" class="link-arrow">Ver Laboral</a></div>
      </article>
      <article class="service-card" data-animate>
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01M9 15h.01M15 15h.01M10 21v-3h4v3"/></svg></div>
        <h3 class="service-card__title">Comunidades</h3>
        <p class="service-card__desc">Administración de comunidades, convocatorias, actas, presupuestos y reclamación de cuotas.</p>
        <div class="service-card__footer"><a href="/servicios#comunidades" class="link-arrow">Ver Comunidades</a></div>
      </article>
      <article class="service-card" data-animate>
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><path d="M5 16h14l-1.5-5h-11L5 16zM7 16v3M17 16v3M8 19h.01M16 19h.01M7 11l1.5-4h7L17 11"/></svg></div>
        <h3 class="service-card__title">Inmobiliario</h3>
        <p class="service-card__desc">Compraventa, donación y alquiler de inmuebles; gestión documental y cambio de titularidad de vehículos.</p>
        <div class="service-card__footer"><a href="/servicios#inmobiliario" class="link-arrow">Ver Inmobiliario</a></div>
      </article>
      <article class="service-card" data-animate>
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/></svg></div>
        <h3 class="service-card__title">Seguros</h3>
        <p class="service-card__desc">Orientación sobre seguros y gestión de siniestros con la aseguradora.</p>
        <div class="service-card__footer"><a href="/servicios#seguros" class="link-arrow">Ver Seguros</a></div>
      </article>
    </div>
    <div class="section__cta" data-animate>
      <a href="/servicios" class="btn btn--dark btn--lg">Ver todos los servicios →</a>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════
     PROCESO
══════════════════════════════════════════ -->
<section class="section section--process-light section--home-process" aria-label="Cómo trabajamos">
  <div class="container">
    <div class="section__header section__header--center" data-animate>
      <span class="badge section__eyebrow">Nuestro proceso</span>
      <h2 class="section__title">Así te ayudamos</h2>
    </div>
    <div class="proceso" data-animate>
      <div class="proceso__step">
        <div class="proceso__num">1</div>
        <div class="proceso__title">Reserva tu cita</div>
        <div class="proceso__desc">Online o por teléfono, elige día y hora que mejor te venga.</div>
      </div>
      <div class="proceso__step">
        <div class="proceso__num">2</div>
        <div class="proceso__title">Consulta inicial</div>
        <div class="proceso__desc">Analizamos tu situación y te explicamos las opciones disponibles.</div>
      </div>
      <div class="proceso__step">
        <div class="proceso__num">3</div>
        <div class="proceso__title">Propuesta a medida</div>
        <div class="proceso__desc">Recibe una propuesta personalizada con precio cerrado y sin sorpresas.</div>
      </div>
      <div class="proceso__step">
        <div class="proceso__num">4</div>
        <div class="proceso__title">Ejecución y seguimiento</div>
        <div class="proceso__desc">Nos encargamos de todo con informes periódicos de estado.</div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════
     SOBRE NOSOTROS
══════════════════════════════════════════ -->
<section class="section section--home-about" aria-label="Quiénes somos">
  <div class="container">
    <div class="about-grid" data-animate>
      <div class="about-img">
        <img src="/assets/external/nortia-image-03.webp"
             alt="Diego Roldán Mesa — Director de Nortia"
             loading="lazy" width="600" height="800">
        <div class="about-img__badge">
          <span class="about-img__badge-num">+15</span>
          <span class="about-img__badge-text">años de experiencia</span>
        </div>
      </div>
      <div>
        <span class="badge section__eyebrow">Quiénes somos</span>
        <h2 class="section__title">Asesoría integral para empresa, patrimonio e inmuebles</h2>
        <p>
          Somos un equipo de asesoría jurídica, fiscal, laboral e inmobiliaria para familias, comunidades, empresas y autónomos en Cantabria. Nuestro enfoque combina criterio técnico, gestión clara y acompañamiento práctico en decisiones importantes.
        </p>
        <p>
          <em>«No solo resolvemos problemas, los prevenimos».</em> Con Nortia, cada cliente cuenta con apoyo coordinado para contratos, impuestos, nóminas, comunidades, operaciones inmobiliarias, vehículos y trámites patrimoniales.
        </p>
        <div class="valores-list">
          <div class="valor">
            <div class="valor__icon"><svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/></svg></div>
            <div>
              <div class="valor__title">Empresa y Autónomos</div>
              <div class="valor__desc">Contratos, actividad, obligaciones y decisiones societarias.</div>
            </div>
          </div>
          <div class="valor">
            <div class="valor__icon"><svg viewBox="0 0 24 24"><path d="M4 19V5M4 19h16M8 16v-4M12 16V8M16 16v-7"/></svg></div>
            <div>
              <div class="valor__title">Fiscal y Laboral</div>
              <div class="valor__desc">Impuestos, renta, nóminas, Seguridad Social y gestión recurrente.</div>
            </div>
          </div>
          <div class="valor">
            <div class="valor__icon"><svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01M9 15h.01M15 15h.01M10 21v-3h4v3"/></svg></div>
            <div>
              <div class="valor__title">Comunidades y Patrimonio</div>
              <div class="valor__desc">Administración de comunidades, revisión documental y tramites patrimoniales.</div>
            </div>
          </div>
          <div class="valor">
            <div class="valor__icon"><svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/><path d="M9 12l2 2 4-5"/></svg></div>
            <div>
              <div class="valor__title">Trámites Patrimoniales</div>
              <div class="valor__desc">Vehículos, herencias y documentación con impacto económico.</div>
            </div>
          </div>
        </div>
        <div class="about-actions">
          <a href="/quienes-somos" class="btn btn--dark">Conocer el equipo</a>
          <a href="/contacto" class="btn btn--outline">Contactar</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════
     TESTIMONIOS
══════════════════════════════════════════ -->
@if (count(config('site.testimonials')))
<section class="section section--dark" aria-label="Opiniones de clientes">
  <div class="container">
    <div class="section__header section__header--center" data-animate>
      <span class="badge section__eyebrow">Opiniones</span>
      <h2 class="section__title">Lo que dicen nuestros clientes</h2>
    </div>
    <div class="testimonios-grid" data-animate>
      @foreach (config('site.testimonials') as $opinion)
      <blockquote class="testimonio">
        <p class="testimonio__text">«{{ $opinion['texto'] }}»</p>
        <div class="testimonio__autor">{{ $opinion['autor'] }}</div>
        <div class="testimonio__empresa">{{ $opinion['detalle'] }}</div>
      </blockquote>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- ══════════════════════════════════════════
     BLOG
══════════════════════════════════════════ -->
<section class="section section--grey" aria-label="Blog y actualidad">
  <div class="container">
    <div class="section__header" data-animate>
      <div>
        <span class="badge section__eyebrow">Blog &amp; Actualidad</span>
        <h2 class="section__title">Actualidad Nortia</h2>
      </div>
      <a href="/blog" class="btn btn--dark">Ver todo el blog →</a>
    </div>
    <div class="blog-grid" id="blog-grid" data-animate></div>
  </div>
</section>

<!-- ══════════════════════════════════════════
     CÓMO SE PRESUPUESTA
══════════════════════════════════════════ -->
<section class="section" aria-label="Cómo se presupuesta">
  <div class="container">
    <div class="section__header section__header--center" data-animate>
      <span class="badge section__eyebrow">Sin letra pequeña</span>
      <h2 class="section__title">Cómo se presupuesta cada servicio</h2>
      <p class="section__desc">Cada encargo se valora antes de empezar y no se activa ningún trámite sin tu confirmación.</p>
    </div>
    <div class="pricing-grid" data-animate>
      <div class="pricing-card">
        <div class="pricing-card__name">Legal</div>
        <div class="pricing-card__period">Valoración inicial y presupuesto según asunto</div>
        <ul class="pricing-card__features">
          <li>Contratos y reclamaciones</li>
          <li>Compliance y protección de datos dentro del área legal</li>
          <li>Revisión previa antes de contratar</li>
          <li>Sin promesas de resultado garantizado</li>
        </ul>
        <a href="/reservar-cita" class="btn btn--dark">Reservar cita</a>
      </div>
      <div class="pricing-card pricing-card--featured">
        <div class="pricing-card__name">Fiscal y Laboral</div>
        <div class="pricing-card__period">Presupuesto según volumen</div>
        <ul class="pricing-card__features">
          <li>Fiscalidad de particulares, autónomos y empresas</li>
          <li>Contratos, nóminas y Seguridad Social</li>
          <li>Revisión inicial prudente</li>
          <li>Confirmación antes de activar trámites</li>
        </ul>
        <a href="/reservar-cita" class="btn btn--primary">Solicitar revisión</a>
      </div>
      <div class="pricing-card">
        <div class="pricing-card__name">Comunidades, Inmobiliario y Seguros</div>
        <div class="pricing-card__period">Presupuesto antes de tramitar</div>
        <ul class="pricing-card__features">
          <li>Administración de comunidades</li>
          <li>Operaciones con inmuebles y vehículos</li>
          <li>Gestión de siniestros con la aseguradora</li>
          <li>Presupuesto antes de tramitar</li>
        </ul>
        <a href="/contacto" class="btn btn--dark">Consultar</a>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════
     CTA FINAL
══════════════════════════════════════════ -->
<section class="cta-band" aria-label="Llamada a la acción">
  <div class="container">
    <span class="badge">Tu empresa protegida y en regla</span>
    <h2 class="cta-band__title">¿Tienes dudas sobre tu situación legal o fiscal?</h2>
    <p class="cta-band__desc">Primera consulta orientativa por teléfono. Sin compromiso.</p>
    <div class="cta-band__actions">
      <a href="/reservar-cita" class="btn btn--primary btn--lg"><x-icono n="cal" /> Reservar cita ahora</a>
      <a href="tel:{{ config('site.phone_tel') }}" class="btn btn--outline btn--lg"><x-icono n="tel" /> {{ config('site.phone') }}</a>
    </div>
  </div>
</section>
@endsection
