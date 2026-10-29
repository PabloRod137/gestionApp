import { $, $$, on } from '../../lib/dom.js';
import { cart } from './cart.js';

// Número de artículos junto al icono del carrito de la cabecera.
export function initCartBadge() {
  const render = () => {
    const count = cart.count();
    $$('.nav__cart-count').forEach(badge => {
      badge.textContent = String(count);
      badge.hidden = count === 0;
    });
    const link = $('.nav__cart');
    if (link) link.setAttribute('aria-label', count ? `Carrito de compra, ${count} ${count === 1 ? 'artículo' : 'artículos'}` : 'Carrito de compra');
  };
  render();
  on(document, 'lg:cart-changed', render);
  on(window, 'storage', render);
}
