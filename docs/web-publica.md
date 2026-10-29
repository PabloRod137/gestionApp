# Web pública (portada desde el proyecto Node)

La web pública de Nortia (inicio, servicios, inmuebles, blog, tienda, carrito, contacto, reserva de cita y páginas legales)
vive en este mismo proyecto Laravel. Se diseñó primero como prototipo en Node y se ha portado aquí; el área cliente y el
panel del despacho ya existían.

## Dónde está cada cosa

| Qué | Dónde |
| --- | --- |
| Páginas (Blade) | `resources/views/site/*.blade.php`, entradas del blog en `resources/views/site/blog/` |
| Cabecera y pie | `resources/views/site/partials/` |
| Plantilla común | `resources/views/layouts/site.blade.php` |
| CSS, JavaScript e imágenes | `public/css`, `public/js`, `public/assets` (no pasan por Vite) |
| Rutas de las páginas | `routes/web.php`, sección «WEB PÚBLICA» |
| API que usa el JavaScript | `routes/web.php` sección 6 → `app/Http/Controllers/Api/` |
| Webhook de Stripe | `routes/api.php` → `StripeWebhookController` |
| Catálogo, horario y pagos | `config/shop.php` |
| Teléfono, correo y horario visibles | `config/site.php` |
| Datos legales | `config/legal.php` (variables `LEGAL_*` y `PRIVACY_*`) |
| Textos del asistente | `config/chat.php` |
| Lógica de la tienda | `app/Shop/` (`Catalog`, `Availability`, `Orders`, `BookingKeys`, `StripeGateway`) |
| Asistente público | `app/Chat/PublicChat.php` |

## API de la web (`/api/nortia/…`)

Con sesión y token CSRF (cabecera `X-CSRF-TOKEN`, que el JavaScript lee de la etiqueta `<meta name="csrf-token">`).
Los formularios (10 cada 15 min), las compras (20) y el chat (60) tienen límite de peticiones por IP.

- `GET services`, `GET availability?service=&date=`
- `POST contact`, `POST reservations` (campo trampa `website` contra bots)
- `GET shop/products`, `POST shop/checkout`, `GET shop/orders/{id}?clave=`
- `POST chat/public`
- `POST /api/stripe/webhook` (sin sesión; verifica la firma de Stripe)

## Cómo funciona el pago

1. El navegador solo envía identificadores y cantidades; **el precio sale siempre de `config/shop.php`**.
2. `checkout` crea un pedido `pending` y una sesión de Stripe Checkout (página de pago de Stripe: Nortia no ve la tarjeta).
3. El pedido solo pasa a `paid` cuando llega el aviso firmado de Stripe y el importe coincide con el del servidor.
   Es idempotente (los avisos repetidos no duplican correos) y los estados nunca retroceden.
4. Al pagar se emite una clave de reserva de un solo uso por consulta (90 días). El comprador elige día y hora con
   `/reservar-cita?pedido=…&clave=…`; la clave se gasta solo si la cita llega a guardarse.
5. Un pedido solo se ve con su clave de acceso (la que viaja en la URL de «pago completado») o desde la sesión de su cliente.

## Antes de publicar

```bash
php artisan site:readiness
```

Lista los bloqueos: datos legales sin completar, `APP_DEBUG` activo, `APP_URL` sin https, correo que no envía, y claves de
Stripe que no corresponden al entorno (de prueba en producción o reales fuera de ella).

Para probar los pagos: poner `PAYMENTS_ENABLED=true`, `PAYMENTS_PROVIDER=stripe`, las claves de **prueba** en el `.env` del
servidor y reenviar los avisos con la CLI de Stripe: `stripe listen --forward-to localhost:8000/api/stripe/webhook`.

## Pruebas

```bash
php artisan test
```

`phpunit.xml` usa SQLite en memoria, así que no hace falta MySQL ni Docker para ejecutar la suite.

## Pendiente de confirmar con el despacho

- **Precios y catálogo.** `config/shop.php` lleva los precios del prototipo (80 € / 95 € por consulta, comunidades
  50 € / 65 €). El despacho que use este software debe confirmarlos, junto con los nombres y precios de los
  documentos y si van con IVA incluido.
- **Horario de citas.** El calendario ofrece 9:00–12:00 y 16:00–18:30 de lunes a viernes (`business_windows`). El código
  anterior del proyecto usaba otro (L–J 9–14 y 16–19:30, V 9–15).
- **Datos legales** (`LEGAL_*`, `PRIVACY_*`), correo de salida y claves de Stripe.
- **Inmuebles y blog** son páginas estáticas; si el despacho quiere editarlos desde el panel hay que pasarlos a las tablas
  `properties` y `blog_posts`, que ya existen.
