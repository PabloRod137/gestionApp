@php
    // La página actual queda marcada en el menú (dorado y subrayado) y se anuncia a los lectores de pantalla.
    $actual = trim($__env->yieldContent('nav'));
    $aqui = fn (string $clave) => $actual === $clave ? ' aria-current="page"' : '';
@endphp
<a class="skip-link" href="#content">Saltar al contenido</a>
<header>
  <nav class="nav" aria-label="Navegación principal">
    <div class="container">
      <div class="nav__inner">
        <a href="/" class="nav__logo nav__brand-lockup" aria-label="Nortia - Inicio"><img src="/assets/logo-mark.png" alt="" class="nav__brand-mark"><span><span class="nav__brand-name">NORTIA</span><span class="nav__brand-subtitle">LEGAL - TAX - REAL ESTATE</span></span></a>
        <ul class="nav__menu" id="nav-menu">
          <li><a href="/" data-nav="inicio"{!! $aqui('inicio') !!}>Inicio</a></li>
          <li><a href="/quienes-somos" data-nav="quienes-somos"{!! $aqui('quienes-somos') !!}>Quiénes somos</a></li>
          <li class="nav__item--dropdown">
            <a href="/servicios" data-nav="servicios"{!! $aqui('servicios') !!} aria-haspopup="true" aria-controls="nav-services" aria-expanded="false">Servicios</a>
            <div class="nav__dropdown" id="nav-services">
              <a href="/servicios#legal">Legal</a>
              <a href="/servicios#fiscal">Fiscal</a>
              <a href="/servicios#laboral">Laboral</a>
              <a href="/servicios#comunidades">Comunidades</a>
              <a href="/servicios#inmobiliario">Inmobiliario</a>
              <a href="/servicios#seguros">Seguros</a>
            </div>
          </li>
          <li><a href="/blog" data-nav="blog"{!! $aqui('blog') !!}>Blog</a></li>
          <li><a href="/inmuebles" data-nav="inmuebles"{!! $aqui('inmuebles') !!}>Inmuebles</a></li>
          <li><a href="/contacto" data-nav="contacto"{!! $aqui('contacto') !!}>Contactar</a></li>
          <li class="nav__cta"><a href="/reservar-cita" class="btn btn--primary btn--sm">Reserva cita</a></li>
          <li class="nav__cart-item"><a class="nav__cart" href="/carrito" aria-label="Carrito de compra"><svg class="nav__cart-icon" aria-hidden="true" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 7H6"/></svg><span class="nav__cart-label">Carrito</span><span class="nav__cart-count" hidden>0</span></a></li>
        </ul>
        <button class="nav__toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="nav-menu"><span></span><span></span><span></span></button>
      </div>
    </div>
  </nav>
</header>
