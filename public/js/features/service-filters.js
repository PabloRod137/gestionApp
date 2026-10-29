import { $$, on } from '../lib/dom.js';

export function initServiceFilters() {
  const cards = $$('.service-detail[data-cat], .blog-card[data-cat]');
  if (!cards.length) return;
  const tabs = $$('.filter-tab[data-cat]');
  tabs.forEach(tab => on(tab, 'click', () => {
    tabs.forEach(item => item.classList.remove('active'));
    tab.classList.add('active');
    const cat = tab.dataset.cat;
    cards.forEach(card => {
      card.hidden = cat !== 'all' && card.dataset.cat !== cat;
    });
  }));
}
