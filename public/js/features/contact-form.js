import { $, on } from '../lib/dom.js';
import { postJson } from '../lib/api.js';
import { addHoneypot, formDataObject, setFormBusy, showFormMessage, formErrorText, toast } from '../lib/ui.js';

export function initContactForm() {
  const form = $('#contact-form');
  if (!form) return;
  addHoneypot(form);
  on(form, 'submit', async event => {
    event.preventDefault();
    const payload = formDataObject(form);
    payload.matter = payload.service || payload.matter || '';
    setFormBusy(form, true);
    try {
      const result = await postJson('/api/nortia/contact', payload);
      form.reset();
      showFormMessage(form, `${result.message} Referencia: ${result.id}`);
      toast(result.delivery === 'sent' ? 'Mensaje enviado al despacho.' : 'Mensaje guardado; el correo está pendiente de configuración.');
    } catch (err) {
      showFormMessage(form, formErrorText(err), 'error');
      toast('No se pudo enviar el mensaje.', 'error');
    } finally {
      setFormBusy(form, false);
    }
  });
}
