# Enseñar Nortia desde el móvil

Todo con **datos inventados**, en una base SQLite temporal propia del script. Nunca toca la base real del despacho (MySQL).

## 1. Visita guiada (la forma más cómoda)
Una página pensada para el móvil con pantallas reales: la web, el área del cliente, el asistente, el panel y el puente con SharePoint, más la lista de lo que falta por cerrar. Se publicó como página privada de Claude; solo la abre quien tenga el enlace compartido desde el menú «Compartir» de esa página. No necesita ordenador encendido.

## 2. Demostración en vivo (para enseñarla en persona)
En el ordenador, desde la carpeta del proyecto, en PowerShell:

```powershell
.\scripts\demo-movil.ps1
```

- Crea una base temporal con los datos de ejemplo, arranca el servidor abierto a la red local y escribe la dirección para el móvil (por ejemplo `http://192.168.1.20:8095`).
- El móvil tiene que estar en **la misma wifi** que el ordenador. La primera vez Windows pregunta si permite el acceso a la red: «Redes privadas».
- Cuentas de ejemplo (correo y contraseña) en `database/seeders/UserSeeder.php`: `cliente@cliente.es` para ver el área del cliente y `admin@admin.es` para el panel.
- Al pulsar Ctrl+C se para y se borra la base temporal.

Úsalo solo en una red de confianza: las cuentas de ejemplo tienen contraseñas conocidas.
