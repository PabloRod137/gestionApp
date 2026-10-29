import { $$, on } from '../lib/dom.js';

// El mapa de OpenStreetMap solo se carga cuando la persona pulsa el botón: hasta entonces no se contacta con terceros.
export function initMaps() {
  $$('.map-embed[data-map-src]').forEach(container => {
    on(container.querySelector('[data-map-show]'), 'click', () => {
      const frame = document.createElement('iframe');
      frame.src = container.dataset.mapSrc;
      frame.title = 'Mapa de la ubicación de Nortia en Santander';
      frame.loading = 'lazy';
      frame.referrerPolicy = 'no-referrer';
      container.replaceChildren(frame);
    });
  });
}
