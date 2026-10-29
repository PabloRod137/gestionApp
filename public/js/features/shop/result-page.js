import { $ } from '../../lib/dom.js';
import { getJson } from '../../lib/api.js';
import { formatEuroCents } from '../../lib/ui.js';
import { cart } from './cart.js';

const POLL_MS = 2000;
const MAX_ATTEMPTS = 15;
const wait = ms => new Promise(resolve => { setTimeout(resolve, ms); });

const MESSAGES = {
  pending: 'Confirmando tu pago…',
  paid: 'Pago confirmado. ¡Gracias!',
  expired: 'El pago no se ha completado. No se ha realizado ningún cobro.',
  refunded: 'Este pedido ha sido reembolsado.'
};

function renderSummary(order) {
  const summary = $('#order-summary');
  const list = document.createElement('ul');
  order.items.forEach(item => {
    const row = document.createElement('li');
    row.textContent = `${item.quantity} × ${item.name}: ${formatEuroCents(item.unitPriceCents * item.quantity)}`;
    list.append(row);
  });
  const total = document.createElement('p');
  total.textContent = `Pedido ${order.id} · Total ${formatEuroCents(order.amountCents)} (IVA incluido)`;
  summary.replaceChildren(list, total);
}

function renderBookingLinks(links) {
  const box = $('#order-booking');
  const list = $('#order-booking-links');
  box.hidden = links.length === 0;
  list.replaceChildren(...links.map(item => {
    const row = document.createElement('li');
    const anchor = document.createElement('a');
    // Solo se enlaza a la reserva del propio sitio.
    anchor.href = new URL(item.url, window.location.origin).pathname + new URL(item.url, window.location.origin).search;
    anchor.textContent = `Reservar: ${item.name}`;
    row.append(anchor);
    return row;
  }));
}

// El navegador no da el pago por bueno: pregunta al servidor, que solo cambia el estado cuando Stripe lo confirma.
export async function initOrderResult() {
  const status = $('#order-status');
  if (!status) return;
  const params = new URLSearchParams(window.location.search);
  const orderId = params.get('pedido');
  const key = params.get('clave');
  if (!orderId || !key) {
    status.textContent = 'No encontramos el pedido. Si acabas de pagar, revisa tu correo: te hemos enviado la confirmación.';
    return;
  }
  // La clave no debe quedar en la barra de direcciones ni en el historial.
  window.history.replaceState(null, '', window.location.pathname);

  for (let attempt = 0; attempt < MAX_ATTEMPTS; attempt += 1) {
    try {
      const { order, bookingLinks } = await getJson(`/api/nortia/shop/orders/${encodeURIComponent(orderId)}?clave=${encodeURIComponent(key)}`);
      status.textContent = MESSAGES[order.status] || MESSAGES.pending;
      renderSummary(order);
      if (order.status === 'paid') {
        cart.clear();
        renderBookingLinks(bookingLinks);
        return;
      }
      if (order.status !== 'pending') return;
    } catch {
      status.textContent = 'No encontramos el pedido. Si acabas de pagar, revisa tu correo.';
      return;
    }
    await wait(POLL_MS);
  }
  status.textContent = 'Tu pago aún se está procesando. Cuando se confirme recibirás un correo con los siguientes pasos.';
}
