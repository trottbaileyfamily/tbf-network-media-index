/**
 * File: assets/js/photofall.js
 * Version: 4.0.3
 *
 * Photofall front-end: infinite scroll + hash routing + resilient thumbnails + item detail loader.
 */
/* global window, document, fetch */
(function () {
  'use strict';

  const CFG = window.TBF_PHOTOFALL || {};
  const apiBase = CFG.apiBase || '/1drop/wp-json/tbf-photofall/v1';
  const placeholder = CFG.placeholder || (window.location.origin + '/wp-content/uploads/2026/02/tbf-nmi-placeholder.png');
  const pageSize = Math.max(6, Math.min(200, parseInt(CFG.pageSize || 24, 10)));

  const els = {
    grid: document.querySelector('[data-photofall-grid]'),
    modal: document.querySelector('[data-photofall-modal]'),
    modalImg: document.querySelector('[data-photofall-modal-img]'),
    modalVideo: document.querySelector('[data-photofall-modal-video]'),
    modalTitle: document.querySelector('[data-photofall-modal-title]'),
    modalClose: document.querySelector('[data-photofall-modal-close]'),
    prev: document.querySelector('[data-photofall-prev]'),
    next: document.querySelector('[data-photofall-next]'),
    search: document.querySelector('[data-photofall-search]'),
  };

  if (!els.grid) return;

  let state = {
    route: 'root',
    page: 1,
    loading: false,
    hasMore: true,
    q: '',
  };

  function buildListUrl() {
    const u = new URL(apiBase + '/list', window.location.origin);
    u.searchParams.set('route', state.route);
    u.searchParams.set('page', String(state.page));
    u.searchParams.set('page_size', String(pageSize));
    if (state.q) u.searchParams.set('q', state.q);
    return u.toString();
  }

  function safeImgUrl(item) {
    // Key fix: use thumb_url if present, else full, else placeholder.
    return (item && (item.thumb_url || item.url_full)) || placeholder;
  }

  function makeCard(item) {
    const a = document.createElement('a');
    a.className = 'tbf-photofall-card';
    a.href = item.href || '#';
    a.dataset.blogId = item.blog_id || '';
    a.dataset.attachmentId = item.attachment_id || '';
    a.dataset.itemId = item.id || ''; // optional if your API later returns a network id
    a.addEventListener('click', function (e) {
      e.preventDefault();
      openFromItem(item);
    });

    const img = document.createElement('img');
    img.loading = 'lazy';
    img.decoding = 'async';
    img.referrerPolicy = 'no-referrer-when-downgrade';
    img.alt = (item && (item.alt || item.title)) || '';
    img.src = safeImgUrl(item);
    img.onerror = function () {
      // If thumb fails (common), try url_full, else placeholder.
      if (item && item.url_full && img.src !== item.url_full) {
        img.src = item.url_full;
      } else {
        img.src = placeholder;
      }
    };

    a.appendChild(img);
    return a;
  }

  async function fetchJson(url) {
    const r = await fetch(url, { credentials: 'same-origin' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  }

  async function loadNextPage() {
    if (state.loading || !state.hasMore) return;
    state.loading = true;

    try {
      const data = await fetchJson(buildListUrl());
      const items = Array.isArray(data.items) ? data.items : [];

      items.forEach((it) => els.grid.appendChild(makeCard(it)));

      state.hasMore = !!data.has_more;
      state.page += 1;
    } catch (err) {
      // stop thrashing on error
      state.hasMore = false;
      // optional: console for debugging
      // console.error('[Photofall] list error', err);
    } finally {
      state.loading = false;
    }
  }

  function showModal() {
    if (!els.modal) return;
    els.modal.style.display = 'block';
    document.documentElement.classList.add('tbf-photofall-modal-open');
  }

  function hideModal() {
    if (!els.modal) return;
    els.modal.style.display = 'none';
    document.documentElement.classList.remove('tbf-photofall-modal-open');
    if (els.modalVideo) {
      els.modalVideo.pause?.();
      els.modalVideo.removeAttribute('src');
      els.modalVideo.load?.();
    }
    if (els.modalImg) {
      els.modalImg.removeAttribute('src');
    }
    // Clear hash without jumping
    if (window.location.hash.startsWith('#i=')) {
      history.replaceState(null, '', window.location.pathname + window.location.search);
    }
  }

  function setHashForItem(item) {
    // hash format: #i=blogId:attId OR #i=id
    if (item && item.id) {
      window.location.hash = '#i=' + encodeURIComponent(String(item.id));
      return;
    }
    const bid = item && item.blog_id ? String(item.blog_id) : '';
    const aid = item && item.attachment_id ? String(item.attachment_id) : '';
    window.location.hash = '#i=' + encodeURIComponent(bid + ':' + aid);
  }

  async function loadItemDetailByHash() {
    const hash = window.location.hash || '';
    if (!hash.startsWith('#i=')) return;

    const raw = decodeURIComponent(hash.slice(3)).trim();
    let url;

    if (/^\d+$/.test(raw)) {
      // network id
      const u = new URL(apiBase + '/item', window.location.origin);
      u.searchParams.set('id', raw);
      url = u.toString();
    } else {
      // blog:att
      const parts = raw.split(':');
      const bid = parts[0] || '';
      const aid = parts[1] || '';
      const u = new URL(apiBase + '/item', window.location.origin);
      u.searchParams.set('blog_id', bid);
      u.searchParams.set('attachment_id', aid);
      url = u.toString();
    }

    try {
      const item = await fetchJson(url);
      openFromItem(item, { updateHash: false });
    } catch (e) {
      // If item doesn't resolve, close modal & clean hash
      hideModal();
    }
  }

  function openFromItem(item, opts) {
    opts = opts || {};
    if (!item) return;

    // Title
    if (els.modalTitle) els.modalTitle.textContent = item.title || '';

    // Render media
    const isVideo = item.media_type === 'video' || (item.mime || '').startsWith('video/');
    if (isVideo) {
      if (els.modalImg) els.modalImg.style.display = 'none';
      if (els.modalVideo) {
        els.modalVideo.style.display = 'block';
        els.modalVideo.controls = true;
        els.modalVideo.src = item.url_full || '';
      }
    } else {
      if (els.modalVideo) els.modalVideo.style.display = 'none';
      if (els.modalImg) {
        els.modalImg.style.display = 'block';
        els.modalImg.alt = item.alt || item.title || '';
        els.modalImg.src = item.url_full || safeImgUrl(item);
        els.modalImg.onerror = function () {
          els.modalImg.src = placeholder;
        };
      }
    }

    showModal();
    if (opts.updateHash !== false) setHashForItem(item);
  }

  // Infinite scroll (simple + reliable)
  function onScroll() {
    const nearBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 1200);
    if (nearBottom) loadNextPage();
  }

  // Search
  if (els.search) {
    let t = null;
    els.search.addEventListener('input', function () {
      clearTimeout(t);
      t = setTimeout(function () {
        state.q = (els.search.value || '').trim();
        state.page = 1;
        state.hasMore = true;
        els.grid.innerHTML = '';
        loadNextPage();
      }, 250);
    });
  }

  // Modal close
  if (els.modalClose) els.modalClose.addEventListener('click', hideModal);
  if (els.modal) {
    els.modal.addEventListener('click', function (e) {
      if (e.target === els.modal) hideModal();
    });
  }
  window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') hideModal();
  });

  // Hash routing
  window.addEventListener('hashchange', loadItemDetailByHash);

  // Boot
  window.addEventListener('scroll', onScroll, { passive: true });
  loadNextPage().then(loadItemDetailByHash);

})();
