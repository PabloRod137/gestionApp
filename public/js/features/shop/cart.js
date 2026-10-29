// Carrito en el navegador: solo identificadores y cantidades (los precios los pone siempre el servidor).
const KEY = 'lg_cart_v1';
export const MAX_QUANTITY = 5;
const MAX_LINES = 10;

// Si localStorage no está disponible (navegación privada, bloqueo), el carrito vive en memoria durante la visita.
let memory = null;

function sanitize(raw) {
  if (!Array.isArray(raw)) return [];
  const merged = new Map();
  for (const item of raw) {
    if (!item || typeof item.productId !== 'string' || !Number.isInteger(item.quantity)) continue;
    const quantity = Math.min(MAX_QUANTITY, (merged.get(item.productId) || 0) + Math.max(1, item.quantity));
    merged.set(item.productId, quantity);
  }
  return [...merged].slice(0, MAX_LINES).map(([productId, quantity]) => ({ productId, quantity }));
}

function load() {
  if (memory) return memory;
  try {
    return sanitize(JSON.parse(localStorage.getItem(KEY)));
  } catch {
    return []; // JSON corrupto o almacenamiento inaccesible: se parte de un carrito vacío
  }
}

function save(items) {
  try {
    localStorage.setItem(KEY, JSON.stringify(items));
    memory = null;
  } catch {
    memory = items;
  }
  document.dispatchEvent(new CustomEvent('lg:cart-changed'));
}

export const cart = {
  items: load,
  count: () => load().reduce((sum, item) => sum + item.quantity, 0),
  add(productId) {
    save(sanitize([...load(), { productId, quantity: 1 }]));
  },
  // Una cantidad menor que 1 elimina la línea.
  setQuantity(productId, quantity) {
    const others = load().filter(item => item.productId !== productId);
    save(quantity >= 1 ? sanitize([...others, { productId, quantity }]) : others);
  },
  remove(productId) {
    save(load().filter(item => item.productId !== productId));
  },
  clear() {
    save([]);
  }
};
