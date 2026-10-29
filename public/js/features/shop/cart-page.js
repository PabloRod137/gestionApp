import { $, on } from '../../lib/dom.js';
import { getJson, postJson } from '../../lib/api.js';
import { formatEuroCents, setFormBusy, showFormMessage } from '../../lib/ui.js';
import { cart, MAX_QUANTITY } from './cart.js';

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function renderLine(product, quantity) {
  const line = document.createElement('div');
  line.className = 'cart-line';
  const name = document.createElement('span');
  name.className = 'cart-line__name';
  name.textContent = product.name;

  const select = document.createElement('select');
  select.className = 'form-control cart-line__qty';
  select.setAttribute('aria-label', `Cantidad de ${product.name}`);
  for (let value = 1; value <= MAX_QUANTITY; value += 1) {
    const option = document.createElement('option');
    option.value = String(value);
    option.textContent = String(value);
    option.selected = value === quantity;
    select.append(option);
  }
  on(select, 'change', () => cart.setQuantity(product.id, Number(select.value)));

  const total = document.createElement('span');
  total.className = 'cart-line__total';
  total.textContent = formatEuroCents(product.priceCents * quantity);

  const remove = document.createElement('button');
  remove.type = 'button';
  remove.className = 'cart-line__remove';
  remove.textContent = 'Quitar';
  remove.setAttribute('aria-label', `Quitar ${product.name} del carrito`);
  on(remove, 'click', () => cart.remove(product.id));

  line.append(name, select, total, remove);
  return line;
}

// Solo se redirige a una dirección https (la de Stripe Checkout, o la de retorno en las pruebas).
function safeRedirect(url) {
  try {
    const target = new URL(url);
    if (target.protocol === 'https:' || target.origin === window.location.origin) window.location.assign(target.href);
  } catch {
    // dirección inválida: no se hace nada
  }
}

export async function initCartPage() {
  const form = $('#cart-form');
  if (!form) return;
  const empty = $('#cart-empty');
  const pay = $('#cart-pay');
  let products = new Map();
  let paymentsEnabled = true;

  try {
    const data = await getJson('/api/nortia/shop/products');
    products = new Map(data.products.map(product => [product.id, product]));
    paymentsEnabled = data.paymentsEnabled;
  } catch {
    showFormMessage(form, 'No se ha podido cargar el carrito. Inténtalo de nuevo en unos minutos.', 'error');
  }

  function render() {
    // Los productos que ya no existen en la tienda se descartan del carrito.
    const lines = cart.items().filter(item => products.has(item.productId));
    const isEmpty = lines.length === 0;
    empty.hidden = !isEmpty;
    form.hidden = isEmpty;
    if (isEmpty) return;
    $('#cart-lines').replaceChildren(...lines.map(item => renderLine(products.get(item.productId), item.quantity)));
    $('#cart-total').textContent = formatEuroCents(lines.reduce((sum, item) => sum + products.get(item.productId).priceCents * item.quantity, 0));
    pay.disabled = !paymentsEnabled;
    if (!paymentsEnabled) showFormMessage(form, 'Los pagos online no están disponibles por ahora.', 'error');
  }

  render();
  on(document, 'lg:cart-changed', render);

  on(form, 'submit', async event => {
    event.preventDefault();
    const email = $('#cart-email').value.trim();
    if (!EMAIL.test(email)) return showFormMessage(form, 'Escribe un correo electrónico válido.', 'error');
    if (!$('#cart-terms').checked) return showFormMessage(form, 'Debes aceptar las condiciones de contratación para pagar.', 'error');
    setFormBusy(form, true);
    try {
      const result = await postJson('/api/nortia/shop/checkout', {
        items: cart.items().map(({ productId, quantity }) => ({ productId, quantity })),
        email,
        acceptTerms: true,
        note: $('#cart-note').value.trim()
      });
      safeRedirect(result.url);
    } catch (error) {
      showFormMessage(form, error.payload?.message || 'No se ha podido iniciar el pago. Inténtalo de nuevo.', 'error');
      setFormBusy(form, false);
    }
    return undefined;
  });
}
