# Conectar el correo del despacho (Microsoft 365)

El panel prepara las comunicaciones y deja una solicitud con el PDF aprobado. Esta conexión convierte esa solicitud en un **borrador** en el buzón del despacho. **El programa nunca envía**: una persona abre el borrador en Outlook, lo revisa y lo envía.

Lo hace una persona con rol de administrador de Microsoft 365 (unos 15 minutos). Los valores que se obtienen **solo se escriben en el `.env` del servidor**; no se mandan por chat ni por correo.

## 1. Registrar la aplicación

1. Entrar en <https://entra.microsoft.com> → **Aplicaciones** → **Registros de aplicaciones** → **Nuevo registro**.
2. Nombre: `Panel del despacho - borradores`. Tipo de cuenta: *Solo esta organización*. Sin URI de redirección.
3. Anotar el **Id. de directorio (inquilino)** y el **Id. de aplicación (cliente)**.

## 2. Dar el permiso mínimo

1. **Permisos de API** → **Agregar un permiso** → **Microsoft Graph** → **Permisos de la aplicación** → `Mail.ReadWrite`.
2. **Conceder consentimiento de administrador**.
3. **Limitar el permiso a un único buzón.** Por defecto este permiso alcanza a todos los buzones de la organización. Se limita con una política de acceso de aplicación en Exchange Online (o con RBAC para aplicaciones), de modo que la aplicación solo vea el buzón del despacho:

```powershell
# Grupo de seguridad con correo que contiene SOLO el buzón del despacho
New-ApplicationAccessPolicy -AppId <Id. de aplicación> -PolicyScopeGroupId despacho-borradores@sudominio.com `
    -AccessRight RestrictAccess -Description "Panel del despacho: solo el buzón del despacho"
```

## 3. Crear el secreto

**Certificados y secretos** → **Nuevo secreto de cliente** → copiar el **valor** (solo se ve una vez). Anotar la fecha de caducidad: hay que renovarlo antes.

## 4. Configurar el servidor

En el `.env` del servidor:

```
MS_DRAFTS_ENABLED=true
MS_TENANT_ID=<Id. de directorio>
MS_CLIENT_ID=<Id. de aplicación>
MS_CLIENT_SECRET=<valor del secreto>
MS_MAILBOX=<correo del buzón, por ejemplo info@sudominio.com>
```

Después, `php artisan config:cache`.

## 5. Probar

1. En el panel, preparar una comunicación de prueba (destinatario propio) y pulsar **Crear borrador ahora** en *Comunicaciones*.
2. Comprobar que aparece el borrador en la carpeta Borradores del buzón, con el PDF adjunto.
3. Comprobar que **no** se ha enviado nada.

La tarea programada `despacho:borradores` repite el proceso cada cinco minutos para lo pendiente. Requiere la tarea de cron habitual de Laravel: `* * * * * php artisan schedule:run`.

## Qué garantiza el programa

- Solo crea borradores; no existe ninguna llamada de envío.
- Antes de crear, busca si ya hay un borrador de esa solicitud, así que reintentar no duplica.
- Después de crear, relee el borrador y comprueba que es un borrador, que el destinatario y el asunto coinciden y que el PDF adjunto es exactamente el aprobado. Si algo no coincide, queda en *Error* y no se da por creado.
- Si el PDF cambia después de aprobarlo, no se envía a Microsoft.
- Adjuntos de hasta 3 MB (suficiente para cartas y certificados).
