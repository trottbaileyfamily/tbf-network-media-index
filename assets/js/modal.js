/* global jQuery, _, Backbone, wp, tbfnmi_modal_data */
/* =========================================================
   File: assets/js/modal.js
   Version: 6.4.9 (CSS Sync & Architecture Consolidation)
   ========================================================= */
(function ($) {
    if (!window.wp || !wp.media || !window.tbfnmi_modal_data) return;

    const Ajax = {
        list(params) { return $.ajax({ url: tbfnmi_modal_data.ajax, method: 'GET', cache: false, dataType: 'json', data: Object.assign({ action: 'tbfnmi_list', nonce: tbfnmi_modal_data.nonce }, params || {}) }); },
        sites() { return $.ajax({ url: tbfnmi_modal_data.ajax, method: 'GET', cache: false, dataType: 'json', data: { action: 'tbfnmi_sites', nonce: tbfnmi_modal_data.nonce } }); },
        proxy(originBlogId, originAttId) { return $.ajax({ url: tbfnmi_modal_data.ajax, method: 'POST', cache: false, dataType: 'json', data: { action: 'tbfnmi_proxy', nonce: tbfnmi_modal_data.nonce, origin_blog_id: originBlogId, origin_attachment_id: originAttId } }); },
        proxyUrl(payload) { return $.ajax({ url: tbfnmi_modal_data.ajax, method: 'POST', cache: false, dataType: 'json', data: Object.assign({ action: 'tbfnmi_proxy_url', nonce: tbfnmi_modal_data.nonce }, payload || {}) }); }
    };

    function isMultiSelectFrame(frame) {
        try {
            const sid = (frame?.state()?.id || '').toLowerCase();
            if (sid.indexOf('gallery') === 0 || sid.indexOf('playlist') !== -1) return true;
            if (frame?.options?.multiple) return true;
        } catch (e) { }
        return false;
    }

    const Controller = function (frame) {
        this.frame = frame;
        this.selectedMap = {};
        this.viewInstance = null;
        this.proxyCache = {};
    };

    Controller.prototype._setStatus = function (msg) {
        if (this.viewInstance && this.viewInstance.setStatus) this.viewInstance.setStatus(msg);
    };

    Controller.prototype.clear = function () {
        this.selectedMap = {};
        const selection = this.frame.state().get('selection');
        if (selection) selection.reset([]);
        if (this.viewInstance) {
            this.viewInstance.$('.tbfnmi-item').removeClass('selected details is-pending');
            this.viewInstance.hideSidebar();
        }
    };

    Controller.prototype.toggleSelected = function (model, el) {
        const m = model.toJSON();
        const key = (m.source === 'vkmedia') ? 'vk:' + (m.vkmedia_id || 0) + ':' + (m.user_id || 0) : m.blog_id + ':' + m.attachment_id;
        const multi = isMultiSelectFrame(this.frame);
        const selection = this.frame.state().get('selection');

        if (!multi) {
            this.selectedMap = {};
            if (selection) selection.reset([]);
            if (this.viewInstance) this.viewInstance.$('.tbfnmi-item').removeClass('selected details');
        }

        if ($(el).hasClass('selected')) {
            delete this.selectedMap[key];
            $(el).removeClass('selected details');
            if (this.proxyCache[key] && selection) selection.remove(selection.get(this.proxyCache[key]));
            if (!multi && this.viewInstance) this.viewInstance.hideSidebar();
            return;
        }

        this.selectedMap[key] = { model, localId: 0 };
        $(el).addClass('selected details');
        this._setStatus('Preparing...');
        if (this.viewInstance) this.viewInstance.renderSidebar(model);

        const finalize = (attachmentId) => {
            if (!this.selectedMap[key]) return;
            this.selectedMap[key].localId = attachmentId;
            const att = wp.media.model.Attachment.get(attachmentId);
            att.set({ id: attachmentId, url: m.url, mime: m.mime || 'image/jpeg', type: m.media_type || 'image' });
            if (selection) selection.add(att);
            this._setStatus('');
        };

        if (this.proxyCache[key]) {
            finalize(this.proxyCache[key]);
            return;
        }

        const fail = () => this._setStatus('Failed to process media.');

        if (m.source === 'vkmedia') {
            Ajax.proxyUrl({ source: 'vkmedia', vkmedia_id: m.vkmedia_id || 0, user_id: m.user_id || 0, url: m.url || '', title: m.title || 'Media', mime: m.mime || 'image/*' })
                .done((resp) => {
                    if (resp && resp.success) {
                        this.proxyCache[key] = resp.data.local_attachment_id;
                        finalize(resp.data.local_attachment_id);
                    } else fail();
                }).fail(fail);
        } else {
            Ajax.proxy(m.blog_id, m.attachment_id).done((resp) => {
                if (resp && resp.success) {
                    this.proxyCache[key] = resp.data.local_attachment_id;
                    finalize(resp.data.local_attachment_id);
                } else fail();
            }).fail(fail);
        }
    };

    const NetworkMediaView = wp.media.View.extend({
        className: 'tbfnmi-view',
        events: {
            'click .tbfnmi-refresh': 'refresh',
            'click .tbfnmi-load-more': 'loadMore',
            'input .tbfnmi-search': 'onSearchInput',
            'change .tbfnmi-mime': 'refresh',
            'change .tbfnmi-origin': 'refresh',
            'click .tbfnmi-item': 'onItemClick'
        },
        initialize(opts) {
            this.controller = opts.controller;
            this.page = 1;
            this.loading = false;
            this.done = false;
            this.query = '';
            this.mime = '';
            this.origin_blog_id = '';
            
            try {
                const lib = this.controller.frame.state().get('library');
                if (lib && lib.get('type')) {
                    const types = lib.get('type');
                    this.mime = Array.isArray(types) ? types[0] : types;
                }
            } catch (e) { }
        },
        render() {
            // Structurally locked layout relying on the v6.4.9 CSS fixes
            this.$el.html(
                '<div style="display: flex; flex-direction: column; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #fff;">' +
                    '<div class="tbfnmi-toolbar">' +
                        '<input type="search" class="tbfnmi-search regular-text" placeholder="Search network..." />' +
                        '<select class="tbfnmi-mime"><option value="">All Types</option><option value="image">Images</option><option value="video">Videos</option><option value="audio">Audio</option><option value="application">Documents</option></select>' +
                        '<select class="tbfnmi-origin"><option value="">All origin sites</option></select>' +
                        '<button type="button" class="button tbfnmi-refresh">Refresh</button>' +
                        '<span class="tbfnmi-status" style="color: #d63638; font-weight: 600; margin-left: 10px; display: none;"></span>' +
                    '</div>' +
                    '<div style="flex: 1; display: flex; overflow: hidden; position: relative;">' +
                        '<div style="flex: 1; overflow-y: auto; background: #f0f0f1;">' +
                            '<ul class="attachments tbfnmi-grid"></ul>' +
                            '<div class="tbfnmi-load-more-wrap">' +
                                '<button type="button" class="button tbfnmi-load-more" style="display:none;">Load more</button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="tbfnmi-sidebar" style="display: none; width: 300px; flex-shrink: 0; background: #f3f4f5; border-left: 1px solid #ccd0d4; padding: 16px; overflow-y: auto;"></div>' +
                    '</div>' +
                '</div>'
            );

            this.$grid = this.$('.tbfnmi-grid');
            this.$status = this.$('.tbfnmi-status');
            this.$origin = this.$('.tbfnmi-origin');
            this.$sidebar = this.$('.tbfnmi-sidebar');

            this.$sidebar.on('mousedown mouseup click play pause', '.tbfnmi-attachment-details audio, .tbfnmi-attachment-details video', function(e) {
                e.stopPropagation();
            });

            if (['image', 'video', 'audio', 'application'].indexOf(this.mime) !== -1) this.$('.tbfnmi-mime').val(this.mime);

            this.populateSites();
            this.refresh();
            return this;
        },
        onItemClick(e) {
            e.preventDefault();
            const el = e.currentTarget;
            const key = $(el).attr('data-tbfnmi-key');
            const model = this.itemsMap[key];
            if (model) this.controller.toggleSelected(model, el);
        },
        renderSidebar(model) {
            const m = model.toJSON();
            let title = m.title || m.url.split('/').pop() || 'Media File';
            if (!m.title && m.url) title = title.replace(/\.[^/.]+$/, "");

            let mediaHtml = '';
            if (m.media_type === 'audio') {
                mediaHtml = '<audio controls src="' + _.escape(m.url) + '" style="width:100%; outline:none; margin-bottom:15px; display:block;"></audio>';
            } else if (m.media_type === 'video') {
                mediaHtml = '<video controls src="' + _.escape(m.url) + '" style="width:100%; background:#000; outline:none; margin-bottom:15px; display:block;"></video>';
            } else {
                mediaHtml = '<img src="' + _.escape(m.thumb || m.url) + '" style="max-width:100%; height:auto; border:1px solid #ddd; border-radius:4px; margin-bottom:15px; display:block;" />';
            }

            this.$sidebar.html(
                '<div class="tbfnmi-attachment-details attachment-details" style="padding-top:0;">' +
                    '<h2 style="font-size:12px; text-transform:uppercase; color:#646970; margin:0 0 15px 0; font-weight:600;">Attachment Details</h2>' +
                    mediaHtml +
                    '<div class="filename" style="font-weight:600; font-size:14px; word-break:break-all; margin-bottom:8px; line-height:1.4;">' + _.escape(title) + '</div>' +
                    '<div class="uploaded" style="color:#646970; font-size:12px; margin-bottom:4px;"><strong>Type:</strong> ' + _.escape(m.mime || m.media_type) + '</div>' +
                    '<div class="url" style="color:#646970; font-size:12px; word-break:break-all; margin-bottom:15px;"><strong>URL:</strong> <a href="' + _.escape(m.url) + '" target="_blank" style="color:#2271b1; text-decoration:underline;">View Original</a></div>' +
                '</div>'
            );
            this.$sidebar.show();
        },
        hideSidebar() { this.$sidebar.hide().empty(); },
        setStatus(msg) {
            if (this.$status) {
                if(msg) this.$status.text(msg).show();
                else this.$status.hide();
            }
        },
        populateSites() {
            Ajax.sites().done((res) => {
                if (!res || !res.data) return;
                const sites = res.data.sites || res.data || [];
                sites.forEach((s) => {
                    if (s.blog_id || s.id) this.$origin.append('<option value="' + (s.blog_id || s.id) + '">' + _.escape(s.name || s.blogname || 'Site ' + (s.blog_id || s.id)) + '</option>');
                });
            });
        },
        onSearchInput() {
            this.query = (this.$('.tbfnmi-search').val() || '').trim();
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => this.refresh(), 250);
        },
        refresh() {
            this.page = 1;
            this.done = false;
            this.$grid.empty();
            this.itemsMap = {};
            this.controller.clear();
            this.loadMore();
        },
        loadMore() {
            if (this.loading || this.done) return;
            this.loading = true;
            this.setStatus('Loading...');

            Ajax.list({
                page: this.page,
                per_page: (tbfnmi_modal_data && tbfnmi_modal_data.perPage) ? tbfnmi_modal_data.perPage : 60,
                s: this.query,
                mime: this.$('.tbfnmi-mime').val() || '',
                origin_blog_id: this.$('.tbfnmi-origin').val() || ''
            }).done((res) => {
                if (!res || !res.success || !res.data) { this.setStatus('Error loading.'); return; }
                const items = res.data.items || [];
                if (!items.length) {
                    this.setStatus(this.page === 1 ? 'No items found.' : 'No more items.');
                    this.$('.tbfnmi-load-more').hide();
                    this.done = true; return;
                }
                
                const includesUrl = (wp.media.view.settings && wp.media.view.settings.includesUrl) ? wp.media.view.settings.includesUrl : '/wp-includes/';

                items.forEach((it) => {
                    const model = new Backbone.Model(it);
                    const key = (it.source === 'vkmedia') ? 'vk:' + (it.vkmedia_id || 0) + ':' + (it.user_id || 0) : it.blog_id + ':' + it.attachment_id;
                    this.itemsMap[key] = model;

                    let thumb = it.thumb || it.url || '';
                    let title = it.title || 'Media File';
                    if (!it.title && it.url) title = it.url.split('/').pop().replace(/\.[^/.]+$/, "");

                    let thumbnailHtml = '';
                    
                    // The pure HTML structure. The v6.4.9 CSS handles all the sizing and centering.
                    if (it.media_type === 'audio' || thumb.match(/\.(mp3|wav|ogg|flac|m4a)$/i)) {
                        thumbnailHtml = '<div class="thumbnail"><div class="centered"><img src="' + includesUrl + 'images/media/audio.png" class="icon" alt=""></div></div>';
                    } else if (it.media_type === 'video' && (!it.thumb || it.thumb === it.url || thumb.match(/\.(mp4|webm|mov)$/i))) {
                        thumbnailHtml = '<div class="thumbnail"><div class="centered"><img src="' + includesUrl + 'images/media/video.png" class="icon" alt=""></div></div>';
                    } else {
                        thumbnailHtml = '<div class="thumbnail"><div class="centered"><img src="' + _.escape(thumb) + '" alt=""></div></div>';
                    }

                    const li = $('<li class="attachment tbfnmi-item" data-tbfnmi-key="' + key + '"></li>');
                    li.html(
                        '<div class="attachment-preview js--select-attachment type-' + _.escape(it.media_type) + '">' +
                            thumbnailHtml +
                            '<div class="filename"><div>' + _.escape(title) + '</div></div>' +
                        '</div>' +
                        '<button type="button" class="check" tabindex="-1"><span class="media-modal-icon"></span><span class="screen-reader-text">Deselect</span></button>'
                    );

                    this.$grid.append(li);
                    if (this.controller.selectedMap[key]) li.addClass('selected details');
                });
                
                if (this.page < parseInt(res.data.max_pages, 10)) {
                    this.$('.tbfnmi-load-more').show();
                } else {
                    this.$('.tbfnmi-load-more').hide();
                }

                this.setStatus('');
                this.page += 1;
            }).fail(() => { this.setStatus('AJAX failed'); }).always(() => { this.loading = false; });
        }
    });

    const oldBrowseRouter = wp.media.view.MediaFrame.Select.prototype.browseRouter;
    wp.media.view.MediaFrame.Select.prototype.browseRouter = function (routerView) {
        oldBrowseRouter.apply(this, arguments);
        routerView.set('tbf-network-media', { text: 'Network Media', priority: 80 });
    };

    const oldBindHandlers = wp.media.view.MediaFrame.Select.prototype.bindHandlers;
    wp.media.view.MediaFrame.Select.prototype.bindHandlers = function () {
        oldBindHandlers.apply(this, arguments);
        this.on('content:render:tbf-network-media', () => {
            const controller = new Controller(this);
            const view = new NetworkMediaView({ controller });
            controller.viewInstance = view;
            this.content.set(view);
        });
    };
})(jQuery);