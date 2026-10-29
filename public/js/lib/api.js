// Laravel deja el token CSRF de la sesión en una etiqueta <meta> de cada página
function csrfToken() {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!token) throw new Error('csrf');
  return token;
}

// POST con token CSRF. Lanza un Error con `payload` y `status` cuando la API responde con fallo.
export async function postJson(url, payload) {
  const token = csrfToken();
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
    body: JSON.stringify(payload)
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok !== true) {
    const error = new Error(data.message || data.error || 'request_failed');
    error.payload = data;
    error.status = response.status;
    throw error;
  }
  return data;
}

export async function getJson(url) {
  const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok !== true) {
    const error = new Error(data.error || 'request_failed');
    error.payload = data;
    error.status = response.status;
    throw error;
  }
  return data;
}
