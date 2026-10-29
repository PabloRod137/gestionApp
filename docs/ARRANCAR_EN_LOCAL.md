# Arrancar Nortia en local (guía para una persona o un agente)

Objetivo: tener el panel del despacho funcionando en un ordenador, con los datos y los modelos Word del propio despacho, **sin tocar nada fuera de esta carpeta** y sin enviar ningún correo.

## 0. Reglas que no se rompen (leer antes de cambiar código)
Están en `CLAUDE.md`. Las que más importan al jugar con el programa:
- **Nunca se envía un correo** desde el programa; solo se preparan solicitudes de borrador.
- **Cero IA al generar documentos**: rellenar un modelo Word es sustituir marcas `${campo}`.
- La IA (Ollama, local) está **apagada por defecto**; solo propone y una persona confirma.
- **Los datos reales de clientes no se suben a ningún repositorio, chat ni servicio externo.** Se cargan en local desde los archivos del despacho (paso 4).
- Las claves van solo en `.env` (ignorado por git). Este paquete no trae ninguna.
- Si algo falla: arreglar con una prueba que falle primero (`php artisan test` debe quedar en verde).

## 1. Requisitos
| Programa | Versión | Para qué |
|---|---|---|
| PHP | 8.3 o superior, con extensiones `sqlite3`, `pdo_sqlite`, `mbstring`, `zip`, `xml`, `curl`, `gd`, `fileinfo`, `intl` | la aplicación |
| Composer | 2.x | dependencias PHP (si PHP es más nuevo que el declarado: añadir `--ignore-platform-req=php`) |
| Node.js | 20 o superior | compilar los estilos (`npm run build`) |
| Microsoft Word **o** LibreOffice | cualquiera reciente | crear el PDF de cada documento a la vez que el Word (ver abajo) |
| Ollama (opcional) | reciente | IA local; modelo `qwen3:4b-instruct-2507-q4_K_M` (~2,5 GB) |

## 2. Instalación (PowerShell, desde la carpeta del proyecto)
```powershell
composer install
npm ci
npm run build
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Force database\despacho.sqlite | Out-Null
```
Abre `.env` y pon la ruta **completa** de la base de datos:
```
DB_CONNECTION=sqlite
DB_DATABASE=C:\ruta\completa\al\proyecto\database\despacho.sqlite
```
`SESSION_DRIVER=file`, `CACHE_STORE=file` y `QUEUE_CONNECTION=sync` ya vienen así en `.env.example`. Después:
```powershell
php artisan migrate
php artisan db:seed --class=RolesSeeder
php artisan despacho:crear-usuario tu-correo@despacho.es --nombre "Tu nombre" --rol admin
```
(El último comando pide la contraseña por teclado; mínimo 12 caracteres. **No uses `db:seed` a secas**: crea usuarios de ejemplo con contraseñas conocidas y está bloqueado en producción.)

## 3. Arrancar
```powershell
php artisan serve --host=127.0.0.1 --port=8099
```
Entra en `http://127.0.0.1:8099/login`. La portada del personal es **Mi jornada**.

## 4. Cargar los datos del despacho (en este orden; los archivos los pone el despacho, no están en el paquete)
```powershell
php artisan despacho:importar  C:\ruta\DESPACHO_READMODEL.db            # clientes, expedientes, servicios, modelos, comunidades, propietarios
php artisan despacho:sincronizar C:\ruta\WORD_VIVO_SYNC                # expedientes nuevos, documentos y tarifa (palabras clave, notas, tipo de servicio)
php artisan despacho:plantillas  C:\ruta\carpeta_con_los_docx          # un .docx por código de modelo (WV_COM_CERT_DEUDA_V1_OBS.docx, ...)
php artisan despacho:recetas                                           # asigna modelos a servicios según config/recetas.php
```
- Todos se pueden repetir sin duplicar. Si hay avisos, se muestran en pantalla: léelos.
- `servicios_tarifa_word_vivo.csv` debe estar dentro de la carpeta que se pasa a `despacho:sincronizar`.
- Los CSV de Power Automate vienen en Windows-1252 y con el servicio como JSON: el programa lo normaliza.
- Rellena **Configuración** del panel (nombre, NIF, dirección, teléfono y correo del despacho): los documentos y el pie legal salen de ahí.

## 4 bis. PDF automático
Cada documento que se genera (desde una propuesta o desde el expediente) se crea **a la vez en Word y en PDF**; nadie tiene que exportarlo a mano.
- Con `PDF_MOTOR=auto` (por defecto) se usa **Microsoft Word si está instalado** (el PDF sale exactamente como al exportarlo desde Word) y, si no, **LibreOffice**. Si uno falla al convertir, se prueba con el otro.
- Word se maneja en segundo plano con la sesión de la persona que arranca el programa: vale para uso en el propio ordenador. Microsoft no soporta automatizar Office desde un servicio o servidor web; en un servidor se instala LibreOffice (`apt install libreoffice-writer` en Linux).
- Si LibreOffice no está en el PATH ni en la carpeta habitual, indica su ruta en `LIBREOFFICE_PATH`. `PDF_MOTOR=word` o `PDF_MOTOR=libreoffice` fuerzan uno.
- Si no se puede crear el PDF, el Word se genera igualmente y la pantalla avisa para subir el PDF a mano (se puede sustituir también por un PDF corregido).
- Windows: `winget install TheDocumentFoundation.LibreOffice`.

## 5. IA local (opcional)
```powershell
ollama pull qwen3:4b-instruct-2507-q4_K_M
```
En `.env`: `IA_ENABLED=true` (y `IA_CARPETAS_RAIZ=` con la carpeta local de clientes si se quiere que liste nombres de archivos del cliente). Reiniciar el servidor. Uso: caja «Preguntar a la IA» en Mi jornada y «Razonar con IA» en cada expediente. Medido con datos reales, **para elegir el servicio de un expediente acierta menos que las palabras clave** (6 de 30 frente a 8 de 30) y tarda ~30 s: está marcada como experimental.

## 6. Guion de prueba (10 minutos)
1. **Mi jornada**: decisiones pendientes, por preparar, calidad de los datos.
2. Abre un expediente **sin servicio** → «Actuaciones propuestas» → *Sugerir servicio* → *Asignar este*.
3. *Proponer actuaciones*: salen presupuesto, documento y factura según el servicio; quedan **pendientes de aprobar**.
4. Entra en una propuesta → aprobar → *Generar documento*: si faltan datos aparece un formulario para completarlos en el momento (opción de guardarlos en la ficha) → descargar el Word → subir el PDF revisado → aprobar el documento → preparar la comunicación (queda como solicitud de borrador; no se envía nada).
5. *Preparar la factura* desde las actuaciones: Facturación llega rellenada.
6. **Plantillas / Servicios / Comunidades**: revisar modelos, campos y asignaciones.

## 7. Comprobar que todo está sano
```powershell
php artisan test          # debe pasar todo
php artisan site:readiness  # lista lo que falta antes de publicar la web (es normal que falle en local)
```

## 8. Mapa rápido del código
| Qué | Dónde |
|---|---|
| Rutas del panel | `routes/panel.php` |
| Circuito propuesta → documento → comunicación | `app/Support/Workflow/ProposalWorkflow.php`, `app/Http/Controllers/Panel/ProposalController.php` |
| Rellenar Word | `app/Support/GeneradorDocumentos.php` |
| Propuestas automáticas / servicio por asunto | `app/Support/PlanActuaciones.php`, `ClasificadorServicio.php`, `Ia/ClasificaServicioIa.php`, `config/recetas.php` |
| Importación / sincronización | `app/Support/Import/` |
| Borradores de correo (M365) | `app/Support/Outlook/`, `docs/correo-microsoft-365.md` |
| IA local | `app/Support/Ia/`, `config/ia.php` |
| Vistas del expediente | `resources/views/panel/expedientes/partials/` |

## 9. Cosas conocidas (no son errores de instalación)
Ver `docs/AUDITORIA_2026-10.md`: integraciones heredadas que no se usan en el panel (WhatsApp, IMAP, clasificación con la API de Anthropic), datos de contacto de la web pendientes y lo que falta para publicar.
