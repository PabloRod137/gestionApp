import { $, $$, on, escapeHtml } from '../lib/dom.js';
import { getJson, postJson } from '../lib/api.js';
import { addHoneypot, formDataObject, formatEuroCents, setFormBusy, showFormMessage, formErrorText, toast } from '../lib/ui.js';

const MIN_NOTICE_MS = 120 * 60 * 1000;
const services = new Map();

// Enlace de reserva de una consulta ya pagada: ?pedido=…&clave=…&consulta=consulta-<servicio>-<modalidad>
const bookingParams = new URLSearchParams(window.location.search);
const paidBooking = bookingParams.get('pedido') && bookingParams.get('clave')
  ? { orderId: bookingParams.get('pedido'), key: bookingParams.get('clave'), consulta: bookingParams.get('consulta') || '' }
  : null;

const dayLabel = date => date.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' });

// Estado y dibujo del calendario mensual y de las franjas horarias de un día.
const calendar = {
  year: new Date().getFullYear(),
  month: new Date().getMonth(),
  selected: null,
  selectedSlot: null,
  availableSlots: [],

  render() {
    const container = $('#calendar-days');
    if (!container) return;
    const earliest = new Date(Date.now() + MIN_NOTICE_MS);
    earliest.setHours(0, 0, 0, 0);
    const startOffset = (new Date(this.year, this.month, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(this.year, this.month + 1, 0).getDate();
    const title = $('#cal-title');
    if (title) title.textContent = new Date(this.year, this.month).toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });

    // Los huecos de otros meses solo alinean la cuadrícula: celdas vacías, sin números que confundan.
    const blank = '<div class="calendar-day calendar-day--other-month" aria-hidden="true"></div>';
    let html = '';
    for (let i = 0; i < startOffset; i += 1) html += blank;
    for (let day = 1; day <= daysInMonth; day += 1) {
      const date = new Date(this.year, this.month, day);
      const weekend = date.getDay() === 0 || date.getDay() === 6;
      const disabled = date < earliest || weekend;
      const state = disabled ? 'disabled' : this.selected === day ? 'selected' : 'available';
      html += `<button type="button" class="calendar-day calendar-day--${state}" data-day="${day}" ${disabled ? 'disabled' : ''}>${day}</button>`;
    }
    const trailing = (7 - ((startOffset + daysInMonth) % 7)) % 7;
    for (let i = 0; i < trailing; i += 1) html += blank;
    container.innerHTML = html;
    $$('[data-day]', container).forEach(button => on(button, 'click', () => this.selectDay(Number(button.dataset.day))));
  },

  async selectDay(day) {
    this.selected = day;
    this.selectedSlot = null;
    this.availableSlots = [];
    this.render();
    $('#time-slots')?.classList.add('is-visible');
    const label = $('#slots-date');
    if (label) label.textContent = dayLabel(new Date(this.year, this.month, day));
    await this.loadSlots();
    updateSummary();
  },

  async loadSlots() {
    const container = $('#slots-grid');
    const service = $('#booking-service')?.value;
    if (!container || !service || !this.selected) return;
    container.innerHTML = '<p class="form-status">Cargando horarios disponibles...</p>';
    try {
      const data = await getJson(`/api/nortia/availability?service=${encodeURIComponent(service)}&date=${encodeURIComponent(this.isoDate())}`);
      this.availableSlots = data.slots || [];
      this.renderSlots();
    } catch {
      this.availableSlots = [];
      container.innerHTML = '<p class="form-status form-status--error">No se pudo cargar la disponibilidad.</p>';
    }
  },

  renderSlots() {
    const container = $('#slots-grid');
    if (!container) return;
    if (!this.availableSlots.length) {
      container.innerHTML = '<p class="form-status">No hay horarios disponibles para esa fecha.</p>';
      return;
    }
    container.innerHTML = this.availableSlots.map(time =>
      `<button type="button" class="slot-btn${this.selectedSlot === time ? ' slot-btn--selected' : ''}" data-slot="${escapeHtml(time)}">${escapeHtml(time)}</button>`
    ).join('');
    $$('[data-slot]', container).forEach(button => on(button, 'click', () => this.selectSlot(button.dataset.slot)));
  },

  selectSlot(time) {
    if (!this.availableSlots.includes(time)) return;
    this.selectedSlot = time;
    this.renderSlots();
    updateSummary();
  },

  shiftMonth(delta) {
    const target = new Date(this.year, this.month + delta, 1);
    this.year = target.getFullYear();
    this.month = target.getMonth();
    this.reset();
  },

  reset() {
    this.selected = null;
    this.selectedSlot = null;
    this.availableSlots = [];
    this.render();
    $('#time-slots')?.classList.remove('is-visible');
    updateSummary();
  },

  isoDate() {
    if (!this.selected) return '';
    return `${this.year}-${String(this.month + 1).padStart(2, '0')}-${String(this.selected).padStart(2, '0')}`;
  }
};

function updateSummary() {
  const service = services.get($('#booking-service')?.value);
  const mode = $('#booking-mode')?.value || 'telematica';
  const price = $('#booking-price-summary');
  if (price && paidBooking) {
    price.textContent = `Consulta ya pagada (pedido ${paidBooking.orderId}). Elige día y hora.`;
  } else if (price && service?.modes?.[mode]) {
    price.textContent = `${formatEuroCents(service.modes[mode].priceCents)}. Solicitud de cita pendiente de confirmación; no se realiza ningún pago en la web.`;
  }
  const summary = $('#booking-summary');
  const submit = $('#booking-submit');
  if (!summary || !submit) return;
  const ready = calendar.selected && calendar.selectedSlot;
  summary.textContent = ready
    ? `${dayLabel(new Date(calendar.year, calendar.month, calendar.selected))} a las ${calendar.selectedSlot}`
    : 'Selecciona primero día y hora en el calendario.';
  submit.disabled = !ready;
  submit.dataset.locked = ready ? 'false' : 'true';
}

async function loadServices() {
  const select = $('#booking-service');
  const data = await getJson('/api/nortia/services');
  services.clear();
  data.services.forEach(service => services.set(service.id, service));
  select.innerHTML = data.services.map(service => `<option value="${escapeHtml(service.id)}">${escapeHtml(service.name)}</option>`).join('');
}

function bookingPayload(form) {
  const raw = formDataObject(form);
  return {
    // En una reserva pagada el servicio y la modalidad están bloqueados (los controles deshabilitados no viajan en el formulario).
    service: $('#booking-service').value,
    mode: $('#booking-mode').value,
    name: raw.name,
    email: raw.email,
    phone: raw.phone,
    date: calendar.isoDate(),
    time: calendar.selectedSlot,
    notes: raw.notes,
    privacy: raw.privacy,
    website: raw.website,
    ...(paidBooking ? { pedido: paidBooking.orderId, clave: paidBooking.key } : {})
  };
}

// Preselecciona y bloquea la consulta comprada. El servidor vuelve a comprobar que coincide con la clave.
function lockPaidConsultation() {
  const match = /^consulta-([a-z]+)-(telematica|presencial)$/.exec(paidBooking.consulta);
  if (match) {
    $('#booking-service').value = match[1];
    $('#booking-mode').value = match[2];
  }
  ['#booking-service', '#booking-mode'].forEach(selector => {
    const control = $(selector);
    control.disabled = true;
    control.dataset.locked = 'true';
  });
  // La clave no debe quedar en la barra de direcciones.
  window.history.replaceState(null, '', window.location.pathname);
}

export async function initBooking() {
  const form = $('#booking-form');
  if (!form) return;
  addHoneypot(form);
  const submit = $('#booking-submit');
  if (submit) submit.dataset.locked = 'true';

  try {
    await loadServices();
  } catch {
    showFormMessage(form, 'No se pudo cargar el catálogo de servicios.', 'error');
  }

  if (paidBooking) lockPaidConsultation();

  on($('#booking-service'), 'change', async () => {
    calendar.selectedSlot = null;
    if (calendar.selected) await calendar.loadSlots();
    updateSummary();
  });
  on($('#booking-mode'), 'change', updateSummary);
  $$('[data-action="calendar-prev"]').forEach(button => on(button, 'click', () => calendar.shiftMonth(-1)));
  $$('[data-action="calendar-next"]').forEach(button => on(button, 'click', () => calendar.shiftMonth(1)));

  on(form, 'submit', async event => {
    event.preventDefault();
    if (!calendar.selected || !calendar.selectedSlot) {
      showFormMessage(form, 'Selecciona una fecha y una hora disponibles.', 'error');
      return;
    }
    const payload = bookingPayload(form);
    setFormBusy(form, true);
    try {
      const result = await postJson('/api/nortia/reservations', payload);
      form.reset();
      calendar.reset();
      showFormMessage(form, `${result.message} Referencia: ${result.id}`);
      toast(result.delivery === 'sent' ? 'Solicitud enviada al despacho.' : 'Solicitud guardada; el correo está pendiente de configuración.');
    } catch (err) {
      showFormMessage(form, formErrorText(err), 'error');
      toast('No se pudo enviar la solicitud.', 'error');
    } finally {
      setFormBusy(form, false);
      updateSummary();
    }
  });

  calendar.render();
  updateSummary();
}
