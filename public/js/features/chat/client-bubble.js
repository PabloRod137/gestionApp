import { createChatWidget } from './widget.js';

const CLIENT_URL = '/cliente/chat';

const ICON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.5 7.2L4 20l1-4.2A8 8 0 1 1 21 12z"/></svg>';

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

// Burbuja del asistente privado del Área Cliente. Responde solo con los datos del cliente que ha iniciado sesión
// (el servidor lo toma de la sesión, nunca de lo que se envía desde aquí).
export function initClientChat() {
  const launcher = el('div', 'chat-launcher');
  const bubble = el('button', 'chat-launcher__bubble');
  bubble.innerHTML = ICON + '<span>Asistente</span>';
  bubble.type = 'button';
  bubble.setAttribute('aria-label', 'Abrir el asistente de tu área privada');
  bubble.setAttribute('aria-expanded', 'false');

  const panel = el('section', 'chat-launcher__panel');
  panel.hidden = true;
  panel.setAttribute('aria-label', 'Asistente de tu área privada');
  const header = el('div', 'chat-launcher__header');
  header.append(el('strong', '', 'Tu asistente'));
  const close = el('button', 'chat-launcher__close', '×');
  close.type = 'button';
  close.setAttribute('aria-label', 'Cerrar el asistente');
  header.append(close);
  const notice = el('p', 'chat-launcher__notice', 'Asistente automático: informa de tus gestiones, no da asesoramiento jurídico.');
  const body = el('div', 'chat-launcher__body');
  panel.append(header, notice, body);
  launcher.append(panel, bubble);
  document.body.append(launcher);

  let widget = null;
  const setOpen = open => {
    panel.hidden = !open;
    launcher.classList.toggle('chat-launcher--open', open);
    bubble.setAttribute('aria-expanded', String(open));
    if (open && !widget) {
      widget = createChatWidget({
        container: body,
        messageUrl: CLIENT_URL,
        carryState: true,
        initial: { text: 'hola' },
        // la sesión caducó: se recarga para volver al acceso
        onUnauthorized: () => window.location.reload()
      });
    }
    if (open && !matchMedia('(pointer: coarse)').matches) panel.querySelector('.chat__input')?.focus();
    else if (open) panel.querySelector('.chat-launcher__close')?.focus();
    else bubble.focus();
  };

  bubble.addEventListener('click', () => setOpen(panel.hidden));
  close.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !panel.hidden) setOpen(false);
  });
}

initClientChat();
