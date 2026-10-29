import { $, $$, on } from '../lib/dom.js';

const MOBILE_QUERY = '(max-width: 1180px)';
const isMobile = () => window.matchMedia(MOBILE_QUERY).matches;

function closeDropdowns() {
  $$('.nav__item--dropdown.open').forEach(item => {
    item.classList.remove('open');
    item.querySelector(':scope > a')?.setAttribute('aria-expanded', 'false');
  });
}

function initDropdown(item) {
  const trigger = item.querySelector(':scope > a');
  if (!trigger) return;
  // En móvil el enlace "Servicios" abre el submenú; en escritorio se abre con hover o foco.
  on(trigger, 'click', event => {
    if (!isMobile()) return;
    event.preventDefault();
    event.stopPropagation();
    const open = !item.classList.contains('open');
    closeDropdowns();
    item.classList.toggle('open', open);
    trigger.setAttribute('aria-expanded', String(open));
  });
  on(item, 'keydown', event => {
    if (event.key !== 'Escape') return;
    closeDropdowns();
    trigger.focus();
  });
}

export function initNav() {
  const nav = $('.nav');
  const toggle = $('.nav__toggle');
  const menu = $('.nav__menu');

  on(window, 'scroll', () => nav?.classList.toggle('scrolled', window.scrollY > 20));

  on(toggle, 'click', () => {
    const open = menu?.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(Boolean(open)));
    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    document.body.classList.toggle('menu-abierto', Boolean(open));
  });

  const cerrar = () => {
    menu?.classList.remove('open');
    toggle?.setAttribute('aria-expanded', 'false');
    toggle?.setAttribute('aria-label', 'Abrir menú');
    document.body.classList.remove('menu-abierto');
  };
  on(document, 'keydown', event => { if (event.key === 'Escape' && menu?.classList.contains('open')) cerrar(); });
  on(window, 'resize', () => { if (!isMobile()) cerrar(); });

  $$('.nav__item--dropdown').forEach(initDropdown);

  $$('.nav__menu a').forEach(link => on(link, 'click', () => {
    if (!isMobile() || link.matches('.nav__item--dropdown > a')) return;
    cerrar();
  }));

  on(document, 'click', event => {
    if (!event.target.closest('.nav__item--dropdown')) closeDropdowns();
  });
}
