# Nortia

**Nortia** es un programa de gestión para gestorías y despachos profesionales (asesoría legal, fiscal, laboral, administración de fincas e inmobiliaria). Centraliza todo lo que una gestoría necesita para llevar a sus clientes: una web pública para captar y atender a quien te contacta, un panel de trabajo para el equipo, y un área privada para que cada cliente consulte sus propios expedientes, documentos y facturas.

Está construido con **Laravel** (PHP) y pensado para poder instalarse en un servidor propio, sin depender de terceros para los datos de los clientes.

> Este repositorio es una **versión de demostración**, con datos y nombres inventados, pensada para enseñar el producto. Antes de usarlo con clientes reales hay que configurar los datos propios del despacho (ver [Puesta en marcha](#puesta-en-marcha-para-desarrolladores)).

## Qué hace Nortia

### Web pública del despacho
Una página web lista para usar: inicio, servicios ofrecidos, blog, un pequeño portal de inmuebles en venta, formulario de contacto y reserva de citas online. El cliente puede reservar una cita sin llamar por teléfono, y recibe la confirmación por email.

### Gestión de clientes y expedientes
Cada cliente tiene su ficha (datos fiscales, de contacto...) y puede tener varios **expedientes** abiertos (por ejemplo, "Declaración de la Renta 2025" o "Alta de autónomo"). Cada expediente guarda sus documentos (nóminas, facturas, contratos, notificaciones de Hacienda o Seguridad Social, escrituras, DNI, justificantes...), su estado, y qué falta por aportar.

### El día a día del equipo
Un panel de trabajo donde cada miembro del equipo ve sus tareas pendientes ("Mi jornada"): propuestas por aprobar, documentos por generar, comunicaciones listas para revisar. El circuito de trabajo es siempre el mismo y queda registrado: se propone algo, se aprueba, se genera el documento (en Word y PDF a la vez, a partir de plantillas), se revisa y, por último, se deja preparado el aviso al cliente. Todo queda firmado con el nombre de quien hizo cada paso, y se guardan las versiones para que nadie pise el trabajo de otro.

### Comunicación con el cliente, sin enviar nada "a lo loco"
El programa nunca envía un correo por su cuenta: prepara un **borrador** en el correo de Microsoft 365 del despacho, y es una persona quien lo revisa y lo envía desde Outlook. Así no hay sorpresas.

### Facturación
Generación de facturas y su PDF, siguiendo el sistema **Veri\*Factu** de la Agencia Tributaria española (código seguro de verificación, registro encadenado a prueba de manipulación, QR). Una factura emitida no se puede editar ni borrar, como exige la ley.

### Tienda y cobros online
Una pequeña tienda para vender servicios o documentos sueltos con pago por tarjeta (Stripe), útil para trámites puntuales que no necesitan una cita previa.

### Mensajería interna
Un chat por expediente entre el despacho y el cliente, con aviso por email cuando hay un mensaje nuevo.

### Área privada del cliente
Cada cliente, con su usuario y contraseña, entra a consultar sus propios expedientes, documentos, facturas, citas y mensajes — sin tener que llamar o escribir para preguntar "cómo va mi trámite".

### Administración de comunidades de propietarios
Para despachos que también llevan comunidades: gestión de propietarios, cuotas y un reporte mensual con las cifras del mes.

### Entrada automática de documentos
Los documentos que llegan por correo electrónico o WhatsApp se pueden archivar solos en el expediente del cliente que corresponda, sin que nadie tenga que subirlos a mano. También hay una vía de entrada para documentos que llegan desde Microsoft 365 / Power Automate (por ejemplo, desde una carpeta de OneDrive sincronizada). Todo esto se apoya en una cola de trabajo en segundo plano, para no bloquear nada mientras se procesa.

### Un vistazo inteligente a cada documento (sin enviarlo a ningún sitio)
El programa puede proponer, por reglas, a qué cliente pertenece un documento, su NIF, correo o teléfono — y una persona siempre confirma o corrige antes de que se guarde. Si se activa una IA (modelo local, instalado en el propio ordenador, no en la nube), puede ayudar a leer documentos más complejos, pero nunca manda los datos de un cliente fuera del despacho.

### Permisos, roles y auditoría
Hay distintos tipos de usuario (administrador, trabajador del despacho, cliente), cada uno ve solo lo que le corresponde. Queda un registro de auditoría de las acciones importantes, y el administrador puede "entrar como" otro usuario para dar soporte sin necesitar su contraseña.

## Tecnología

Laravel 13 (PHP), con Blade para las vistas, una base de datos SQLite o MySQL, y Tailwind CSS para el diseño. No hace falta contratar ningún servicio en la nube para que funcione: la IA de clasificación de documentos, si se activa, es local.

---

## Puesta en marcha (para desarrolladores)

### Requisitos

- PHP 8.3 o superior, con Composer
- Node.js y npm
- Una base de datos: SQLite (la opción por defecto, no requiere instalar nada más) o MySQL
- Opcional: una cuenta de correo IMAP (para recibir documentos por email), WhatsApp Business API, y/o [Ollama](https://ollama.com) con un modelo local si se quiere activar la IA

### Instalación

```bash
composer install
npm ci

cp .env.example .env
php artisan key:generate

php artisan migrate
php artisan db:seed   # carga datos de ejemplo (usuarios, clientes, servicios...)

npm run build          # o "npm run dev" mientras se desarrolla
php artisan serve
```

La guía paso a paso, con más detalle, está en [`docs/ARRANCAR_EN_LOCAL.md`](docs/ARRANCAR_EN_LOCAL.md).

Usuarios de ejemplo tras el `db:seed` (ver [`database/seeders/UserSeeder.php`](database/seeders/UserSeeder.php)):

| Usuario | Email | Contraseña |
|---|---|---|
| Administrador | `admin@admin.es` | `Administrador1*` |
| Cliente de ejemplo | `cliente@cliente.es` | `Cliente1*` |

### Variables de entorno más relevantes (`.env`)

- `DB_*` — conexión a la base de datos.
- `SITE_*` — teléfono, email, dirección y horario que se muestran en la web pública.
- `MAIL_*` — envío de notificaciones (citas, mensajes, documentos recibidos).
- `QUEUE_CONNECTION` — cómo se procesan en segundo plano la entrada y clasificación de documentos.
- `IMAP_*` / `WHATSAPP_*` — buzón de correo y WhatsApp para la entrada automática de documentos (opcional).
- `POWER_AUTOMATE_API_TOKEN` — token para la integración con Microsoft Power Automate (opcional).
- `IA_ENABLED`, `IA_OLLAMA_URL`, `IA_OLLAMA_MODEL` — IA local opcional (Ollama). No requiere ninguna clave de un servicio externo.
- `VERIFACTU_*` — datos del sistema de facturación Veri\*Factu.
- `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` — si se activa la tienda con pagos online.

Para procesar en segundo plano la entrada y clasificación de documentos:

```bash
php artisan queue:work --queue=documents,default
```

### Tests

```bash
php artisan test
```

### Licencia

Proyecto privado — todos los derechos reservados.
