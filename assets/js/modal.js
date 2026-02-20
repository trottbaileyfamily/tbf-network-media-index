/* global jQuery, _, Backbone, wp, TBF_NMI */
/* =========================================================
   File: assets/js/modal.js
   Version: 3.0.3

   Featured (Gutenberg/REST):
   - Save remote featured meta via AJAX (tbfnmi_set_featured_remote)
   - Set featured_media to placeholder attachment (prevents WP REST from deleting _thumbnail_id)
   - Show visible errors if AJAX fails (so it never silently "stays placeholder")
   ========================================================= */
(function ($) {
  if (!window.wp || !wp.media || !window.TBF_NMI) return;

  const DEBUG = true;
  const log  = (...a) => { if (DEBUG) console.log('[TBF_NMI]', ...a); };
  const warn = (...a) => { if (DEBUG) console.warn('[TBF_NMI]', ...a); };
  const err  = (...a) => { if (DEBUG) console.error('[TBF_NMI]', ...a); };

  const PLACEHOLDER_ID = parseInt((TBF_NMI && TBF_NMI.placeholderId) ? TBF_NMI.placeholderId : 0, 10) || 0;

  function getCurrentPostId() {
    try {
      const id = wp.media?.model?.settings?.post?.id;
      return parseInt(id || 0, 10) || 0;
    } catch (e) { return 0; }
  }

  const Ajax = {
    list(params) {
      return $.ajax({
        url: TBF_NMI.ajax,
        method: 'GET',
        cache: false,
        dataType: 'json',
        data: Object.assign({ action: 'tbfnmi_list', nonce: TBF_NMI.nonce }, params || {})
      });
    },
    sites() {
      return $.ajax({
        url: TBF_NMI.ajax,
        method: 'GET',
        cache: false,
        dataType: 'json',
        data: { action: 'tbfnmi_sites', nonce: TBF_NMI.nonce }
      });
    },
    proxy(originBlogId, originAttId) {
      return $.ajax({
        url: TBF_NMI.ajax,
        method: 'POST',
        cache: false,
        dataType: 'json',
        data: {
          action: 'tbfnmi_proxy',
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
        data: Object.assign({ action: 'tbfnmi_proxy_url', nonce: TBF_NMI.nonce }, payload || {})
      });
    },
    setFeaturedRemote(payload) {
      return $.ajax({
        url: TBF_NMI.ajax,
        method: 'POST',
        cache: false,
        dataType: 'json',
        data: Object.assign({ action: 'tbfnmi_set_featured_remote', nonce: TBF_NMI.nonce }, payload || {})
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
    this.selectedMap = {};
    this.viewInstance = null;
    this.proxyCache = {};
  };

  Controller.prototype._key = function(model){
    const m = model.toJSON();
    if (m.source === 'vkmedia') return 'vk:' + String(m.vkmedia_id || 0) + ':' + String(m.user_id || 0);
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
    Object.keys(this.selectedMap).forEach((k) => {
      this._selectUI(k, false);
      this._markPending(k, false);
    });
    this.selectedMap = {};
    clearFrameSelection(this.frame);
  };

  Controller.prototype._enforceSingleSelection = function(){
    clearFrameSelection(this.frame);
    Object.keys(this.selectedMap).forEach((k) => {
      const entry = this.selectedMap[k];
      if (entry && entry.localId) removeFromFrameSelection(this.frame, entry.localId);
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

    if (!multi) this._enforceSingleSelection();

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
    className: 'tbfnmi-item attachment',
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
      this.$el.attr('data-tbfnmi-key', this.key);
      return this;
    },
    select(e){ e.preventDefault(); this.controller.toggleSelected(this.model); }
  });

  const NetworkMediaView = wp.media.View.extend({
    className: 'tbfnmi-view',
    events: {
      'click .tbfnmi-refresh': 'refresh',
      'click .tbfnmi-load-more': 'loadMore',
      'input .tbfnmi-search': 'onSearchInput',
      'change .tbfnmi-mime': 'refresh',
      'change .tbfnmi-origin': 'refresh'
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
        '<div class="tbfnmi-toolbar">' +
          '<input type="search" class="tbfnmi-search regular-text" placeholder="Search network media..." style="min-width:240px;" />' +
          '<select class="tbfnmi-mime">' +
            '<option value="">All Types</option>' +
            '<option value="image">Images</option>' +
            '<option value="video">Videos</option>' +
            '<option value="audio">Audio</option>' +
            '<option value="application">Documents</option>' +
          '</select>' +
          '<select class="tbfnmi-origin"><option value="">All origin sites</option></select>' +
          '<button type="button" class="button tbfnmi-refresh">Refresh</button>' +
          '<span class="tbfnmi-meta" style="color:#50575e;"></span>' +
          '<span class="tbfnmi-status" style="color:#50575e;"></span>' +
        '</div>' +
        '<ul class="tbfnmi-grid"></ul>' +
        '<div style="padding:10px 20px; border-top:1px solid #ccd0d4; background:#fff;">' +
          '<button type="button" class="button tbfnmi-load-more">Load more</button>' +
        '</div>'
      );

      this.$grid   = this.$('.tbfnmi-grid');
      this.$status = this.$('.tbfnmi-status');
      this.$meta   = this.$('.tbfnmi-meta');
      this.$origin = this.$('.tbfnmi-origin');

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
      this.query = (this.$('.tbfnmi-search').val() || '').trim();
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
      const $el = this.$grid.find('[data-tbfnmi-key="' + key.replace(/"/g,'&quot;') + '"]').first();
      if ($el && $el.length) $el.toggleClass('is-selected', !!on);
    },
    markTilePendingByKey(key, on){
      const $el = this.$grid.find('[data-tbfnmi-key="' + key.replace(/"/g,'&quot;') + '"]').first();
      if ($el && $el.length) $el.toggleClass('is-pending', !!on);
    },
    loadMore(isFirst){
      if (this.loading || this.done) return;

      this.loading = true;
      this.setStatus('Loading...');

      this.mime = this.$('.tbfnmi-mime').val() || '';
      this.origin_blog_id = this.$('.tbfnmi-origin').val() || '';

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

      if (isMultiSelectFrame(this)) return;

      const toolbar = this.toolbar && this.toolbar.get && this.toolbar.get('select');
      if (!toolbar) return;

      const frame = this;
      toolbar.click = () => {
        const keys = Object.keys(controller.selectedMap);
        if (!keys.length) return;

        const entry = controller.selectedMap[keys[0]];
        if (!entry || entry.pending || !entry.localId) {
          controller._setStatus('Preparing...');
          return;
        }

        const postId = getCurrentPostId();
        if (!postId) {
          alert('TBF NMI: Could not detect post ID (wp.media.model.settings.post.id).');
          controller._setStatus('Cannot detect post ID.');
          return;
        }

        const m = entry.model ? entry.model.toJSON() : {};
        const url = m.url || '';
        const mime = m.mime || 'application/octet-stream';
        const type = (m.media_type || '').toLowerCase() || (
          (mime.indexOf('video/') === 0) ? 'video' :
          (mime.indexOf('audio/') === 0) ? 'audio' :
          (mime.indexOf('image/') === 0) ? 'image' : 'file'
        );

        controller._setStatus('Saving featured...');
        log('Saving featured remote meta', { postId, url, mime, type });

        Ajax.setFeaturedRemote({ post_id: postId, url, mime, type })
          .done((resp) => {
            log('setFeaturedRemote response', resp);
            if (!resp || resp.success !== true) {
              alert('TBF NMI: Failed to save featured remote meta. Check console for details.');
              controller._setStatus('Failed to save featured.');
              return;
            }

            if (PLACEHOLDER_ID <= 0) {
              alert('TBF NMI: Placeholder attachment is missing.');
              controller._setStatus('Placeholder missing.');
              return;
            }

            const attachment = setFrameSelectionSingle(frame, PLACEHOLDER_ID);
            frame.close();
            frame.state().trigger('select', attachment);
          })
          .fail((xhr) => {
            err('setFeaturedRemote AJAX failed', xhr);
            alert('TBF NMI: AJAX failed saving featured remote meta. Check console.');
            controller._setStatus('AJAX failed.');
          });
      };
    });
  };

})(jQuery);
