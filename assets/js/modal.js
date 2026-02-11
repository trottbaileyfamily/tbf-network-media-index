/* global jQuery, _, Backbone, wp, TBF_NMI */
/* =========================================================
   File: assets/js/modal.js
   Version: 3.0.1

   Fixes (v3.0.1):
   - Gallery: proxy attachments now render thumbnails (PHP supplies sizes via wp_prepare_attachment_for_js)
   - Single/Featured: enforce single selection (clear previous selectedMap + UI)
   - Keeps: vkmedia support + multi-select for gallery/playlist
   ========================================================= */
(function ($) {
  if (!window.wp || !wp.media || !window.TBF_NMI) return;

  const DEBUG = true;
  const log  = (...a) => { if (DEBUG) console.log('[TBF_NMI]', ...a); };
  const warn = (...a) => { if (DEBUG) console.warn('[TBF_NMI]', ...a); };
  const err  = (...a) => { if (DEBUG) console.error('[TBF_NMI]', ...a); };

  const PLACEHOLDER_ID = parseInt((TBF_NMI && TBF_NMI.placeholderId) ? TBF_NMI.placeholderId : 0, 10) || 0;

  const Ajax = {
    list(params) {
      return $.ajax({
        url: TBF_NMI.ajax,
        method: 'GET',
        cache: false,
        dataType: 'json',
        data: Object.assign({ action: 'tbf_nmi_list', nonce: TBF_NMI.nonce }, params || {})
      });
    },
    sites() {
      return $.ajax({
        url: TBF_NMI.ajax,
        method: 'GET',
        cache: false,
        dataType: 'json',
        data: { action: 'tbf_nmi_sites', nonce: TBF_NMI.nonce }
      });
    },
    proxy(originBlogId, originAttId) {
      return $.ajax({
        url: TBF_NMI.ajax,
        method: 'POST',
        cache: false,
        dataType: 'json',
        data: {
          action: 'tbf_nmi_proxy',
          nonce: TBF_NMI.nonce,
          origin_blog_id: originBlogId,
          origin_attachment_id: originAttId
        }
      });
    },
    proxyUrl(payload) {
      return $.ajax({
        url: TBF_NMI.ajax,
        method: 'POST',
        cache: false,
        dataType: 'json',
        data: Object.assign({
          action: 'tbf_nmi_proxy_url',
          nonce: TBF_NMI.nonce
        }, payload || {})
      });
    }
  };

  function getFrameSelection(frame) {
    try {
      const state = frame && frame.state && frame.state();
      return state && state.get ? state.get('selection') : null;
    } catch (e) { return null; }
  }

  function getFrameStateId(frame) {
    try {
      const state = frame && frame.state && frame.state();
      return state && state.id ? state.id : '';
    } catch (e) { return ''; }
  }

  function isMultiSelectFrame(frame) {
    const sid = (getFrameStateId(frame) || '').toLowerCase();
    if (sid.indexOf('gallery') === 0) return true;
    if (sid.indexOf('playlist') !== -1) return true;
    if (sid.indexOf('video-playlist') !== -1) return true;
    try { if (frame && frame.options && frame.options.multiple) return true; } catch (e) {}
    return false;
  }

  function setFrameSelectionSingle(frame, attachmentId) {
    const attachment = wp.media.model.Attachment.get(attachmentId);
    try { attachment.fetch(); } catch(_){}
    const selection = getFrameSelection(frame);
    if (selection && selection.reset) selection.reset([attachment]);
    return attachment;
  }

  function addToFrameSelection(frame, attachmentId) {
    const attachment = wp.media.model.Attachment.get(attachmentId);
    try { attachment.fetch(); } catch(_){}
    const selection = getFrameSelection(frame);
    if (!selection) return attachment;
    if (selection.get && selection.get(attachmentId)) return attachment;
    if (selection.add) selection.add(attachment);
    return attachment;
  }

  function removeFromFrameSelection(frame, attachmentId) {
    const selection = getFrameSelection(frame);
    if (!selection) return;
    if (selection.get && selection.get(attachmentId)) {
      const model = selection.get(attachmentId);
      if (selection.remove) selection.remove(model);
    }
  }

  function clearFrameSelection(frame) {
    const selection = getFrameSelection(frame);
    if (selection && selection.reset) selection.reset([]);
  }

  const Controller = function(frame){
    this.frame = frame;
    this.selectedMap = {}; // key => { model, localId, pending }
    this.viewInstance = null;
    this.proxyCache = {};  // key => localId
  };

  Controller.prototype._key = function(model){
    const m = model.toJSON();
    if (m.source === 'vkmedia') {
      return 'vk:' + String(m.vkmedia_id || 0) + ':' + String(m.user_id || 0);
    }
    return String(m.blog_id) + ':' + String(m.attachment_id);
  };

  Controller.prototype._setStatus = function(msg){
    if (this.viewInstance && this.viewInstance.setStatus) this.viewInstance.setStatus(msg);
  };

  Controller.prototype._selectUI = function(key, on){
    if (this.viewInstance && this.viewInstance.markTileSelectedByKey) this.viewInstance.markTileSelectedByKey(key, on);
  };

  Controller.prototype._markPending = function(key, on){
    if (this.viewInstance && this.viewInstance.markTilePendingByKey) this.viewInstance.markTilePendingByKey(key, on);
  };

  Controller.prototype.clear = function(){
    // Clear UI selection for all keys currently selected
    Object.keys(this.selectedMap).forEach((k) => {
      this._selectUI(k, false);
      this._markPending(k, false);
    });
    this.selectedMap = {};
    clearFrameSelection(this.frame);
  };

  /**
   * v3.0.1: in single-select frames, enforce ONLY ONE selected item
   */
  Controller.prototype._enforceSingleSelection = function(){
    // remove existing selection from WP frame too
    clearFrameSelection(this.frame);

    // remove all UI/map selections
    Object.keys(this.selectedMap).forEach((k) => {
      const entry = this.selectedMap[k];
      if (entry && entry.localId) {
        // remove from selection if present (safe)
        removeFromFrameSelection(this.frame, entry.localId);
      }
      this._selectUI(k, false);
      this._markPending(k, false);
    });
    this.selectedMap = {};
  };

  Controller.prototype.toggleSelected = function(model){
    const key = this._key(model);
    const multi = isMultiSelectFrame(this.frame);
    const m = model.toJSON();

    log('Selected network item:', m);

    // If single-select frame: clear everything before selecting new
    if (!multi) {
      this._enforceSingleSelection();
    }

    // If already selected: in multi mode toggle off
    if (this.selectedMap[key]) {
      if (multi) {
        const entry = this.selectedMap[key];
        if (entry && entry.localId) removeFromFrameSelection(this.frame, entry.localId);
        delete this.selectedMap[key];
        this._selectUI(key, false);
        this._markPending(key, false);
        this._setStatus('');
      }
      return;
    }

    this.selectedMap[key] = { model, localId: 0, pending: true };
    this._selectUI(key, true);
    this._markPending(key, true);
    this._setStatus('Preparing...');

    // Single select: keep buttons alive with placeholder
    if (!multi) {
      if (PLACEHOLDER_ID > 0) setFrameSelectionSingle(this.frame, PLACEHOLDER_ID);
      else clearFrameSelection(this.frame);
    }

    // Cached local id?
    if (this.proxyCache[key]) {
      const localId = this.proxyCache[key];
      this.selectedMap[key].localId = localId;
      this.selectedMap[key].pending = false;
      this._markPending(key, false);
      if (multi) addToFrameSelection(this.frame, localId);
      else setFrameSelectionSingle(this.frame, localId);
      this._setStatus('');
      return;
    }

    const done = (localId) => {
      this.proxyCache[key] = localId;

      // In single-select mode, user might have clicked another item already
      if (!this.selectedMap[key]) return;

      this.selectedMap[key].localId = localId;
      this.selectedMap[key].pending = false;
      this._markPending(key, false);

      if (multi) addToFrameSelection(this.frame, localId);
      else setFrameSelectionSingle(this.frame, localId);

      this._setStatus('');
      log('Prepared local attachment:', localId);
    };

    const fail = (xhrOrResp) => {
      err('Proxy failed:', xhrOrResp);
      this._setStatus('Proxy failed.');
      this._markPending(key, false);
    };

    if (m.source === 'vkmedia') {
      Ajax.proxyUrl({
        source: 'vkmedia',
        vkmedia_id: m.vkmedia_id || 0,
        user_id: m.user_id || 0,
        url: m.url || '',
        title: m.title || 'Vikinger Media',
        mime: m.mime || 'image/*'
      })
      .done((resp) => {
        if (!resp || resp.success !== true || !resp.data) return fail(resp);
        const localId = resp.data.local_attachment_id;
        if (!localId) return fail(resp);
        done(localId);
      })
      .fail(fail);

      return;
    }

    Ajax.proxy(m.blog_id, m.attachment_id)
      .done((resp) => {
        if (!resp || resp.success !== true || !resp.data) return fail(resp);
        const localId = resp.data.local_attachment_id;
        if (!localId) return fail(resp);
        done(localId);
      })
      .fail(fail);
  };

  const ItemView = Backbone.View.extend({
    tagName: 'li',
    className: 'tbf-nmi-item attachment',
    events: { click: 'select' },
    initialize(opts){ this.controller = opts.controller; this.key = opts.key; },
    render(){
      const m = this.model.toJSON();
      const thumb = m.thumb || m.url || '';
      this.$el.html(
        '<div class="attachment-preview js--select-attachment">' +
          '<div class="thumbnail"><div class="centered"><img src="' + _.escape(thumb) + '" alt=""></div></div>' +
          '<button type="button" class="check" tabindex="-1" aria-hidden="true"><span class="media-modal-icon"></span></button>' +
          '<div class="filename"><div>' + _.escape(m.title || '') + '</div></div>' +
        '</div>'
      );
      this.$el.attr('data-tbf-nmi-key', this.key);
      return this;
    },
    select(e){ e.preventDefault(); this.controller.toggleSelected(this.model); }
  });

  const NetworkMediaView = wp.media.View.extend({
    className: 'tbf-nmi-view',
    events: {
      'click .tbf-nmi-refresh': 'refresh',
      'click .tbf-nmi-load-more': 'loadMore',
      'input .tbf-nmi-search': 'onSearchInput',
      'change .tbf-nmi-mime': 'refresh',
      'change .tbf-nmi-origin': 'refresh'
    },
    initialize(opts){
      this.controller = opts.controller;
      this.page = 1;
      this.loading = false;
      this.done = false;
      this.query = '';
      this.mime = '';
      this.origin_blog_id = '';
      this.total = 0;
      this.max_pages = 1;
      this._searchTimer = null;
    },
    render(){
      this.$el.html(
        '<div class="tbf-nmi-toolbar">' +
          '<input type="search" class="tbf-nmi-search regular-text" placeholder="Search network media..." style="min-width:240px;" />' +
          '<select class="tbf-nmi-mime">' +
            '<option value="">All Types</option>' +
            '<option value="image">Images</option>' +
            '<option value="video">Videos</option>' +
            '<option value="audio">Audio</option>' +
            '<option value="application">Documents</option>' +
          '</select>' +
          '<select class="tbf-nmi-origin"><option value="">All origin sites</option></select>' +
          '<button type="button" class="button tbf-nmi-refresh">Refresh</button>' +
          '<span class="tbf-nmi-meta" style="color:#50575e;"></span>' +
          '<span class="tbf-nmi-status" style="color:#50575e;"></span>' +
        '</div>' +
        '<ul class="tbf-nmi-grid"></ul>' +
        '<div style="padding:10px 20px; border-top:1px solid #ccd0d4; background:#fff;">' +
          '<button type="button" class="button tbf-nmi-load-more">Load more</button>' +
        '</div>'
      );

      this.$grid   = this.$('.tbf-nmi-grid');
      this.$status = this.$('.tbf-nmi-status');
      this.$meta   = this.$('.tbf-nmi-meta');
      this.$origin = this.$('.tbf-nmi-origin');

      this.populateSites();
      this.refresh();
      return this;
    },
    setStatus(msg){ if (this.$status) this.$status.text(msg ? (' ' + msg) : ''); },
    setMeta(){
      const shownPage = Math.max(1, this.page);
      const mp = Math.max(1, this.max_pages || 1);
      const t = Math.max(0, this.total || 0);
      if (this.$meta) this.$meta.text('Items: ' + t + ' (page ' + shownPage + ' of ' + mp + ')');
    },
    populateSites(){
      Ajax.sites().done((res) => {
        if (!res || res.success !== true || !res.data) return;
        const sites = res.data.sites || res.data || [];
        sites.forEach((s) => {
          const bid = s.blog_id || s.id || '';
          const name = s.name || s.blogname || ('Site ' + bid);
          if (!bid) return;
          this.$origin.append('<option value="' + bid + '">' + _.escape(name) + ' (ID ' + bid + ')</option>');
        });
      }).fail(() => {});
    },
    onSearchInput(){
      this.query = (this.$('.tbf-nmi-search').val() || '').trim();
      clearTimeout(this._searchTimer);
      this._searchTimer = setTimeout(() => this.refresh(), 250);
    },
    refresh(){
      this.page = 1;
      this.done = false;
      this.$grid.empty();
      this.controller.clear();
      this.loadMore(true);
    },
    markTileSelectedByKey(key, on){
      const $el = this.$grid.find('[data-tbf-nmi-key="' + key.replace(/"/g,'&quot;') + '"]').first();
      if ($el && $el.length) $el.toggleClass('is-selected', !!on);
    },
    markTilePendingByKey(key, on){
      const $el = this.$grid.find('[data-tbf-nmi-key="' + key.replace(/"/g,'&quot;') + '"]').first();
      if ($el && $el.length) $el.toggleClass('is-pending', !!on);
    },
    loadMore(isFirst){
      if (this.loading || this.done) return;

      this.loading = true;
      this.setStatus('Loading...');

      this.mime = this.$('.tbf-nmi-mime').val() || '';
      this.origin_blog_id = this.$('.tbf-nmi-origin').val() || '';

      Ajax.list({
        page: this.page,
        per_page: (TBF_NMI && TBF_NMI.perPage) ? TBF_NMI.perPage : 60,
        s: this.query,
        mime: this.mime,
        origin_blog_id: this.origin_blog_id
      })
      .done((res) => {
        if (!res || res.success !== true || !res.data) {
          this.setStatus('Error: invalid response (not JSON).');
          return;
        }

        const data = res.data;
        const items = data.items || [];

        this.total = parseInt(data.total || 0, 10) || 0;
        this.max_pages = parseInt(data.max_pages || 1, 10) || 1;
        this.setMeta();

        if (!items.length) {
          this.setStatus(isFirst ? 'No items found.' : 'No more items.');
          this.done = true;
          return;
        }

        items.forEach((it) => {
          const model = new Backbone.Model(it);
          const key = (it.source === 'vkmedia')
            ? ('vk:' + String(it.vkmedia_id || 0) + ':' + String(it.user_id || 0))
            : (String(it.blog_id) + ':' + String(it.attachment_id));

          const view = new ItemView({ model, controller: this.controller, key });
          const el = view.render().el;
          this.$grid.append(el);

          if (this.controller.selectedMap[key]) {
            $(el).addClass('is-selected');
            if (this.controller.selectedMap[key].pending) $(el).addClass('is-pending');
          }
        });

        this.setStatus('');
        this.page += 1;
        this.setMeta();
      })
      .fail((xhr) => {
        this.setStatus('AJAX failed');
        err('TBF NMI list error', xhr);
      })
      .always(() => { this.loading = false; });
    }
  });

  const oldBrowseRouter = wp.media.view.MediaFrame.Select.prototype.browseRouter;
  wp.media.view.MediaFrame.Select.prototype.browseRouter = function(routerView){
    oldBrowseRouter.apply(this, arguments);
    routerView.set('tbf-network-media', { text: 'Network Media', priority: 80 });
  };

  const oldBindHandlers = wp.media.view.MediaFrame.Select.prototype.bindHandlers;
  wp.media.view.MediaFrame.Select.prototype.bindHandlers = function(){
    oldBindHandlers.apply(this, arguments);

    this.on('content:render:tbf-network-media', () => {
      const controller = new Controller(this);
      const view = new NetworkMediaView({ controller });
      controller.viewInstance = view;

      this.content.set(view);

      // Gallery/playlist: let WP handle toolbar click using selection collection
      if (isMultiSelectFrame(this)) return;

      const toolbar = this.toolbar && this.toolbar.get && this.toolbar.get('select');
      if (toolbar) {
        const frame = this;
        toolbar.click = () => {
          const keys = Object.keys(controller.selectedMap);
          if (!keys.length) return;

          // single mode: only one key should exist now
          const entry = controller.selectedMap[keys[0]];
          if (!entry || entry.pending || !entry.localId) {
            controller._setStatus('Preparing...');
            return;
          }

          const attachment = setFrameSelectionSingle(frame, entry.localId);
          frame.close();
          frame.state().trigger('select', attachment);
        };
      }
    });
  };

})(jQuery);
