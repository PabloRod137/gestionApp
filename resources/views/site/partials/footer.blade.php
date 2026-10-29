<footer class="footer">
  <div class="container">
    <div class="footer__grid">
      <div>
        <a href="/" class="footer__brand-lockup" aria-label="Nortia - Inicio"><img src="/assets/logo-mark.png" alt="" class="footer__brand-mark"><span><span class="footer__brand-name">NORTIA</span><span class="footer__brand-subtitle">LEGAL - TAX - REAL ESTATE</span></span></a>
        <p class="footer__desc">En Nortia defendemos tus intereses, protegemos tu patrimonio y damos soluciones legales rápidas y eficaces para tu familia, empresa o actividad profesional.</p>
      </div>
      <div>
        <div class="footer__col-title">Servicios</div>
        <ul class="footer__links">
          <li><a href="/servicios#legal">Legal</a></li>
          <li><a href="/servicios#fiscal">Fiscal</a></li>
          <li><a href="/servicios#laboral">Laboral</a></li>
          <li><a href="/servicios#comunidades">Comunidades</a></li>
          <li><a href="/servicios#inmobiliario">Inmobiliario</a></li>
          <li><a href="/servicios#seguros">Seguros</a></li>
          <li><a href="/tienda">Tienda de consultas</a></li>
        </ul>
      </div>
      <div>
        <div class="footer__col-title">Normativa</div>
        <ul class="footer__links">
          <li><a href="/aviso-legal">Aviso legal</a></li>
          <li><a href="/politica-de-privacidad">Política de privacidad</a></li>
          <li><a href="/politica-de-cookies">Política de cookies</a></li>
          <li><a href="/condiciones-de-contratacion">Condiciones de contratación</a></li>
        </ul>
      </div>
      <div>
        <div class="footer__col-title">Contacta con nosotros</div>
        <div class="footer__contact-item"><x-icono n="mail" /><a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a></div>
        <div class="footer__contact-item"><x-icono n="tel" /><a href="tel:{{ config('site.phone_tel') }}">{{ config('site.phone') }}</a></div>
        <div class="footer__contact-item"><x-icono n="pin" /><span>{{ config('site.address') }}</span></div>
        <div class="footer__contact-link"><a href="/contacto">Formulario de contacto</a></div>
        <a href="/login" class="footer__portal" aria-label="Área Cliente - acceso privado">
          <div class="footer__portal-icon" aria-hidden="true"><x-icono n="candado" /></div>
          <div>
            <div class="footer__portal-name">ÁREA CLIENTE</div>
            <div class="footer__portal-text">Acceso privado a tus expedientes y documentos</div>
          </div>
        </a>
      </div>
    </div>
    <div class="footer__bottom">
      <p class="footer__copy">© 2026 {{ config('site.copyright') }}. Todos los derechos reservados.</p>
    </div>
  </div>
</footer>
