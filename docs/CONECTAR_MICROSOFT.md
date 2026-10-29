# Conectar Nortia al nuevo entorno de Microsoft: qué hay que hacer y en qué orden

Hay tres conexiones **independientes**. Se pueden hacer una a una, y cada una se puede probar sin las demás. El programa **nunca envía correo ni escribe en SharePoint**: prepara borradores y entrega eventos; otra herramienta (Outlook, Power Automate) ejecuta.

Regla de oro: **ningún secreto por chat ni por correo.** Todo valor sensible se escribe solo en el `.env` del equipo donde corre el programa.

## 0. Antes de empezar (lo que necesitamos saber)
- ¿Quién es **administrador global / de Exchange** del nuevo entorno? Los pasos 1 y 2 los hace esa persona.
- ¿Cuál es el **buzón del despacho** (por ejemplo `info@…`) y tiene licencia?
- ¿Dónde estarán los documentos? ¿En **SharePoint** (biblioteca «Documentos compartidos» con `01_CLIENTES`) y sincronizada con **OneDrive** en el equipo donde correrá el programa?
- ¿Se han **migrado** las listas de SharePoint (*Registro_Actividad_Expediente*, *Expedientes*…) y los flujos de **Power Automate** al nuevo entorno? Los flujos no se mueven solos: hay que recrearlos o importarlos.
- ¿En qué equipo correrá el programa? (Para Power Automate en la nube, un equipo de la oficina **no** es accesible desde fuera: de momento se usa la carpeta sincronizada.)

## 1. Correo: borradores en Outlook (15 minutos, administrador)
Guía completa: `docs/correo-microsoft-365.md`. Resumen:
1. **Entra** (<https://entra.microsoft.com>) → Registros de aplicaciones → Nuevo registro «Panel del despacho - borradores» (solo esta organización). Anotar *Id. de directorio (inquilino)* e *Id. de aplicación (cliente)*.
2. Permisos de API → Microsoft Graph → **Permisos de la aplicación** → `Mail.ReadWrite` → *Conceder consentimiento de administrador*.
3. **Limitar la aplicación a un solo buzón** con una política de acceso de aplicación en Exchange Online (comando en la guía). Sin esto el permiso alcanza a todos los buzones.
4. Certificados y secretos → nuevo **secreto de cliente**; copiar el valor (solo se ve una vez) y apuntar su caducidad.
5. En el `.env`: `MS_DRAFTS_ENABLED=true`, `MS_TENANT_ID`, `MS_CLIENT_ID`, `MS_CLIENT_SECRET`, `MS_MAILBOX`. Después `php artisan config:cache`.
6. Prueba: preparar una comunicación con un destinatario propio → *Crear borrador ahora* → comprobar que aparece en Borradores con su PDF y que **no se envía**.

## 2. SharePoint ↔ panel: estados y actividad (Power Automate)
Guía completa: `docs/puente-sharepoint.md`. Para empezar, la **vía A (carpeta sincronizada)**, que no necesita que el programa sea accesible desde Internet:
1. Crear en SharePoint la subcarpeta de intercambio (por ejemplo `00_CONTROL\imports-nortia`) y **sincronizarla con OneDrive** en el equipo del programa.
2. `.env`: `SYNC_EXPORT_DIR=` la ruta local de esa carpeta, `SYNC_EXPORT_ENABLED=true`.
3. Crear el **flujo de Power Automate** «Cuando se crea un archivo» en esa carpeta que lea el JSON y cree/actualice elementos en *Registro_Actividad_Expediente* y el estado en *Expedientes* (pasos en la guía). Estados admitidos: No iniciado, En curso, Completado.
4. Comprobar en *Sincronización* que los eventos pasan de pendiente → entregado → confirmado.
5. Más adelante, **vía B (API)** cuando el programa esté alojado en un servidor accesible: `POWER_AUTOMATE_API_TOKEN` (largo y aleatorio) y el flujo con HTTP.

## 3. Documentos de los clientes: lectura local (indexación)
Guía: `docs/INDEXACION_DOCUMENTAL.md`.
1. La biblioteca de documentos (la que contiene `01_CLIENTES`) **sincronizada con OneDrive en el equipo** donde corre el programa. Mejor «Mantener siempre en este dispositivo» para que los archivos estén realmente descargados.
2. `.env`: `DOCUMENTAL_RAIZ=` la ruta local de esa biblioteca (y `IA_CARPETAS_RAIZ=` la misma, para la IA local).
3. Instalar **Tesseract** (español) y **Poppler** para los escaneados (opcional al principio).
4. Comprobar que las fichas de los clientes tienen su **ruta de SharePoint** (se importa del modelo de lectura).
5. `DOCUMENTAL_ACTIVO=true` y, en otra ventana, `.\scripts\trabajador-documental.ps1`.

## 4. Más adelante (cuando haya cuenta y datos)
- **Planner:** hacen falta los identificadores internos de las listas y los flujos (ni el despacho los tiene todavía).
- **Aplicación Entra para leer SharePoint por API** (alternativa a la carpeta sincronizada): no está construida; la carpeta sincronizada cubre lo necesario.
- **Doble factor** para el personal y **cuentas de cliente** invitadas, si se quiere acceso externo.

## Orden recomendado
1. Respuestas del apartado 0.
2. **Correo (paso 1)**: es lo más autocontenido y se prueba en minutos.
3. **Carpeta de documentos sincronizada (paso 3, solo lectura)**: no cambia nada en Microsoft; permite ver las propuestas.
4. **Puente SharePoint (paso 2)**: requiere que las listas y el flujo existan en el nuevo entorno.
