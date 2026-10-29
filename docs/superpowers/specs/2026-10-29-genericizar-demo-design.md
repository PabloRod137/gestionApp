# Genericizar Nortia para demo de producto

**Fecha:** 2026-10-29
**Estado:** Aprobado por el usuario (conversación), implementación en curso.

## Contexto

Este repositorio es la aplicación real del despacho de Diego Roldán Mesa
(Santander). El usuario quiere una copia local, genérica, para mostrar y
vender como producto, sin datos ni nombres reales de Alberto o su despacho.
No se debe tocar ni empujar nada al repositorio real de GitHub
(`PabloRod137/nortia`).

## Decisiones ya tomadas con el usuario

- Destino: solo local. Se quita el remoto `origin` para evitar un `push`
  accidental al repo real.
- **Actualizado:** el usuario pidió que "Nortia" tampoco aparezca en
  ningún sitio (ni vistas, ni nombres, ni código). Se sustituye en todo el
  repo por la marca ficticia **Nortia** (218 apariciones en ~101 archivos:
  textos, rutas de API `/api/nortia/*` → `/api/nortia/*`, nombre de
  fichero `og-nortia.png` → `og-nortia.png`, dominio de email, clave
  de configuración de Verifactu, tests que comprueban ese texto/ruta).
- Ciudad (Santander/Cantabria): se mantiene. Solo cambian el nombre del
  director, el despacho, la dirección exacta, el teléfono, el email y las
  coordenadas del mapa.
- Integraciones heredadas específicas del despacho real (import
  `DESPACHO_READMODEL.db`, puente SharePoint/Power Automate): se mantienen
  como funcionalidad de ejemplo, pero sin menciones a "Alberto"/"Berdejo".
- Documentación interna con historial real de trabajo con Alberto
  (auditorías, entregas, informes): se elimina. README y CLAUDE.md se
  reescriben cortos y genéricos.
- Seeders de datos demo (`UserSeeder`, `DemoDataSeeder`): ya son ficticios,
  no requieren cambios.

## Identidad ficticia a introducir

- Director: **Diego Roldán Mesa**
- Despacho resultante de fusión: **Roldán Asesores** y **Roldán Abogados**
- Dirección: **Avenida de Calvo Sotelo 24, 39002 Santander, Cantabria**
- Teléfono: **600 000 000**
- Email de contacto por defecto: **info@nortia-demo.test** (dominio
  no real, para que nadie escriba por error al correo de Alberto)
- Coordenadas de mapa: centro genérico de Santander (se mantienen las
  actuales, 43.4623 / -3.8099, ya que no señalan una dirección concreta)
- Nombre de fichero legado de import: **DESPACHO_READMODEL.db** (genérico)

## Alcance de cambios

### Contenido público / config (impacto en lo que se ve al mostrar la app)
- `config/site.php`: valores por defecto de `copyright`.
- `.env.example`: `SITE_ADDRESS`, `SITE_COPYRIGHT` (phone/email ya están
  vacíos por defecto, no requieren cambio de valor real).
- `resources/views/site/home.blade.php`: alt de imagen y texto "Alberto
  Berdejo Payno".
- `resources/views/site/quienes-somos.blade.php`: historia de fusión y
  nombre del director.

### Integraciones heredadas (código + comentarios, sin cambiar lógica)
- `app/Console/Commands/ImportarDespacho.php`
- `app/Support/Import/ReadModelImporter.php`
- `app/Support/SituacionExpediente.php` (comentario)

### Documentación — eliminar (historial real de trabajo con Alberto)
- `docs/AUDITORIA_2026-10.md`
- `docs/AUTOMATIZACION.md`
- `docs/INDEXACION_DOCUMENTAL.md`
- `docs/LEEME_PRIMERO_ENTREGA.md`
- `docs/puente-sharepoint.md`
- `docs/guia-env-paso-a-paso.md`
- `docs/INFORME_TECNICO.md` y `docs/INFORME_TECNICO.pdf`

### Documentación — reescribir corta y genérica
- `README.md`
- `CLAUDE.md`

### Documentación — editar (quitar la mención puntual, mantener el resto)
- `docs/DISENO.md` (línea sobre "la foto de Alberto")
- `docs/web-publica.md` (línea "Alberto debe confirmarlos")
- `docs/ARRANCAR_EN_LOCAL.md` (referencia a `DESPACHO_READMODEL.db`)

### Tests a actualizar (para que sigan pasando con el nuevo contenido)
- `tests/Feature/IdentidadVisualTest.php` (dirección esperada)
- `tests/Feature/CrearUsuarioTest.php` (email/nombre de ejemplo)
- `tests/Feature/SitioPublicoRetoquesTest.php` (comentario)
- `tests/Feature/PublicChatTest.php`, `tests/Feature/ReadinessTest.php`
  (sustituir el teléfono real usado como dato de prueba)

### Git
- Quitar el remoto `origin` (`git remote remove origin`) para que no haya
  push accidental al repo real.

## Verificación

- `php artisan test` debe pasar completo tras los cambios.
- Revisión visual rápida de `/`, `/quienes-somos`, `/contacto` en local.

## Fuera de alcance

- No se cambia el nombre de marca "Nortia".
- No se cambia la ciudad/región (Santander/Cantabria).
- No se reescribe el histórico de git (el trabajo es solo local, no se
  publica tal cual).
- No se tocan `docs/CONECTAR_MICROSOFT.md`, `docs/DEMO_MOVIL.md`,
  `docs/correo-microsoft-365.md`, `docs/VERIFACTU*.md` (sin datos
  personales reales).
