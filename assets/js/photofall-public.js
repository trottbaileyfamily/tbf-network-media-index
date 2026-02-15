/*!
 * File: assets/js/photofall-public.js
 * Version: 4.1.3
 *
 * Photofall public JS enhancement:
 * - Infinite scroll
 * - Hash-based search (#q=...)
 *
 * v4.0.1 fix:
 * - Do NOT emit query params with "undefined"
 * - Only include params that have real values
 */

/* global jQuery, TBF_PHOTOFALL */
(function ($) {
  if (!window.TBF_PHOTOFALL) return;

  const state = {
    loading: false,
    nextPage: (TBF_PHOTOFALL.page || 1) + 1,
    hasMore: true,
    route: (TBF_PHOTOFALL.route || 'root').toString(),
    blogId: parseInt(TBF_PHOTOFALL.blogId || 0, 10) || 0,
    year: parseInt(TBF_PHOTOFALL.year || 0, 10) || 0,
    month: parseInt(TBF_PHOTOFALL.month || 0, 10) || 0,
    tag: (TBF_PHOTOFALL.tag || '').toString(),
    pageSize: parseInt(TBF_PHOTOFALL.pageSize || 96, 10) || 96,
    q: ''
  };

  function parseHash() {
    const h = (window.location.hash || '').replace(/^#/, '').trim();
    const out = {};
    if (!h) return out;
    h.split('&').forEach((pair) => {
      const [k, v] = pair.split('=');
      if (!k) return;
      out[decodeURIComponent(k)] = decodeURIComponent(v || '');
    });
    return out;
  }

  function setHashParam(key, val) {
    const cur = parseHash();
    if (!val) delete cur[key];
    else cur[key] = val;

    const parts = Object.keys(cur).map(k => encodeURIComponent(k) + '=' + encodeURIComponent(cur[k]));
    const next = parts.length ? ('#' + parts.join('&')) : '';
    if (window.location.hash !== next) window.location.hash = next;
  }

  function buildQuery(params) {
    // Remove null/undefined/empty-string keys so URLSearchParams never serializes "undefined"
    const clean = {};
    Object.keys(params).forEach((k) => {
      const v = params[k];
      if (v === undefined || v === null) return;
      if (typeof v === 'string' && v.trim() === '') return;
      clean[k] = v;
    });
    return new URLSearchParams(clean).toString();
  }

  function restUrl(path, params) {
    const base = (TBF_PHOTOFALL.rest || '').replace(/\/$/, '');
    const qs = buildQuery(params || {});
    return base + path + (qs ? ('?' + qs) : '');
  }

  function getGrid() {
    return document.querySelector('.tbf-photofall__grid');
  }

  function getSentinel() {
    return document.querySelector('.tbf-photofall__sentinel');
  }

  function setLoading(on) {
    state.loading = !!on;
    const el = document.querySelector('.tbf-photofall__footer');
    if (el) el.textContent = on ? 'Loading more…' : '';
  }

  function addFooterIfMissing() {
    if (document.querySelector('.tbf-photofall__footer')) return;
    const main = document.querySelector('.tbf-photofall__main');
    if (!main) return;
    const div = document.createElement('div');
    div.className = 'tbf-photofall__footer';
    main.appendChild(div);
  }

  function escapeHtml(s) {
    return (s || '').toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
  function escapeAttr(s) { return escapeHtml(s); }

  function cardHtml(it) {
    const href = it.href;
    const thumb = it.thumb_url || it.poster_url || it.url_full || '';
    const title = it.title || '';
    const isVideo = (it.media_type === 'video');

    return `
      <article class="tbf-pf-card">
        <a class="tbf-pf-card__link" href="${escapeAttr(href)}">
          <img class="tbf-pf-card__img" src="${escapeAttr(thumb)}" alt="${escapeAttr(it.alt || '')}" loading="lazy" />
          ${isVideo ? '<span class="tbf-pf-card__badge">▶</span>' : ''}
        </a>
        <h2 class="tbf-pf-card__title"><a href="${escapeAttr(href)}">${escapeHtml(title)}</a></h2>
      </article>
    `;
  }

  async function fetchNextPage() {
    if (state.loading || !state.hasMore) return;
    setLoading(true);

    const url = restUrl('/list', {
      route: state.route,
      page: state.nextPage,
      page_size: state.pageSize,
      blog_id: state.blogId > 0 ? state.blogId : undefined,
      year: state.year > 0 ? state.year : undefined,
      month: state.month > 0 ? state.month : undefined,
      tag: state.tag ? state.tag : undefined,
      q: state.q ? state.q : undefined
    });

    try {
      const res = await fetch(url, {
        headers: { 'X-WP-Nonce': TBF_PHOTOFALL.nonce || '' }
      });

      const json = await res.json();
      if (!json || !json.items) {
        state.hasMore = false;
        return;
      }

      const items = json.items || [];
      state.hasMore = !!json.has_more;
      state.nextPage = (json.page || state.nextPage) + 1;

      if (items.length) {
        const grid = getGrid();
        if (grid) {
          const frag = document.createElement('div');
          frag.innerHTML = items.map(cardHtml).join('');
          while (frag.firstChild) grid.appendChild(frag.firstChild);
        }
      }
    } catch (e) {
      state.hasMore = false;
    } finally {
      setLoading(false);
    }
  }

  function setupInfiniteScroll() {
    const sentinel = getSentinel();
    if (!sentinel) return;

    addFooterIfMissing();

    const io = new IntersectionObserver((entries) => {
      entries.forEach((ent) => {
        if (ent.isIntersecting) fetchNextPage();
      });
    }, { root: null, rootMargin: '800px 0px', threshold: 0.01 });

    io.observe(sentinel);
  }

  function setupHashSearch() {
    const params = parseHash();
    if (params.q) state.q = (params.q || '').trim();

    const input = document.querySelector('.tbf-photofall__search input[type="search"]');
    if (!input) return;

    input.value = state.q || '';

    let t = null;
    input.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        state.q = (input.value || '').trim();
        setHashParam('q', state.q);

        // Reload to page 1 (SEO safe)
        const base = window.location.pathname.replace(/\/page\/\d+\/?$/, '/');
        window.location.href = base;
      }, 350);
    });
  }

  $(function () {
    setupHashSearch();
    setupInfiniteScroll();
  });

})(jQuery);
