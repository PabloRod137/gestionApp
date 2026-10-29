# Nortia

Aplicación de gestión documental y de expedientes para gestorías y despachos profesionales (legal, fiscal, laboral, administración de fincas), construida con **Laravel 13**. Incluye la web pública, el panel interno del equipo ("Mi jornada", expedientes, propuestas, documentos desde modelos Word, comunicaciones como borradores de Outlook, facturación), un área de cliente y una IA local opcional (Ollama).

> **Para arrancarlo en local:** `docs/ARRANCAR_EN_LOCAL.md`. **Reglas del proyecto:** `CLAUDE.md`.
> Este README describe además integraciones heredadas (IMAP, WhatsApp, Power Automate, clasificación con la API de Anthropic) que **no forman parte del flujo actual del panel**; ver el aviso de la sección siguiente.

## Descripción

Nortia centraliza la relación de una gestoría con sus clientes:

- **Clientes y expedientes** (`Client`, `Expedient`, `ExpedientDocument`): cada cliente tiene expedientes con documentos adjuntos (nóminas, facturas, contratos, notificaciones de Hacienda/Seguridad Social, escrituras, DNI/NIE, justificantes de pago, correspondencia, etc.).
- **Citas** (`Appointment`): reserva y gestión de citas, con notificaciones por email (`resources/views/emails/appointments/notification.blade.php`).
- **Facturación** (`Invoice`, `InvoiceLine`): generación de facturas y PDF (`barryvdh/laravel-dompdf`, `resources/views/pdf/invoice.blade.php`).
- **Mensajería interna** (`Message`): chat por expediente entre gestoría y cliente, con notificación por email.
- **Blog y páginas públicas** (`BlogPost`, `BlogCategory`, `Property`, `Service`, `ContactRequest`): sitio público de la gestoría (inicio, servicios, propiedades, blog, contacto, reserva de citas).
- **Auditoría** (`AuditLog`) e **impersonación de usuarios** (`lab404/laravel-impersonate`) para soporte desde el panel.
- **Roles y permisos** vía `spatie/laravel-permission`.

### Ingesta y clasificación automática de documentos

- **Ingesta por email (IMAP)** y **WhatsApp** (`WhatsAppWebhookController`, `webklex/laravel-imap`): los documentos que llegan por estos canales se archivan automáticamente en el expediente del cliente (`DocumentIngestionService`).
- **Ingesta desde Power Automate / Microsoft 365** (`app/Http/Controllers/Api/PowerAutomateController.php`): endpoint `POST /api/v1/power-automate/ingest` que recibe un archivo de OneDrive (por URL con SAS token o en Base64), lo guarda temporalmente y despacha `ProcessPowerAutomateDocumentJob` a la cola `documents` (respuesta `202 Accepted` inmediata, sin bloquear el flujo de Power Automate). El estado del job puede consultarse por polling en `GET /api/v1/power-automate/job-status/{jobId}`. Estas rutas están protegidas por el middleware `auth.powerautomate` (`AuthenticatePowerAutomate`), que exige un Bearer token igual a `POWER_AUTOMATE_API_TOKEN` (en `local`, si no hay token configurado, deja pasar la petición para facilitar pruebas; en producción sin token configurado, bloquea todo con `503`).
- **Clasificación con IA en la nube — heredada, NO aprobada para datos de clientes** (`app/Services/DocumentClassificationService.php`). Solo actúa si se define `ANTHROPIC_API_KEY`; **dejarla vacía** hasta que el despacho firme un acuerdo de tratamiento de datos o se sustituya por el modelo local. Con la clave definida, cada documento subido (desde el panel o por ingesta automática) se envía a la API de Anthropic Claude (`claude-haiku-4-5`) junto con su nombre, tipo MIME y, si es un PDF, un fragmento de su texto extraído con `smalot/pdfparser`. Claude devuelve una `categoria` (nómina, factura, contrato, notificación de Hacienda/Seguridad Social, declaración fiscal, escritura, DNI/NIE, justificante de pago, correspondencia u otro) y un nivel de `confianza` (alta/media/baja), que se guardan en el documento. Desde el panel de expediente se puede corregir manualmente la categoría (`PATCH /panel/documentos/{document}/categoria`), lo que marca la confianza como "alta".

## Requisitos

- PHP 8.3+
- Composer
- Node.js / npm
- Base de datos (SQLite por defecto en `.env.example`; MySQL/PostgreSQL soportados)
- Cuenta IMAP (para ingesta por email) y/o WhatsApp Business API (opcional)
- (Opcional) Ollama con el modelo `qwen3:4b-instruct-2507-q4_K_M` para la IA local. **No hace falta ninguna clave de Anthropic.**

## Instalación

Resumen (pasos completos, datos del despacho y primer usuario en `docs/ARRANCAR_EN_LOCAL.md`):

```bash
composer install
npm ci

cp .env.example .env
php artisan key:generate
```

Variables de entorno relevantes (`.env`):

- `DB_*` — conexión a base de datos.
- `QUEUE_CONNECTION` — los Jobs de ingesta/clasificación se despachan a cola (recomendado `database` o Redis en producción; el job de Power Automate usa la cola `documents`).
- `MAIL_*` — envío de notificaciones (citas, mensajes, documentos ingeridos).
- `IMAP_HOST`, `IMAP_PORT`, `IMAP_USERNAME`, `IMAP_PASSWORD`, etc. — buzón de correo desde el que se ingieren documentos automáticamente.
- `WHATSAPP_API_TOKEN`, `WHATSAPP_WEBHOOK_VERIFY_TOKEN` — integración de WhatsApp.
- `ANTHROPIC_API_KEY` — **dejar vacía** (ver aviso arriba). Si está vacía, la clasificación en la nube se omite (se registra un warning en el log).
- `POWER_AUTOMATE_API_TOKEN` y `POWER_AUTOMATE_CALLBACK_URL_TIMEOUT` — token Bearer y timeout para el webhook de Power Automate.

```bash
php artisan migrate

npm run build   # o `npm run dev` en desarrollo
php artisan serve
```

Para procesar la cola de ingesta/clasificación de documentos:

```bash
php artisan queue:work --queue=documents,default
```

## Uso

- **Panel interno** (`/panel`): gestión de clientes, expedientes, documentos, citas, facturas, mensajes, blog y auditoría.
- **Área de cliente** (`/cliente` o equivalente en `routes/`): el cliente consulta sus expedientes, documentos, facturas, citas y mensajes.
- **Ingesta automática**: configura el buzón IMAP y/o el flujo de Power Automate para que los documentos entrantes se archiven y clasifiquen solos; revisa y corrige la categoría desde la vista de expediente si la confianza es baja/media.

## Tests

```bash
php artisan test
```

Incluye pruebas de reserva de citas (`tests/Feature/AppointmentBookingTest.php`) y de clasificación de documentos (`tests/Feature/DocumentClassificationTest.php`).

## Licencia

Proyecto privado — todos los derechos reservados.
