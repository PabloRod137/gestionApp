# Nortia

**Nortia** es un programa de gestión para gestorías y despachos profesionales (asesoría legal, fiscal, laboral, administración de fincas e inmobiliaria). Centraliza todo lo que una gestoría necesita para llevar a sus clientes: una web pública para captar y atender a quien contacta, un panel de trabajo para el equipo, y un área privada para que cada cliente consulte sus propios expedientes, documentos y facturas.

Está construido con **Laravel** (PHP), pensado para instalarse en un servidor propio, sin depender de ningún servicio en la nube para los datos de los clientes.

> Este repositorio es una **versión de demostración**, con nombres, direcciones, fotos y datos inventados, pensada para enseñar el producto. Antes de usarlo con clientes reales hay que configurar los datos propios del despacho — ver [Puesta en marcha](#puesta-en-marcha).

## Índice

- [Qué hace Nortia (con detalle técnico)](#qué-hace-nortia-con-detalle-técnico)
- [Tecnologías usadas](#tecnologías-usadas)
- [Puesta en marcha](#puesta-en-marcha)
- [Cómo se crean los usuarios](#cómo-se-crean-los-usuarios)
- [Variables de entorno](#variables-de-entorno)
- [Uso diario, paso a paso](#uso-diario-paso-a-paso)
- [Comandos disponibles](#comandos-disponibles-php-artisan-)
- [Tests](#tests)
- [Estructura del proyecto](#estructura-del-proyecto)

---

## Qué hace Nortia (con detalle técnico)

### 1. Web pública del despacho
Página lista para usar: inicio, servicios, blog, un pequeño portal de inmuebles en venta, formulario de contacto y reserva de citas online, sin tener que llamar por teléfono.

- Hecha con **Blade** (motor de plantillas de Laravel) y **Tailwind CSS**; sin JavaScript framework, con **Alpine.js** solo para interacciones puntuales (menús, pestañas).
- Tipografías (Inter y Source Serif 4) servidas desde el propio servidor, no desde Google Fonts, por privacidad y velocidad.
- SEO con el paquete `artesaos/seotools`: título, descripción, Open Graph y Twitter Card por página, además de una ficha `LegalService` en formato JSON-LD (lo que permite a Google mostrar datos del despacho directamente en el buscador). `robots.txt` y `sitemap.xml` se generan solos a partir del dominio real de la petición.
- El comando `php artisan site:readiness` revisa que no queden datos de relleno (teléfono, email, textos legales) antes de dar por buena una publicación.

### 2. Reserva de citas
El cliente elige fecha y hora disponibles y reserva sin registrarse. El sistema calcula la disponibilidad real según el horario configurado (`config/shop.php` → `business_windows`) y las citas ya existentes, y envía un email de confirmación.

### 3. Tienda con pagos online
Una pequeña tienda para vender servicios o documentos sueltos con pago inmediato por tarjeta, usando **Stripe Checkout** (`stripe/stripe-php`). El pago se confirma siempre mediante el *webhook* de Stripe (nunca "a ciegas" desde el navegador), y cada pedido queda con un identificador público único.

### 4. Chat público (sin inteligencia artificial)
Un asistente de chat en la web pública que reconoce palabras clave para orientar sobre servicios, precios y cómo reservar cita. No usa ningún modelo de lenguaje: son reglas fijas, así que responde siempre lo mismo y no puede "alucinar" datos.

### 5. Clientes y expedientes
Cada cliente tiene su ficha (datos fiscales y de contacto) y puede tener varios **expedientes** abiertos (por ejemplo, "Declaración de la Renta 2025" o "Alta de autónomo"). Cada expediente guarda sus documentos (nóminas, facturas, contratos, notificaciones de Hacienda o Seguridad Social, escrituras, DNI, justificantes...), su estado y qué falta por aportar. Se pueden importar y exportar clientes y expedientes en Excel (paquete `maatwebsite/excel`, que usa internamente PhpSpreadsheet).

### 6. El día a día del equipo ("Mi jornada")
Un panel de trabajo donde cada persona ve sus tareas pendientes: propuestas por aprobar, documentos por generar, comunicaciones listas para revisar. El circuito de trabajo (`App\Support\Workflow\ProposalWorkflow`) sigue siempre el mismo orden:

```
Propuesta → aprobada → documento generado (Word + PDF) → documento aprobado → comunicación preparada
```

Cada paso queda firmado con el nombre de quien lo hizo, se guardan las distintas versiones (para que nadie pise el trabajo de otro) y cada Word/PDF generado lleva su propia huella **SHA-256**, de forma que si los datos cambian después, el documento se marca como caducado.

### 7. Generación de documentos desde plantillas Word
Los documentos se generan rellenando plantillas `.docx` reales que use el despacho, sustituyendo marcas de tipo `${campo}` por los datos guardados (paquete `phpoffice/phpword`). Si falta algún dato, se pide en un formulario antes de generar. **No interviene ninguna IA en este paso**: es sustitución literal de texto.

### 8. PDF automático de cada documento
Cada documento se crea a la vez en Word y en PDF, sin que nadie tenga que exportarlo a mano:
- **Motor `auto`** (por defecto): usa **Microsoft Word** por automatización COM si está instalado en el servidor (el PDF sale igual que si se exportara manualmente desde Word), y si no, recurre a **LibreOffice** (`soffice --headless`) como alternativa.
- Si uno de los dos falla, se prueba con el otro; si ninguno funciona, se conserva el Word y la pantalla avisa para subir el PDF a mano.

### 9. Propuesta de actuaciones y clasificación del servicio
A partir del servicio asignado a un expediente, el sistema propone por **reglas fijas** (sin IA) el presupuesto, el documento a generar y la factura correspondiente; todo queda pendiente de que una persona lo apruebe. Si el expediente no tiene servicio, se sugiere uno según palabras clave del asunto (`App\Support\ClasificadorServicio`) y, si se activa, con ayuda de la IA local eligiendo solo entre una lista cerrada de servicios (nunca inventa uno nuevo). Las recetas de qué documento corresponde a cada servicio están en `config/recetas.php`.

### 10. Facturación conforme a Veri\*Factu
El módulo de facturación sigue el sistema **Veri\*Factu** de la Agencia Tributaria española:
- Una factura pasa de **borrador** a **emitida** (con número correlativo, datos congelados y un registro de alta con huella encadenada criptográficamente) y, si hace falta, a **anulada**.
- **Una factura emitida no se puede editar ni borrar** — está forzado a nivel de base de datos, no solo de aplicación.
- Genera el **código QR tributario** de cada factura con el paquete `chillerlan/php-qrcode`.
- El envío a la AEAT (`App\Support\Verifactu\EnviadorAeat`) está apagado por defecto (`VERIFACTU_ACTIVO=false`) y, en pruebas, siempre simulado.
- La facturación automática por eventos (`config/facturacion.php`) prepara **borradores** de factura cuando se cierra un expediente facturable; nunca emite una factura por sí sola.

### 11. Comunicación con el cliente, sin enviar nada "a lo loco"
El programa **nunca envía un correo por su cuenta**. Cuando hay que avisar a un cliente, se prepara un **borrador** en el buzón de Microsoft 365 del despacho mediante la **API de Microsoft Graph** (`App\Support\Outlook\GraphDraftGateway`, autenticación OAuth2 por credenciales de aplicación), y es una persona quien lo revisa y lo envía desde Outlook.

### 12. Mensajería interna
Un chat por expediente entre el despacho y el cliente, con aviso por email cuando hay un mensaje nuevo. El texto se escapa siempre antes de mostrarse (protección frente a HTML/JavaScript malicioso en los mensajes).

### 13. Área privada del cliente
Cada cliente, con su usuario y contraseña, entra a consultar sus propios expedientes, documentos, facturas, citas y mensajes — sin tener que llamar o escribir para preguntar "cómo va mi trámite". Un asistente de chat privado (también basado en reglas, sin IA) responde solo con los datos del cliente que ha iniciado sesión.

### 14. Entrada automática de documentos
Los documentos que llegan por distintos canales se pueden archivar solos en el expediente que corresponda, sin que nadie tenga que subirlos a mano:
- **Correo electrónico (IMAP)**, con el paquete `webklex/laravel-imap`.
- **WhatsApp Business API**, mediante un webhook propio.
- **Microsoft Power Automate / OneDrive**: un endpoint (`POST /api/v1/power-automate/ingest`) recibe un archivo (por URL con token o en Base64), protegido con un token Bearer propio, y responde de inmediato mientras el procesado ocurre en segundo plano.

Todo esto se apoya en **colas de trabajo** de Laravel (`php artisan queue:work`), para no bloquear la web mientras se procesa un documento.

### 15. Clasificación de documentos
Hay dos vías, pensadas para no depender de ningún servicio externo con datos de clientes:
- **Clasificación en la nube (heredada, apagada por defecto)**: envía el nombre, tipo y un fragmento de texto del documento a la API de Anthropic Claude para sugerir una categoría (nómina, factura, contrato...). Solo se activa si se rellena `ANTHROPIC_API_KEY`; vacía por defecto justo para no usarla.
- **Conocimiento documental local**: inventario de la carpeta de cada cliente, lectura de texto en el propio equipo (PDF, Word, Excel, CSV, imágenes) con **OCR local** opcional (Tesseract + Poppler para PDF escaneados), y reglas (sin IA) que proponen NIF, email o teléfono como "hallazgos" con un nivel de confianza; una persona los acepta, corrige o rechaza uno a uno. Nada sale del equipo del despacho.

### 16. "Razonar con IA" — asistente local opcional
Una caja de pregunta libre sobre un expediente concreto, que usa un modelo de lenguaje **instalado en el propio ordenador** mediante **[Ollama](https://ollama.com)** (por defecto, el modelo `qwen3:4b-instruct-2507-q4_K_M`), consultado por HTTP a `http://127.0.0.1:11434`. Reglas estrictas:
- Apagado por defecto (`IA_ENABLED=false`).
- Solo ve los datos del cliente del expediente activo — nunca los de otro cliente.
- Responde siempre en JSON estructurado y su respuesta queda marcada como **"pendiente de validación"**: nunca escribe en la ficha ni envía nada por sí sola.
- No sale ningún dato a internet: todo ocurre contra el servidor de Ollama local.

### 17. Administración de comunidades de propietarios
Para despachos que también llevan fincas: gestión de propietarios, cuotas, y un **reporte mensual** con las cifras del mes, que una persona confirma antes de que se use para rellenar el modelo Word del informe.

### 18. Sincronización con sistemas externos (integración de ejemplo)
Un ejemplo de importación repetible desde una base de datos de lectura externa (no duplica datos si se repite, avisa de lo que no puede importar) y de "puente" de eventos hacia un sistema externo tipo SharePoint/Power Automate: el programa **nunca escribe directamente** en el sistema externo, solo entrega eventos que otra herramienta aplica.

### 19. Roles, permisos y auditoría
- Tres roles con `spatie/laravel-permission`: **administrador**, **trabajador** del despacho y **cliente**. Las rutas del panel exigen `worker` o `admin`; las del área de cliente exigen `client` — aplicado a nivel de middleware de Laravel, no solo ocultando botones.
- **Suplantación de usuario** (`lab404/laravel-impersonate`): un administrador puede "entrar como" un cliente para dar soporte, sin necesitar su contraseña, y queda registrado quién lo hizo.
- **Registro de auditoría** (`AuditLog`) de las acciones importantes del panel.

### 20. Identidad visual y accesibilidad
Un único sistema de diseño con Tailwind CSS para toda la aplicación (web pública, panel y portal de cliente, compilado por Vite), iconos en SVG propio (sin emojis), contraste de color comprobado. Hay tests dedicados (`IdentidadVisualTest`, `MenuMovilTest`) que vigilan que no vuelva a aparecer una hoja de estilos aparte y que cada clase propia (no de Tailwind) tenga su regla.

---

## Tecnologías usadas

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Base de datos | MySQL (local y producción); los tests corren aparte, en SQLite en memoria |
| Frontend | Blade, Tailwind CSS, Alpine.js, Vite |
| Roles y permisos | `spatie/laravel-permission` |
| Suplantación de usuario | `lab404/laravel-impersonate` |
| Documentos Word | `phpoffice/phpword` |
| PDF de facturas | `barryvdh/laravel-dompdf` |
| PDF de documentos generados | Microsoft Word (COM, Windows) o LibreOffice (`--headless`), con conversión automática |
| Lectura de texto de PDF | `smalot/pdfparser` |
| Código QR (Veri\*Factu) | `chillerlan/php-qrcode` |
| Excel (import/export) | `maatwebsite/excel` |
| Correo entrante (IMAP) | `webklex/laravel-imap` |
| Pagos | `stripe/stripe-php` (Stripe Checkout + webhooks) |
| SEO | `artesaos/seotools` |
| Borradores de correo | API de Microsoft Graph (OAuth2) |
| IA local (opcional) | [Ollama](https://ollama.com), por HTTP, en el propio equipo |
| Clasificación en la nube (heredada, apagada) | API de Anthropic Claude |
| OCR (opcional) | Tesseract OCR + Poppler (`pdftoppm`) |
| Tests | PHPUnit (`php artisan test`) |

No hace falta contratar ningún servicio en la nube para que la aplicación funcione: todas las integraciones externas (IA en la nube, WhatsApp, Power Automate, Stripe) son opcionales y están apagadas por defecto.

---

## Puesta en marcha

### Requisitos

| Programa | Versión | Para qué |
|---|---|---|
| PHP | 8.3 o superior, con extensiones `pdo_mysql`, `pdo_sqlite` (para los tests), `mbstring`, `zip`, `xml`, `curl`, `gd`, `fileinfo`, `intl` | la aplicación |
| Composer | 2.x | dependencias PHP |
| Node.js | 20 o superior | compilar los estilos (`npm run build`) |
| MySQL | 8.x (o MariaDB equivalente) | base de datos. Se puede levantar con Docker (ver abajo) o usar una instalación propia (Laragon, XAMPP, un servidor...) |
| Microsoft Word o LibreOffice | cualquiera reciente | generar el PDF de cada documento (opcional: sin ninguno de los dos, se queda solo el Word) |
| Ollama | reciente (opcional) | IA local; modelo `qwen3:4b-instruct-2507-q4_K_M` (~2,5 GB) |

### Instalación

```bash
composer install
npm ci

cp .env.example .env
php artisan key:generate
```

Necesitas un MySQL accesible. Dos formas:

- **Con Docker (Laravel Sail), sin instalar MySQL:** `./vendor/bin/sail up -d` levanta un contenedor MySQL ya configurado igual que `.env.example` (ver `compose.yaml`). En Windows, usa `vendor\bin\sail` o `php -d short_open_tag=0 vendor/bin/sail` según tu shell.
- **Con un MySQL que ya tengas instalado** (Laragon, XAMPP, un servidor propio...): crea una base de datos `nortia` y, si tu usuario/contraseña no son `root`/vacía, ajústalo en `.env` (`DB_USERNAME`, `DB_PASSWORD`).

```bash
php artisan migrate
php artisan db:seed          # carga datos de ejemplo (usuarios, clientes, servicios, citas, inmuebles...)

npm run build                # o "npm run dev" mientras se desarrolla
php artisan serve
```

Abre `http://127.0.0.1:8000` (o el puerto que indique `php artisan serve`). La portada del personal, tras entrar en `/login`, es **Mi jornada**; la web pública está en `/`.

> Si `composer install` falla por la versión de PHP instalada, añade `--ignore-platform-req=php`. Si falla por la extensión `zip`, actívala en el `php.ini` (`extension=zip`) o añade `--ignore-platform-req=ext-zip`.

### Procesar la cola de documentos

La entrada automática de documentos (Power Automate) y el conocimiento documental local se procesan en segundo plano, cada uno en su propia cola. Para que funcionen, hay que tener un trabajador corriendo:

```bash
php artisan queue:work --queue=documents,documentos,default
```

---

## Cómo se crean los usuarios

Hay **dos caminos**, según si se trata de probar la demo o de poner en marcha un despacho real.

### A) Usuarios de ejemplo (demo / desarrollo)

`php artisan db:seed` (o `php artisan migrate:fresh --seed` para partir de cero) ejecuta, entre otros, `UserSeeder` y crea:

| Usuario | Email | Contraseña | Rol |
|---|---|---|---|
| Administrador | `admin@admin.es` | `Administrador1*` | `admin` |
| Asesor fiscal (ejemplo) | `worker1@nortia.com` | `password` | `worker` |
| Abogada civil/compliance (ejemplo) | `worker2@nortia.com` | `password` | `worker` |
| Cliente de ejemplo 1 (empresa) | `cliente@cliente.es` | `Cliente1*` | `client` |
| Clientes de ejemplo 2 a 5 | `cliente2@nortia.com` a `cliente5@nortia.com` | `password` | `client` |

Esto solo funciona si `APP_ENV` no es `production`, o si se fuerza con `ALLOW_SAMPLE_DATA=1` — es una medida de seguridad para que nadie deje por error estos usuarios (con contraseñas conocidas y públicas en este repositorio) en un despacho real.

### B) Primer usuario real (producción)

Para un despacho real, **no se usa `db:seed`**. Se crea el primer administrador con:

```bash
php artisan despacho:crear-usuario correo@despacho.es --nombre "Nombre Apellido" --rol admin
```

El comando pide la contraseña por teclado (mínimo 12 caracteres) y no la deja en el historial de la terminal ni en los logs. A partir de ahí, el resto de usuarios (trabajadores y clientes) se crean desde el propio panel.

---

## Variables de entorno

Las más relevantes de `.env` (la lista completa, comentada, está en [`.env.example`](.env.example)):

| Variable | Para qué |
|---|---|
| `DB_*` | Conexión a la base de datos (SQLite por defecto). |
| `SITE_PHONE`, `SITE_EMAIL`, `SITE_ADDRESS`, `SITE_HOURS` | Datos de contacto que se muestran en la web pública. Vacíos por defecto: `php artisan site:readiness` avisa mientras falten. |
| `LEGAL_*`, `PRIVACY_*` | Datos legales y de protección de datos del despacho (textos del aviso legal y la política de privacidad). |
| `MAIL_*` | Envío de notificaciones (citas, mensajes, documentos recibidos). |
| `QUEUE_CONNECTION` | Cómo se procesan en segundo plano la entrada y clasificación de documentos (`database` o `sync` en local; Redis recomendado en producción). |
| `IMAP_*` | Buzón de correo para la entrada automática de documentos (opcional). |
| `WHATSAPP_*` | Integración de WhatsApp Business (opcional, apagada por defecto). |
| `POWER_AUTOMATE_API_TOKEN` | Token del webhook de ingesta desde Microsoft Power Automate (opcional). |
| `MS_DRAFTS_ENABLED`, `MS_TENANT_ID`, `MS_CLIENT_ID`, `MS_CLIENT_SECRET`, `MS_MAILBOX` | Conexión a Microsoft 365 para crear borradores de correo (opcional). |
| `ANTHROPIC_API_KEY` | Clasificación de documentos en la nube — **dejar vacía**; es una integración heredada, no aprobada para datos de clientes reales. |
| `IA_ENABLED`, `IA_OLLAMA_URL`, `IA_OLLAMA_MODEL`, `IA_CARPETAS_RAIZ` | IA local opcional (Ollama). No requiere ninguna clave de un servicio externo. |
| `DOCUMENTAL_ACTIVO`, `DOCUMENTAL_RAIZ`, `TESSERACT_PATH`, `PDFTOPPM_PATH` | Conocimiento documental local y OCR (opcional). |
| `VERIFACTU_ACTIVO`, `VERIFACTU_ENTORNO`, `VERIFACTU_CERT_*` | Sistema de facturación Veri\*Factu y envío a la AEAT. |
| `PAYMENTS_ENABLED`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` | Tienda con pagos online (opcional, apagada por defecto). |
| `PDF_MOTOR`, `LIBREOFFICE_PATH` | Motor de conversión a PDF (`auto`, `word` o `libreoffice`). |
| `ALLOW_SAMPLE_DATA` | Permite usar `db:seed` (datos de ejemplo) fuera de `local`. |

---

## Uso diario, paso a paso

Un recorrido rápido (unos 10 minutos) para ver el circuito completo, tras entrar como administrador:

1. **Mi jornada**: panel con las decisiones pendientes, lo que hay que preparar y la calidad de los datos.
2. Abre un expediente **sin servicio asignado** → "Actuaciones propuestas" → *Sugerir servicio* → *Asignar este*.
3. *Proponer actuaciones*: aparecen presupuesto, documento y factura según el servicio asignado, pendientes de aprobar.
4. Entra en la propuesta → **aprobar** → *Generar documento*: si faltan datos, aparece un formulario para completarlos al momento (con opción de guardarlos en la ficha) → se descarga el Word (con su PDF ya generado) → se aprueba el documento → se prepara la comunicación (queda como solicitud de borrador de correo; no se envía nada).
5. *Preparar la factura* desde las actuaciones: la pantalla de Facturación llega ya rellenada.
6. Revisa **Plantillas**, **Servicios** y **Comunidades** para ver cómo se configuran los modelos de documento, los servicios y las comunidades de propietarios.
7. En el área de cliente (`/cliente`, con un usuario de rol `client`): el cliente ve sus expedientes, documentos, facturas, citas y mensajes.

---

## Comandos disponibles (`php artisan …`)

| Comando | Qué hace |
|---|---|
| `despacho:crear-usuario` | Crea el primer usuario real (o más adelante, cualquiera) con contraseña segura pedida por teclado. |
| `despacho:importar <fichero>` | Importa clientes, expedientes, servicios, modelos y comunidades desde una base de lectura externa (ejemplo de integración). |
| `despacho:sincronizar <carpeta>` | Sincroniza expedientes, documentos y tarifa desde una carpeta exportada (ejemplo de integración). |
| `despacho:plantillas <carpeta>` | Carga los ficheros `.docx` de las plantillas de documento. |
| `despacho:recetas` | Asigna plantillas a servicios según `config/recetas.php`. |
| `despacho:borradores` | Procesa las solicitudes de borrador de correo pendientes (pensado para ejecutarse cada 5 minutos). |
| `documentos:inventariar` | Inventaría la carpeta documental local de cada cliente. |
| `documentos:barrido` | Barrido periódico del conocimiento documental local. |
| `verifactu:estado` | Consulta el estado de envío a la AEAT. |
| `verifactu:enviar` | Envía los registros de facturación pendientes a la AEAT (apagado salvo `VERIFACTU_ACTIVO=true`). |
| `facturas:fin-de-mes` | Prepara los borradores de factura de las cuotas mensuales de clientes/comunidades. |
| `site:readiness` | Lista lo que falta antes de poder publicar la web pública (datos de contacto, legales, etc.). |

---

## Tests

```bash
php artisan test
```

Toda la suite corre siempre en memoria (`tests/TestCase.php` aborta si la base de datos no es `:memory:`), así que nunca toca datos reales. Incluye, entre otras, pruebas de reserva de citas, facturación Veri\*Factu, clasificación e ingesta de documentos, identidad visual, SEO y seguridad de cabeceras HTTP.

---

## Estructura del proyecto

```
app/
  Http/Controllers/Panel/   Controladores del panel interno (equipo)
  Http/Controllers/Client/  Controladores del área de cliente
  Http/Controllers/Api/     Endpoints públicos y de integraciones (Power Automate, WhatsApp, Stripe...)
  Models/                   Modelos Eloquent (Client, Expedient, Invoice, Property, Community...)
  Support/                  Lógica de negocio: Workflow, Verifactu, Outlook, Ia, Sync, Import...
  Chat/                     Asistentes de chat (público y de cliente), sin IA
  Services/                 Integraciones externas (clasificación en la nube)
database/
  migrations/                Esquema de la base de datos
  seeders/                   Datos de ejemplo
resources/
  views/site/                 Web pública
  views/panel/                 Panel del equipo
  views/client/                 Área de cliente
routes/
  web.php, panel.php, cliente.php, api.php   Rutas de cada área
config/                    Configuración (ia.php, verifactu.php, shop.php, documental.php, recetas.php...)
docs/                      Guías de instalación, diseño y despliegue
```

## Licencia

Proyecto privado — todos los derechos reservados.
