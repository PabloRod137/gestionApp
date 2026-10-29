// Nortia - punto de entrada del frontend. Cada función vive en js/features/ y
// se activa solo si la página contiene sus elementos.
import { initNav } from './features/nav.js';
import { initBlog } from './features/blog.js';
import { initServiceFilters } from './features/service-filters.js';
import { initContactForm } from './features/contact-form.js';
import { initBooking } from './features/booking.js';
import { initMaps } from './features/map.js';
import { initPublicChat } from './features/chat/public-bubble.js';
import { initCartBadge } from './features/shop/cart-badge.js';
import { initShop } from './features/shop/shop.js';
import { initCartPage } from './features/shop/cart-page.js';
import { initOrderResult } from './features/shop/result-page.js';

initNav();
initBlog();
initServiceFilters();
initContactForm();
initMaps();
initPublicChat();
initCartBadge();
initShop();
await initCartPage();
await initOrderResult();
await initBooking();
