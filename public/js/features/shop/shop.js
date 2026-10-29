import { $, on } from '../../lib/dom.js';
import { getJson } from '../../lib/api.js';
import { formatEuroCents, toast } from '../../lib/ui.js';
import { cart } from './cart.js';

const MODE_LABELS = { telematica: 'Telemática', presencial: 'Presencial' };

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

// Una opción comprable (o, mientras no haya pagos, un enlace para reservar cita directamente).
function renderOption(product, canBuy) {
  const row = el('div', 'shop-option');
  row.dataset.productId = product.id;
  const info = el('div', 'shop-option__info');
  info.append(el('span', 'shop-option__mode', MODE_LABELS[product.mode] || 'Consulta'), el('span', 'shop-option__price', formatEuroCents(product.priceCents)));
  row.append(info);

  if (canBuy) {
    const button = el('button', 'btn btn--primary btn--sm', 'Añadir al carrito');
    button.type = 'button';
    button.setAttribute('aria-label', `Añadir al carrito: ${product.name}`);
    on(button, 'click', () => {
      cart.add(product.id);
      toast('Añadido al carrito.');
    });
    row.append(button);
  } else {
    const link = el('a', 'btn btn--outline btn--sm', 'Reservar cita');
    link.href = '/reservar-cita';
    row.append(link);
  }
  return row;
}

// Agrupa las consultas por servicio (una tarjeta por área, con sus modalidades) y deja el resto como tarjeta propia.
function groupProducts(products) {
  const groups = new Map();
  for (const product of products) {
    const key = product.serviceId || product.id;
    if (!groups.has(key)) groups.set(key, { title: product.name.replace(/\s*\([^)]*\)$/, ''), description: product.description, items: [] });
    groups.get(key).items.push(product);
  }
  return [...groups.values()];
}

function renderGroup(group, canBuy) {
  const card = el('article', 'shop-card');
  card.append(el('h3', 'shop-card__title', group.title), el('p', 'shop-card__desc', group.description));
  const options = el('div', 'shop-card__options');
  group.items.forEach(product => options.append(renderOption(product, canBuy)));
  card.append(options);
  return card;
}

export async function initShop() {
  const grid = $('#shop-products');
  if (!grid) return;
  const status = $('#shop-status');
  try {
    const data = await getJson('/api/nortia/shop/products');
    grid.replaceChildren(...groupProducts(data.products).map(group => renderGroup(group, data.paymentsEnabled)));
  } catch {
    if (status) {
      status.hidden = false;
      status.textContent = 'No se ha podido cargar la tienda. Inténtalo de nuevo en unos minutos.';
    }
  }
}
