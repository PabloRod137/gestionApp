# Sistema de diseño de Nortia

Una sola identidad para la web pública, el acceso de clientes y el portal del cliente (el panel del equipo también la hereda).
Sale del logo: **grafito cálido** del escudo, **oro oliva** del círculo y **marfil** de fondo.

## Dónde está
Todo pasa por un único pipeline de Tailwind/Vite — ya no hay una hoja de estilos aparte escrita a mano.
- `tailwind.config.js` — paleta de marca. Las escalas `gray`, `emerald`, `amber`, `rose`/`red` y `purple` están sustituidas por tonos de la misma gama (`gray`=grafito, `purple`=oro, `emerald`=ok, `amber`=aviso, `red`=error, `blue`=info): las utilidades de Tailwind (`bg-gray-900`, `text-purple-300`...) ya salen en la paleta de Nortia sin tocar cada pantalla.
- `resources/css/app.css` — fuentes (`@font-face`), y, dentro de `@layer components`, lo que una utilidad de Tailwind no puede expresar bien: degradados (`.hero`, `.product-hero`), el menú móvil y la burbuja del asistente (compartida por la web pública y el área de cliente), animaciones y estados que alterna el JS (`.calendar-day--*`, `.toast.active`...). Todo lo demás (layout, espaciado, tipografía) va como utilidades directamente en las vistas. `npm run build` tras cualquier cambio.
- `public/fonts/` — **Inter** (texto) y **Source Serif 4** (títulos), variables, con sus licencias (SIL OFL). Se sirven desde el propio servidor: no se pide nada a Google (RGPD).
- `resources/views/components/icono.blade.php` — iconos de línea SVG (`<x-icono n="tel" />`). No se usan emojis: cambian con cada sistema operativo.

**Clases que arma el JS por interpolación** (p. ej. `` `calendar-day--${state}` `` en `booking.js`): Tailwind no evalúa JS, así que no las ve y las purga en silencio aunque la regla exista en `app.css`. Hace falta que la clase completa aparezca escrita en algún `.js` bajo `public/js/` (ya escaneado por `content` en `tailwind.config.js`) o añadirla a `safelist`. `IdentidadVisualTest` vigila que las ya conocidas sigan sobreviviendo a la purga.

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
