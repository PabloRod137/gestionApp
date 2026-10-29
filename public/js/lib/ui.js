import { $, $$ } from './dom.js';

export function toast(message, type = 'success') {
  let node = $('#toast');
  if (!node) {
    node = document.createElement('div');
    node.id = 'toast';
    node.className = 'toast';
    document.body.appendChild(node);
  }
  node.textContent = message;
  node.className = `toast active${type === 'error' ? ' toast--error' : ''}`;
  window.setTimeout(() => node.classList.remove('active'), 4500);
}

export const formatEuroCents = cents =>
  (Number(cents || 0) / 100).toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });

export function formDataObject(form) {
  const data = new FormData(form);
  const result = Object.fromEntries(data.entries());
  result.privacy = ['on', 'true'].includes(data.get('privacy'));
  result.website = data.get('website') || '';
  return result;
}

// Bloquea el formulario mientras se envía, salvo los controles marcados como bloqueados a propósito.
export function setFormBusy(form, busy) {
  $$('button, input, select, textarea', form).forEach(item => {
    item.disabled = busy || item.dataset.locked === 'true';
  });
}

export function showFormMessage(form, message, type = 'success') {
  let node = form.querySelector('.form-status');
  if (!node) {
    node = document.createElement('div');
    node.setAttribute('role', 'status');
    form.appendChild(node);
  }
  node.textContent = message;
  node.className = `form-status form-status--${type}`;
}

// Campo trampa para bots: fuera de la vista y del orden de tabulación.
export function addHoneypot(form) {
  if (form.querySelector('input[name="website"]')) return;
  form.insertAdjacentHTML('afterbegin', '<input class="lg-hp-field" type="text" name="website" tabindex="-1" autocomplete="new-password" aria-hidden="true">');
}

const FORM_ERRORS = {
  slot_occupied: 'La franja seleccionada ya no está disponible.',
  slot_not_available: 'Ese horario no está disponible para el servicio seleccionado.',
  unknown_service_or_mode: 'Selecciona un servicio y una modalidad disponibles.',
  rate_limited: 'Demasiados intentos seguidos. Espera unos minutos y vuelve a probar.',
  invalid_booking_key: 'La clave de reserva no es válida o ha caducado.',
  booking_key_used: 'Esta clave de reserva ya se ha utilizado.',
  csrf_required: 'La sesión del formulario ha caducado. Recarga la página y vuelve a enviarlo.'
};

export function formErrorText(error) {
  const fields = error.payload?.fields;
  if (Array.isArray(fields) && fields.length) return fields.map(item => item.message || item.field).join(' ');
  return FORM_ERRORS[error.payload?.error] || 'No se pudo enviar. Revisa los datos o inténtalo de nuevo en unos minutos.';
}
