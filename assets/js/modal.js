/* global jQuery, _, Backbone, wp, TBF_NMI */
/* =========================================================
   TBF Network Media Index - Media Modal Tab
   File: assets/js/modal.js
   Version: 1.0.27

   Gutenberg Featured Image fix:
   - Never use id=-1 in selection.
   - Use a real placeholder attachment id while proxy prepares.
   - Swap to real proxy attachment id when ready.
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
    }
  };

  function setFrameSelection(frame, attachmentId) {
    const attachment = wp.media.model.Attachment.get(attachmentId);
    try { attachment.fetch(); } catch(_){}

    try {
      const state = frame && frame.state && frame.state();
      const selection = state && state.get && state.get('selection');
      if (selection && selection.reset) selection.reset([attachment]);
    } catch(_){}

    return attachment;
  }

  function clearFrameSelection(frame) {
    try {
      const state = frame && frame.state && frame.state();
      const selection = state && state.get && state.get('selection');
      if (selection && selection.reset) selection.reset([]);
    } catch(_){}
  }

  const Controller = function(frame){
    this.frame = frame;
    this.selected = null;
    this.preparedLocalId = 0;
    this.proxyCache = {};
    this.viewInstance = null;
  };

  Controller.prototype.clear = function(){
    this.selected = null;
    this.preparedLocalId = 0;
    clearFrameSelection(this.frame);
  };

  Controller.prototype.setSelected = function(model){
    this.selected = model;
    this.preparedLocalId = 0;

    const m = model.toJSON();
    const originBlog = m.blog_id;
    const originAtt  = m.attachment_id;
    const key = String(originBlog) + ':' + String(originAtt);

    if (this.viewInstance && this.viewInstance.setStatus) {
      this.viewInstance.setStatus('Preparing...');
    }

    log('Selected network item:', {
      blog_id: originBlog,
      attachment_id: originAtt,
      url: m.url,
      thumb: m.thumb,
      mime: m.mime
    });

    // IMPORTANT: Immediately set a REAL attachment into selection (placeholder),
    // so Gutenberg never tries /media/-1.
    if (PLACEHOLDER_ID > 0) {
      setFrameSelection(this.frame, PLACEHOLDER_ID);
    } else {
      // If placeholder missing, better to keep selection empty than -1
      clearFrameSelection(this.frame);
      warn('PlaceholderId missing. Featured image may stay disabled until proxy is ready.');
    }

    // Cached proxy?
    if (this.proxyCache[key]) {
      const localId = this.proxyCache[key];
      this.preparedLocalId = localId;
      setFrameSelection(this.frame, localId);
      if (this.viewInstance && this.viewInstance.setStatus) this.viewInstance.setStatus('');
      log('Reused cached proxy attachment:', localId);
      return;
    }

    Ajax.proxy(originBlog, originAtt)
      .done((resp) => {
        if (!resp || resp.success !== true || !resp.data) {
          warn('Proxy response invalid:', resp);
          if (this.viewInstance && this.viewInstance.setStatus) this.viewInstance.setStatus('Proxy error.');
          return;
        }

        const localId = resp.data.local_attachment_id || resp.data.id || resp.data.attachment_id;
        if (!localId) {
          warn('Proxy response missing local ID:', resp);
          if (this.viewInstance && this.viewInstance.setStatus) this.viewInstance.setStatus('Proxy missing local ID.');
          return;
        }

        this.proxyCache[key] = localId;
        this.preparedLocalId = localId;

        // Swap selection to REAL proxy attachment id (Gutenberg REST fetch now works)
        setFrameSelection(this.frame, localId);

        if (this.viewInstance && this.viewInstance.setStatus) this.viewInstance.setStatus('');
        log('Prepared proxy attachment:', localId);
      })
      .fail((xhr) => {
        err('Proxy failed:', xhr);
        if (this.viewInstance && this.viewInstance.setStatus) this.viewInstance.setStatus('Proxy failed.');
      });
  };

  const ItemView = Backbone.View.extend({
    tagName: 'li',
    className: 'tbf-nmi-item attachment',
    events: { click: 'select' },
    initialize(opts){ this.controller = opts.controller; },
    render(){
      const m = this.model.toJSON();
      const thumb = m.thumb || m.url || '';
      this.$el.html(
        '<div class="attachment-preview js--select-attachment">' +
          '<div class="thumbnail"><div class="centered"><img src="' + _.escape(thumb) + '" alt=""></div></div>' +
          '<div class="filename"><div>' + _.escape(m.title || '') + '</div></div>' +
        '</div>'
      );
      return this;
    },
    select(){
      this.controller.setSelected(this.model);
      this.$el.closest('ul').find('.tbf-nmi-item').removeClass('is-selected');
      this.$el.addClass('is-selected');
    }
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

    setStatus(msg){
      if (this.$status) this.$status.text(msg ? (' ' + msg) : '');
    },

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

        if (typeof data.total !== 'undefined') this.total = parseInt(data.total, 10) || 0;
        if (typeof data.max_pages !== 'undefined') this.max_pages = parseInt(data.max_pages, 10) || 1;

        this.setMeta();

        if (!items.length) {
          const msg = data.message ? (' ' + data.message) : '';
          this.setStatus(isFirst ? ('No items found.' + msg) : ('No more items.' + msg));
          this.done = true;
          return;
        }

        items.forEach((it) => {
          const model = new Backbone.Model(it);
          const view = new ItemView({ model, controller: this.controller });
          this.$grid.append(view.render().el);
        });

        this.setStatus('');
        this.page += 1;
        this.setMeta();
      })
      .fail((xhr) => {
        let hint = '';
        if (xhr && typeof xhr.responseText === 'string') {
          if (xhr.responseText.trim() === '-1') hint = ' (nonce failed: refresh the page)';
          else if (xhr.responseText.toLowerCase().includes('<html')) hint = ' (HTML returned: check PHP errors/permissions)';
        }
        this.setStatus('AJAX failed' + hint);
        err('TBF NMI list error', xhr);
      })
      .always(() => {
        this.loading = false;
      });
    }
  });

  // Add router tab
  const oldBrowseRouter = wp.media.view.MediaFrame.Select.prototype.browseRouter;
  wp.media.view.MediaFrame.Select.prototype.browseRouter = function(routerView){
    oldBrowseRouter.apply(this, arguments);
    routerView.set('tbf-network-media', { text: 'Network Media', priority: 80 });
  };

  // Render tab + hook the Select button
  const oldBindHandlers = wp.media.view.MediaFrame.Select.prototype.bindHandlers;
  wp.media.view.MediaFrame.Select.prototype.bindHandlers = function(){
    oldBindHandlers.apply(this, arguments);

    this.on('content:render:tbf-network-media', () => {
      const controller = new Controller(this);
      const view = new NetworkMediaView({ controller });
      controller.viewInstance = view;

      this.content.set(view);

      const toolbar = this.toolbar && this.toolbar.get && this.toolbar.get('select');
      if (toolbar) {
        toolbar.click = () => {
          const localId = controller.preparedLocalId;

          if (!localId) {
            warn('Toolbar clicked but proxy not ready yet (wait for "Preparing..." to clear).');
            return;
          }

          const attachment = setFrameSelection(this, localId);

          this.close();
          this.state().trigger('select', attachment);

          log('Triggered frame select with local attachment:', localId);
        };
      }
    });
  };

})(jQuery);
