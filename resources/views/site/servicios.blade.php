@extends('layouts.site')

@section('title', 'Servicios — Asesoría jurídica, fiscal e inmobiliaria | Nortia Santander')
@section('description', 'Servicios Nortia: Legal, Fiscal, Laboral, Comunidades, Inmobiliario y Seguros en Cantabria.')
@section('nav', 'servicios')

@section('content')
<section class="product-hero">
  <div class="container">
    <span class="badge">Servicios Nortia</span>
    <h1>Asesoría jurídica, fiscal e inmobiliaria con seguimiento claro</h1>
    <p>Una página para entender qué cubre cada área, cuándo conviene pedir ayuda y cómo se inicia el trabajo con Nortia.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section__header" data-animate>
      <span class="badge section__eyebrow">Catálogo detallado</span>
      <h2 class="section__title">Elige el área que encaja con tu caso</h2>
      <p class="section__desc">Cada servicio empieza con una revisión inicial para acotar el problema, confirmar documentación y evitar trámites innecesarios.</p>
    </div>

    <div class="filter-tabs" data-animate>
      <button class="filter-tab active" data-cat="all">Todos</button>
      <button class="filter-tab" data-cat="legal">Legal</button>
      <button class="filter-tab" data-cat="fiscal">Fiscal</button>
      <button class="filter-tab" data-cat="laboral">Laboral</button>
      <button class="filter-tab" data-cat="comunidades">Comunidades</button>
      <button class="filter-tab" data-cat="inmobiliario">Inmobiliario</button>
      <button class="filter-tab" data-cat="seguros">Seguros</button>
    </div>
    <div class="service-detail-list" data-animate>

      <article class="service-card service-detail" data-cat="legal" id="legal">
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><path d="M12 3v18M5 7l-3 7a4 4 0 0 0 8 0l-3-7zm14 0l-3 7a4 4 0 0 0 8 0l-3-7zM5 7h3M16 7h3M5 21h14"/></svg></div>
        <div class="service-detail__content">
          <h3 class="service-card__title">Legal</h3>
          <p class="service-card__desc">Acompañamiento jurídico para decidir antes de firmar, reclamar, contestar o iniciar un procedimiento. La prioridad es separar lo urgente de lo importante y evitar pasos que empeoren la posición del cliente.</p>
          <div class="service-detail__analysis">
            <div>
              <h4>Qué analizamos</h4>
              <ul class="service-detail__list">
                <li>Contratos, acuerdos, comunicaciones recibidas y documentación previa.</li>
                <li>Plazos, riesgos de prueba, coste razonable de actuar y alternativas de negociación.</li>
                <li>Obligaciones legales de empresas: protección de datos, compliance y textos jurídicos básicos.</li>
                <li>Operaciones societarias: constitución, participaciones, cambio de administrador y libros de la sociedad.</li>
              </ul>
            </div>
            <div>
              <h4>Qué obtienes</h4>
              <ul class="service-detail__list">
                <li>Criterio jurídico inicial y próximos pasos ordenados.</li>
                <li>Redacción o revisión de documentos cuando el encargo lo requiera.</li>
                <li>Propuesta de actuación si conviene reclamar, negociar o preparar defensa.</li>
              </ul>
            </div>
          </div>
          <p class="service-detail__note">Adecuado cuando necesitas saber qué firmar, qué riesgo asumes o cómo responder sin improvisar.</p>
          <div class="service-card__price">Valoración jurídica inicial</div>
          <div class="service-card__footer"><a href="/reservar-cita" class="btn btn--primary btn--sm">Reservar valoración</a><a href="/contacto" class="btn btn--outline btn--sm">Consultar por escrito</a></div>
        </div>
      </article>

      <article class="service-card service-detail" data-cat="fiscal" id="fiscal">
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><path d="M4 19V5M4 19h16M8 16v-4M12 16V8M16 16v-7"/></svg></div>
        <div class="service-detail__content">
          <h3 class="service-card__title">Fiscal</h3>
          <p class="service-card__desc">Gestión y revisión fiscal para particulares, autónomos y empresas. El objetivo es presentar con datos coherentes, anticipar riesgos y responder con orden ante Hacienda.</p>
          <div class="service-detail__analysis">
            <div>
              <h4>Qué analizamos</h4>
              <ul class="service-detail__list">
                <li>Ingresos, gastos, retenciones, operaciones inmobiliarias y documentación justificativa.</li>
                <li>Borradores de renta, modelos periódicos, declaraciones anteriores y comunicaciones tributarias.</li>
                <li>Impacto fiscal de decisiones personales, profesionales o societarias.</li>
              </ul>
            </div>
            <div>
              <h4>Qué obtienes</h4>
              <ul class="service-detail__list">
                <li>Revisión antes de presentar y advertencia de puntos sensibles.</li>
                <li>Preparación de declaraciones o modelos cuando proceda.</li>
                <li>Respuesta organizada ante requerimientos o comprobaciones.</li>
              </ul>
            </div>
          </div>
          <p class="service-detail__note">Útil si vas a presentar impuestos, has recibido una notificación o quieres medir el impacto fiscal de una operación.</p>
          <div class="service-card__price">Revisión fiscal personalizada</div>
          <div class="service-card__footer"><a href="/reservar-cita" class="btn btn--primary btn--sm">Revisar mi caso fiscal</a></div>
        </div>
      </article>

      <article class="service-card service-detail" data-cat="laboral" id="laboral">
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M16.5 13.5a4 4 0 0 1 4 4V21"/></svg></div>
        <div class="service-detail__content">
          <h3 class="service-card__title">Laboral</h3>
          <p class="service-card__desc">Soporte laboral para empresas, autónomos y trabajadores. Se revisa tanto la parte documental como la estrategia ante altas, bajas, cambios contractuales o conflictos.</p>
          <div class="service-detail__analysis">
            <div>
              <h4>Qué analizamos</h4>
              <ul class="service-detail__list">
                <li>Contratos, nóminas, jornada, categoría, antigüedad y comunicaciones laborales.</li>
                <li>Altas, bajas, modificaciones, despidos, sanciones y reclamaciones.</li>
                <li>Necesidades recurrentes de empresa: nóminas sociales y calendario laboral.</li>
              </ul>
            </div>
            <div>
              <h4>Qué obtienes</h4>
              <ul class="service-detail__list">
                <li>Diagnóstico de la situación y riesgos laborales principales.</li>
                <li>Preparación o revisión de documentación laboral.</li>
                <li>Propuesta de gestión recurrente si necesitas continuidad mensual.</li>
              </ul>
            </div>
          </div>
          <p class="service-detail__note">Adecuado para empresas que quieren ordenar la gestión laboral y para trabajadores que necesitan entender su posición.</p>
          <div class="service-card__price">Diagnóstico laboral y propuesta</div>
          <div class="service-card__footer"><a href="/reservar-cita" class="btn btn--primary btn--sm">Analizar situación laboral</a></div>
        </div>
      </article>

      <article class="service-card service-detail" data-cat="comunidades" id="comunidades">
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01M9 15h.01M15 15h.01M10 21v-3h4v3"/></svg></div>
        <div class="service-detail__content">
          <h3 class="service-card__title">Comunidades</h3>
          <p class="service-card__desc">Administración de comunidades orientada a ordenar cuentas, acuerdos, incidencias y comunicación con propietarios. No se trata sólo de llevar actas: se trata de que la comunidad sepa qué esta abierto y quién responde de cada asunto.</p>
          <div class="service-detail__analysis">
            <div>
              <h4>Qué analizamos</h4>
              <ul class="service-detail__list">
                <li>Número de viviendas/locales, presupuesto, incidencias abiertas y estado de cuotas.</li>
                <li>Actas, acuerdos abiertos, proveedores, contratos y documentación comunitaria.</li>
                <li>Necesidades de comunicación, morosidad y trazabilidad documental.</li>
              </ul>
            </div>
            <div>
              <h4>Qué obtienes</h4>
              <ul class="service-detail__list">
                <li>Propuesta de administración ajustada a la comunidad.</li>
                <li>Plan de orden documental, económico y de incidencias.</li>
                <li>Seguimiento de acuerdos, cuotas y proveedores con criterio práctico.</li>
              </ul>
            </div>
          </div>
          <p class="service-detail__note">Pensado para comunidades que necesitan orden, respuesta y una gestión transparente de incidencias y cuentas.</p>
          <div class="service-card__price">Propuesta tras conocer la comunidad</div>
          <div class="service-card__footer"><a href="/reservar-cita" class="btn btn--primary btn--sm">Solicitar propuesta</a></div>
        </div>
      </article>

      <article class="service-card service-detail" data-cat="inmobiliario" id="inmobiliario">
        <span id="activos" aria-hidden="true"></span><span id="vehiculos" aria-hidden="true"></span>
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><path d="M5 16h14l-1.5-5h-11L5 16zM7 16v3M17 16v3M8 19h.01M16 19h.01M7 11l1.5-4h7L17 11"/></svg></div>
        <div class="service-detail__content">
          <h3 class="service-card__title">Inmobiliario</h3>
          <p class="service-card__desc">Acompañamiento jurídico, fiscal y documental en operaciones con inmuebles, vehículos y otros bienes: compraventa, donación, herencias, alquileres y cambios de titularidad. Cada operación se revisa según el bien, las partes y los impuestos implicados.</p>
          <div class="service-detail__analysis">
            <div>
              <h4>Qué analizamos</h4>
              <ul class="service-detail__list">
                <li>Situación registral, cargas, nota simple, referencia catastral y documentación de la comunidad.</li>
                <li>Contratos de arras, compraventa, donación o alquiler y su impacto fiscal: ITP, plusvalía, sucesiones y donaciones.</li>
                <li>Vehículos y bienes muebles: titularidad, cargas, contrato y cambio de titular ante Tráfico.</li>
              </ul>
            </div>
            <div>
              <h4>Qué obtienes</h4>
              <ul class="service-detail__list">
                <li>Revisión previa de la documentación esencial y de los riesgos visibles.</li>
                <li>Coordinación de impuestos, notaría y registro cuando proceda.</li>
                <li>Preparación o revisión de contratos, liquidaciones y justificantes.</li>
                <li>Acompañamiento hasta la firma, la presentación o el cambio de titularidad.</li>
              </ul>
            </div>
          </div>
          <p class="service-detail__note">Recomendado antes de fijar condiciones, firmar arras o contratos, entregar el bien o presentar un cambio de titularidad.</p>
          <div class="service-card__price">Revisión inicial según la operación</div>
          <div class="service-card__footer"><a href="/reservar-cita" class="btn btn--primary btn--sm">Preparar operación</a><a href="/inmuebles" class="btn btn--outline btn--sm">Ver portal inmobiliario</a></div>
        </div>
      </article>

      <article class="service-card service-detail" data-cat="seguros" id="seguros">
        <div class="service-card__icon"><svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/></svg></div>
        <div class="service-detail__content">
          <h3 class="service-card__title">Seguros</h3>
          <p class="service-card__desc">Orientación sobre los seguros ligados a la vivienda, la comunidad, la actividad profesional y las operaciones inmobiliarias, y gestión de siniestros con la aseguradora. Cualquier propuesta de seguro se confirma con el despacho antes de contratar.</p>
          <div class="service-detail__analysis">
            <div>
              <h4>Qué analizamos</h4>
              <ul class="service-detail__list">
                <li>Póliza vigente, coberturas, exclusiones y vencimientos.</li>
                <li>Partes de siniestro, daños, presupuestos de reparación y comunicaciones con la compañía.</li>
                <li>Necesidades de comunidades, viviendas y actividad profesional.</li>
              </ul>
            </div>
            <div>
              <h4>Qué obtienes</h4>
              <ul class="service-detail__list">
                <li>Revisión de si la cobertura encaja con el riesgo que quieres cubrir.</li>
                <li>Gestión del parte y seguimiento con la aseguradora.</li>
                <li>Orden documental para reclamar o renovar con criterio.</li>
              </ul>
            </div>
          </div>
          <p class="service-detail__note">Útil si ha ocurrido un siniestro, vas a renovar una póliza o quieres saber si estás bien cubierto.</p>
          <div class="service-card__price">Consulta según la necesidad</div>
          <div class="service-card__footer"><a href="/contacto" class="btn btn--primary btn--sm">Consultar por escrito</a></div>
        </div>
      </article>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="container">
    <h2 class="cta-band__title">¿No encuentras lo que necesitas?</h2>
    <p class="cta-band__desc">Cuéntanos tu caso y te haremos una propuesta personalizada en menos de 24h.</p>
    <div class="cta-band__actions">
      <a href="/contacto" class="btn btn--primary btn--lg">Contactar ahora</a>
      <a href="tel:{{ config('site.phone_tel') }}" class="btn btn--outline btn--lg"><x-icono n="tel" /> {{ config('site.phone') }}</a>
    </div>
  </div>
</section>
@endsection
