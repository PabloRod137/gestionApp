import { $, $$, on, escapeHtml } from '../lib/dom.js';
import { BLOG_POSTS } from '../data/blog-posts.js';

// `heading` es el nivel del título de cada tarjeta: debe ser el siguiente al del encabezado anterior de la página.
function renderPosts(container, posts, heading = 'h3') {
  container.innerHTML = posts.map(post => `
    <article class="blog-card" data-cat="${escapeHtml(post.cat)}">
      <a class="blog-card__img" href="${escapeHtml(post.url)}" aria-hidden="true" tabindex="-1">
        <img src="${escapeHtml(post.image)}" alt="" loading="lazy">
      </a>
      <div class="blog-card__body">
        <div class="blog-card__cat">${escapeHtml(post.cat)}</div>
        <${heading} class="blog-card__title"><a href="${escapeHtml(post.url)}">${escapeHtml(post.title)}</a></${heading}>
        <p>${escapeHtml(post.excerpt)}</p>
        <a class="btn btn--outline btn--sm" href="${escapeHtml(post.url)}">Leer artículo</a>
      </div>
    </article>
  `).join('');
}

export function initBlog() {
  const preview = $('#blog-grid');
  if (preview) renderPosts(preview, BLOG_POSTS.slice(0, 3));

  const full = $('#blog-grid-full');
  if (!full) return;
  renderPosts(full, BLOG_POSTS, 'h2');
  $$('[data-blogcat]').forEach(tab => on(tab, 'click', () => {
    $$('[data-blogcat]').forEach(item => item.classList.remove('active'));
    tab.classList.add('active');
    const cat = tab.dataset.blogcat;
    renderPosts(full, cat === 'all' ? BLOG_POSTS : BLOG_POSTS.filter(post => post.cat === cat), 'h2');
  }));
}
