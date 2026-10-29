# Sistema de diseño de Nortia

Una sola identidad para la web pública, el acceso de clientes y el portal del cliente (el panel del equipo también la hereda).
Sale del logo: **grafito cálido** del escudo, **oro oliva** del círculo y **marfil** de fondo.

## Dónde está
- `public/css/main.css` — web pública y acceso de clientes. Tokens en `:root` (`--grafito-*`, `--oro-*`, `--marfil`, `--arena`, `--ok/--aviso/--error/--info`).
- `tailwind.config.js` + `resources/css/app.css` — portal del cliente y panel (Tailwind). Las escalas `gray`, `emerald`, `amber`, `rose`, `blue` y `purple` están sustituidas por tonos apagados de la misma gama: las clases de las vistas ya salen en la paleta sin tocar cada pantalla. `npm run build` tras cualquier cambio.
- `public/css/chat-cliente.css` — burbuja y conversación del asistente del cliente.
- `public/fonts/` — **Inter** (texto) y **Source Serif 4** (títulos), variables, con sus licencias (SIL OFL). Se sirven desde el propio servidor: no se pide nada a Google (RGPD).
- `resources/views/components/icono.blade.php` — iconos de línea SVG (`<x-icono n="tel" />`). No se usan emojis: cambian con cada sistema operativo.

## Reglas
- **Contraste (WCAG AA, ≥ 4,5:1):** sobre oscuro, el texto dorado es `--oro-300` (`#D5C27A`, 7,6:1); sobre claro, `--oro-700` (`#6B5A1A`, 6,6:1). El oro vivo (`--oro-500`) solo en botones (con texto `#1B1E21`) y filetes. Texto secundario `--texto-suave`.
- **Un solo botón dorado por pantalla** (`.btn--primary`); lo demás es `.btn--outline`, `.btn--dark` o un enlace con flecha (`.link-arrow`).
- **Estados** (abierto, en proceso, finalizado, error): colores apagados `--info`, `--aviso`, `--ok`, `--error`; nunca pasteles.
- **Títulos** en Source Serif 4 peso 600, cifras lineales; **texto** en Inter. Escala: 14 / 16 / 20 / 25 / 31 / 39 / 49.
- Sin `!important` salvo casos justificados; cada clase que usa una vista pública tiene su regla (lo comprueba `IdentidadVisualTest`).
- Las tablas del portal (`.tabla-resp` + `data-l`) pasan a tarjetas en móvil.

## Contenido que decide el despacho
- Dirección, teléfono, correo y horario: `config/site.php` (`SITE_ADDRESS`, `SITE_PHONE`, `SITE_EMAIL`, `SITE_HOURS`). Coordenadas del mapa de contacto: `SITE_MAP_LAT/LNG` (ahora, el centro de Santander; falta el punto exacto de la oficina).
- Opiniones de clientes: `config/site.php` → `testimonials`. Vacío = la sección no se muestra. Solo opiniones reales y con permiso.
- Fotografías (`public/assets/external`): genéricas de banco de imágenes; sustituir por fotos propias cuando se puedan hacer, con fondo neutro y a la altura de la gama.
