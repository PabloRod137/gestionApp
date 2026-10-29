import { getJson, postJson } from '../../lib/api.js';

const MAX_TEXT = 500;
const MAX_DESCRIPTION = 1000;
// Solo se enlazan páginas del propio sitio y descargas de documentos propios.
const SAFE_HREF = /^\/(cliente\/(expedientes|documentos|facturas|citas)(\/\d+)?(\/pdf)?|cliente\/mis-datos|[a-z-]+)(#[\w-]+)?$/;

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

// Widget del chat: pinta mensajes, botones y tarjetas con textContent (nunca HTML) y habla con la API.
//   historyUrl  GET → { messages, state }       messageUrl  POST { text } | { action, label } → { messages, state }
//   carryState  el estado del flujo lo guarda el navegador (chat público, sin sesión)
//   initial     mensaje inicial silencioso (saludo) cuando no hay historial
export function createChatWidget({ container, historyUrl, messageUrl, onUnauthorized = () => {}, carryState = false, initial = null }) {
  container.replaceChildren();
  const root = el('div', 'chat');
  const log = el('div', 'chat__log');
  log.setAttribute('role', 'log');
  log.setAttribute('aria-live', 'polite');
  log.setAttribute('aria-label', 'Conversación con el asistente');

  const form = el('form', 'chat__form');
  const inputId = `chat-input-${Math.random().toString(36).slice(2, 8)}`;
  const label = el('label', 'sr-only', 'Escribe tu mensaje');
  label.htmlFor = inputId;
  const input = el('input', 'chat__input');
  input.id = inputId;
  input.type = 'text';
  input.maxLength = MAX_TEXT;
  input.autocomplete = 'off';
  input.placeholder = 'Escribe tu mensaje…';
  const send = el('button', 'btn btn--primary btn--sm chat__send', 'Enviar');
  send.type = 'submit';
  form.append(label, input, send);
  root.append(log, form);
  container.append(root);

  let currentState = null;
  const scrollDown = () => { log.scrollTop = log.scrollHeight; };
  // Una respuesta se lee desde su primera línea, aunque sea larga
  const revealTop = node => { log.scrollTop += node.getBoundingClientRect().top - log.getBoundingClientRect().top - 8; };
  const clearButtons = () => log.querySelectorAll('.chat__buttons').forEach(node => node.remove());

  function addUser(text) {
    log.append(el('div', 'chat__msg chat__msg--user', text));
    scrollDown();
  }

  function renderCard(card) {
    const node = el('div', 'chat__card');
    node.append(el('strong', 'chat__card-title', card.title));
    (card.lines || []).forEach(line => node.append(el('p', 'chat__card-line', line)));
    if (card.href && SAFE_HREF.test(card.href)) {
      const link = el('a', 'chat__link', /\/(documents|documentos)\/|\/pdf$/.test(card.href) ? 'Descargar' : 'Abrir');
      link.href = card.href;
      node.append(link);
    }
    if (card.action) {
      const button = el('button', 'chat__button', card.action.id === 'documents' ? 'Ver documentos' : 'Abrir');
      button.type = 'button';
      button.addEventListener('click', () => sendAction(card.action, button.textContent));
      node.append(button);
    }
    return node;
  }

  function addBot(message, { interactive = true } = {}) {
    const bubble = el('div', 'chat__msg chat__msg--bot');
    bubble.append(el('p', 'chat__text', message.text || ''));
    if (message.cards?.length) {
      const cards = el('div', 'chat__cards');
      message.cards.forEach(card => cards.append(renderCard(card)));
      bubble.append(cards);
    }
    if (interactive && message.buttons?.length) {
      const buttons = el('div', 'chat__buttons');
      message.buttons.forEach(item => {
        const button = el('button', 'chat__button', item.label);
        button.type = 'button';
        button.addEventListener('click', () => sendAction({ id: item.id, payload: item.payload }, item.label));
        buttons.append(button);
      });
      bubble.append(buttons);
    }
    log.append(bubble);
    revealTop(bubble);
    return bubble;
  }

  function setBusy(busy) {
    input.disabled = busy;
    send.disabled = busy;
    root.setAttribute('aria-busy', String(busy));
  }

  function applyState(state) {
    currentState = state ?? null;
    input.maxLength = state?.step === 'description' ? MAX_DESCRIPTION : MAX_TEXT;
  }

  function renderMessages(messages) {
    clearButtons();
    const bubbles = messages.map(message => addBot(message));
    if (bubbles.length) revealTop(bubbles[0]);
  }

  async function submit(body, echo) {
    addUser(echo);
    clearButtons();
    setBusy(true);
    try {
      const result = await postJson(messageUrl, carryState ? { ...body, state: currentState } : body);
      renderMessages(result.messages);
      applyState(result.state);
    } catch (error) {
      if (error.status === 401) return onUnauthorized();
      addBot({ text: error.status === 400 && error.payload?.message ? error.payload.message : 'No he podido responder ahora. Inténtalo de nuevo en un momento.' }, { interactive: false });
    } finally {
      setBusy(false);
      input.focus();
    }
    return undefined;
  }

  function sendAction(action, echo) {
    return submit({ action: { id: action.id, payload: action.payload }, label: echo }, echo);
  }

  form.addEventListener('submit', event => {
    event.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    submit({ text }, text);
  });

  (async () => {
    setBusy(true);
    try {
      if (!historyUrl) {
        const greeting = await postJson(messageUrl, carryState ? { ...initial, state: null } : initial);
        renderMessages(greeting.messages);
        applyState(greeting.state);
        return;
      }
      const history = await getJson(historyUrl);
      const messages = history.messages || [];
      const lastBot = messages.map(message => message.role).lastIndexOf('bot');
      messages.forEach((message, index) => {
        if (message.role === 'user') addUser(message.text);
        else addBot(message, { interactive: index === lastBot });
      });
      applyState(history.state);
    } catch (error) {
      if (error.status === 401) onUnauthorized();
      else addBot({ text: 'El asistente no está disponible ahora mismo.' }, { interactive: false });
    } finally {
      setBusy(false);
    }
  })();

  return { destroy: () => container.replaceChildren() };
}
