/* global jQuery, _, Backbone, wp, tbfnmi_modal_data */
/* =========================================================
   File: assets/js/modal.js
   Version: 6.3.0 (Audio Block & Sidebar Preview Fixes)
   ========================================================= */
(function ($) {
  if (!window.wp || !wp.media || !window.tbfnmi_modal_data) return;

  const DEBUG = true;
  const log  = (...a) => { if (DEBUG) console.log('[TBFNMI]', ...a); };
  const warn = (...a) => { if (DEBUG) console.warn('[TBFNMI]', ...a); };
  const err  = (...a) => { if (DEBUG) console.error('[TBFNMI]', ...a); };

  const PLACEHOLDER_ID = parseInt((tbfnmi_modal_data && tbfnmi_modal_data.placeholderId) ? tbfnmi_modal_data.placeholderId : 0, 10) || 0;

  function getCurrentPostId() {
    try {
      const id = wp.media?.model?.settings?.post?.id;
      return parseInt(id || 0, 10) || 0;
    } catch (e) { return 0; }
  }

  const Ajax = {
    list(params) {
      return $.ajax({
        url: tbfnmi_modal_data.ajax,
        method: 'GET',
        cache: false,
        dataType: 'json',
        data: Object.assign({ action: 'tbfnmi_list', nonce: tbfnmi_modal_data.nonce }, params || {})
      });
    },
    sites() {
      return $.ajax({
        url: tbfnmi_modal_data.ajax,
        method: 'GET',
        cache: false,
        dataType: 'json',
        data: { action: 'tbfnmi_sites', nonce: tbfnmi_modal_data.nonce }
      });
    },
    proxy(originBlogId, originAttId) {
      return $.ajax({
        url: tbfnmi_modal_data.ajax,
        method: 'POST',
        cache: false,
        dataType: 'json',
        data: {
          action: 'tbfnmi_proxy',
          nonce: tbfnmi_modal_data.nonce,
          origin_blog_id: originBlogId,
          origin_attachment_id: originAttId
        }
      });
    },
    proxyUrl(payload) {
      return $.ajax({
        url: tbfnmi_modal_data.ajax,
        method: 'POST',
        cache: false,
        dataType: 'json',
        data: Object.assign({ action: 'tbfnmi_proxy_url', nonce: tbfnmi_modal_data.nonce }, payload || {})
      });
    },
    setFeaturedRemote(payload) {
      return $.ajax({
        url: tbfnmi_modal_data.ajax,
        method: 'POST',
        cache: false,
        dataType: 'json',
        data: Object.assign({ action: 'tbfnmi_set_featured_remote', nonce: tbfnmi_modal_data.nonce }, payload || {})
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
    if (this.viewInstance && this.viewInstance.hideSidebar) this.viewInstance.hideSidebar();
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
    
    // Show the new sidebar preview!
    if (this.viewInstance && this.viewInstance.renderSidebar) {
        this.viewInstance.renderSidebar(model);
    }

    if (!multi) {
      if (PLACEHOLDER_ID > 0) setFrameSelectionSingle(this.frame, PLACEHOLDER_ID);
      else clearFrameSelection(this.frame);
    }

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
      }).done((resp) => {
        if (!resp || resp.success !== true || !resp.data) return fail(resp);
        done(resp.data.local_attachment_id);
      }).fail(fail);
      return;
    }

    Ajax.proxy(m.blog_id, m.attachment_id).done((resp) => {
      if (!resp || resp.success !== true || !resp.data) return fail(resp);
      done(resp.data.local_attachment_id);
    }).fail(fail);
  };

  const ItemView = Backbone.View.extend({
    tagName: 'li',
    className: 'tbfnmi-item attachment',
    events: { click: 'select' },
    initialize(opts){ this.controller = opts.controller; this.key = opts.key; },
    render(){
      const m = this.model.toJSON();
      const thumb = m.thumb || m.url || '';
      
      // AUDIO FIX: Extract filename if title is missing
      const displayTitle = m.title || m.url.split('/').pop() || 'Media File';

      this.$el.html(
        '<div class="attachment-preview js--select-attachment">' +
          '<div class="thumbnail"><div class="centered"><img src="' + _.escape(thumb) + '" alt=""></div></div>' +
          '<button type="button" class="check" tabindex="-1" aria-hidden="true"><span class="media-modal-icon"></span></button>' +
          '<div class="filename"><div>' + _.escape(displayTitle) + '</div></div>' +
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

      // AUDIO BLOCK FIX: Sniff WP state to auto-select Audio/Video tab
      try {
          const lib = this.controller.frame.state().get('library');
          if (lib && lib.get('type')) {
              const types = lib.get('type');
              if (Array.isArray(types) && types.length > 0) this.mime = types[0];
              else if (typeof types === 'string') this.mime = types;
          }
      } catch(e) {}
    },
    render(){
      // SPLIT SCREEN FIX: Added Sidebar to match Native WP Layout
      this.$el.html(
        '<div style="display:flex; height:100%; width:100%;">' +
          '<div style="flex:1; display:flex; flex-direction:column; overflow:hidden;">' +
            '<div class="tbfnmi-toolbar" style="padding:10px 16px; border-bottom:1px solid #ddd; flex-shrink:0;">' +
              '<input type="search" class="tbfnmi-search regular-text" placeholder="Search network media..." style="min-width:240px; margin-right:10px;" />' +
              '<select class="tbfnmi-mime" style="margin-right:10px;">' +
                '<option value="">All Types</option>' +
                '<option value="image">Images</option>' +
                '<option value="video">Videos</option>' +
                '<option value="audio">Audio</option>' +
                '<option value="application">Documents</option>' +
              '</select>' +
              '<select class="tbfnmi-origin" style="margin-right:10px;"><option value="">All origin sites</option></select>' +
              '<button type="button" class="button tbfnmi-refresh">Refresh</button>' +
              '<span class="tbfnmi-status" style="color:#d63638; margin-left:10px; font-weight:600;"></span>' +
              '<span class="tbfnmi-meta" style="color:#50575e; float:right; margin-top:5px;"></span>' +
            '</div>' +
            '<ul class="tbfnmi-grid" style="flex:1; overflow-y:auto; align-content: flex-start; margin:0;"></ul>' +
            '<div style="padding:10px 20px; border-top:1px solid #ccd0d4; background:#fff; flex-shrink:0;">' +
              '<button type="button" class="button tbfnmi-load-more">Load more</button>' +
            '</div>' +
          '</div>' +
          '<div class="tbfnmi-sidebar media-sidebar" style="display:none; width:267px; background:#f3f4f5; border-left:1px solid #ccd0d4; padding:16px; overflow-y:auto; flex-shrink:0; box-sizing:border-box;"></div>' +
        '</div>'
      );

      this.$grid    = this.$('.tbfnmi-grid');
      this.$status  = this.$('.tbfnmi-status');
      this.$meta    = this.$('.tbfnmi-meta');
      this.$origin  = this.$('.tbfnmi-origin');
      this.$sidebar = this.$('.tbfnmi-sidebar');

      // Auto-select the dropdown if sniffing caught 'audio' or 'video'
      if (['image','video','audio','application'].indexOf(this.mime) !== -1) {
          this.$('.tbfnmi-mime').val(this.mime);
      } else {
          this.mime = '';
      }

      this.populateSites();
      this.refresh();
      return this;
    },
    renderSidebar(model) {
        const m = model.toJSON();
        const title = m.title || m.url.split('/').pop() || 'Media File';
        let mediaHtml = '';
        
        // AUDIO PREVIEW FIX: Embed actual HTML5 media players
        if (m.media_type === 'audio') {
            mediaHtml = '<img src="' + _.escape(m.thumb) + '" style="max-width:100%; max-height:100px; display:block; margin:0 auto 15px;" />' +
                        '<audio controls src="' + _.escape(m.url) + '" style="width:100%; height:40px; outline:none;"></audio>';
        } else if (m.media_type === 'video') {
            mediaHtml = '<video controls src="' + _.escape(m.url) + '" style="width:100%; border-radius:4px; outline:none;"></video>';
        } else {
            mediaHtml = '<img src="' + _.escape(m.thumb) + '" style="max-width:100%; height:auto; display:block; margin:0 auto; border-radius:4px;" />';
        }

        this.$sidebar.html(
            '<h2 style="font-size:14px; text-transform:uppercase; color:#646970; margin-top:0; margin-bottom:15px;">Attachment Details</h2>' +
            '<div style="margin-bottom:20px; background:#fff; border:1px solid #dcdcde; border-radius:4px; padding:12px; text-align:center;">' + mediaHtml + '</div>' +
            '<div style="font-weight:600; font-size:14px; word-break:break-all; margin-bottom:8px; line-height:1.4;">' + _.escape(title) + '</div>' +
            '<div style="color:#646970; font-size:13px; margin-bottom:4px;"><strong>Type:</strong> ' + _.escape(m.mime || m.media_type) + '</div>' +
            '<div style="color:#646970; font-size:13px; word-break:break-all;"><strong>URL:</strong> <a href="' + _.escape(m.url) + '" target="_blank" style="text-decoration:none;">View Original</a></div>'
        );
        this.$sidebar.show();
    },
    hideSidebar() {
        this.$sidebar.hide().empty();
    },
    setStatus(msg){ if (this.$status) this.$status.text(msg ? (msg) : ''); },
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
        per_page: (tbfnmi_modal_data && tbfnmi_modal_data.perPage) ? tbfnmi_modal_data.perPage : 60,
        s: this.query,
        mime: this.mime,
        origin_blog_id: this.origin_blog_id
      }).done((res) => {
        if (!res || res.success !== true || !res.data) {
          this.setStatus('Error: invalid response.');
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
      }).fail(() => {
        this.setStatus('AJAX failed');
      }).always(() => { this.loading = false; });
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
          controller._setStatus('Preparing data, please wait...');
          return;
        }

        // INSERT FIX: Check if we are inserting a Featured Image or just a standard Audio/Image block
        const stateId = frame.state().get('id');
        const isFeaturedImage = (stateId === 'featured-image');

        if (!isFeaturedImage) {
            // Standard Insert Mode (Audio blocks, Video blocks, post content)
            // WordPress relies on the frame selection array, which we already populated.
            // We just trigger the native WP behaviors and let it drop into Gutenberg!
            const attachment = setFrameSelectionSingle(frame, entry.localId);
            frame.close();
            frame.state().trigger('insert', [attachment]);
            frame.state().trigger('select');
            return;
        }

        // Featured Image specific override
        const postId = getCurrentPostId();
        if (!postId) {
          alert('TBFNMI: Could not detect post ID.');
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

        controller._setStatus('Saving featured image link...');

        Ajax.setFeaturedRemote({ post_id: postId, url, mime, type })
          .done((resp) => {
            if (!resp || resp.success !== true) {
              alert('TBFNMI: Failed to save featured remote meta.');
              controller._setStatus('Failed.');
              return;
            }
            if (PLACEHOLDER_ID <= 0) {
              alert('TBFNMI: Placeholder attachment is missing.');
              return;
            }
            const attachment = setFrameSelectionSingle(frame, PLACEHOLDER_ID);
            frame.close();
            frame.state().trigger('select', attachment);
          })
          .fail(() => {
            alert('TBFNMI: AJAX failed saving featured remote meta.');
            controller._setStatus('AJAX failed.');
          });
      };
    });
  };

})(jQuery);