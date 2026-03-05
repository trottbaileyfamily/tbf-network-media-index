/* global jQuery, _, Backbone, wp, tbfbkm_modal_data */
/* =========================================================
   File: assets/js/modal.js
   Version: 6.9.24 (Audio/Video Thumbnail Badges)
   ========================================================= */
(function ($) {
    if (!window.wp || !wp.media || !window.tbfbkm_modal_data) return;

    // --- 1. AJAX Helper ---
    const Ajax = {
        list(params) { 
            return $.ajax({ 
                url: tbfbkm_modal_data.ajax, 
                method: 'GET', 
                cache: false, 
                dataType: 'json', 
                data: Object.assign({ action: 'tbfbkm_list', nonce: tbfbkm_modal_data.nonce }, params || {}) 
            }); 
        },
        sites() { 
            return $.ajax({ 
                url: tbfbkm_modal_data.ajax, 
                method: 'GET', 
                cache: false, 
                dataType: 'json', 
                data: { action: 'tbfbkm_sites', nonce: tbfbkm_modal_data.nonce } 
            }); 
        },
        proxy(originBlogId, originAttId, url, title, mime) { 
            return $.ajax({ 
                url: tbfbkm_modal_data.ajax, 
                method: 'POST', 
                cache: false, 
                dataType: 'json', 
                data: { 
                    action: 'tbfbkm_proxy', 
                    nonce: tbfbkm_modal_data.nonce, 
                    origin_blog_id: originBlogId, 
                    origin_attachment_id: originAttId, 
                    url: url || '', 
                    title: title || '', 
                    mime: mime || '' 
                } 
            }); 
        },
        proxyUrl(payload) { 
            return $.ajax({ 
                url: tbfbkm_modal_data.ajax, 
                method: 'POST', 
                cache: false, 
                dataType: 'json', 
                data: Object.assign({ action: 'tbfbkm_proxy_url', nonce: tbfbkm_modal_data.nonce }, payload || {}) 
            }); 
        }
    };

    function isMultiSelectFrame(frame) {
        try {
            const sid = (frame?.state()?.id || '').toLowerCase();
            if (sid.indexOf('gallery') === 0 || sid.indexOf('playlist') !== -1) return true;
            if (frame?.options?.multiple) return true;
        } catch (e) { }
        return false;
    }

    // --- 2. Controller Logic ---
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
            this.viewInstance.$('.tbfbkm-item').removeClass('selected details is-pending');
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
            if (this.viewInstance) this.viewInstance.$('.tbfbkm-item').removeClass('selected details');
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

        const finalize = (attachmentId, proxyUrl) => {
            if (!this.selectedMap[key]) return;
            this.selectedMap[key].localId = attachmentId;
            const att = wp.media.model.Attachment.get(attachmentId);
            
            const finalUrl = proxyUrl || m.url;
            const w = parseInt(m.width) || 800;
            const h = parseInt(m.height) || 800;
            
            att.set({ 
                id: attachmentId, 
                url: finalUrl, 
                mime: m.mime || 'image/jpeg', 
                type: m.media_type || 'image',
                width: w,
                height: h,
                sizes: { full: { url: finalUrl, width: w, height: h } }
            });

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
                        finalize(resp.data.local_attachment_id, m.url);
                    } else fail();
                }).fail(fail);
        } else {
            Ajax.proxy(m.blog_id, m.attachment_id, m.url, m.title, m.mime).done((resp) => {
                if (resp && resp.success) {
                    this.proxyCache[key] = resp.data.local_attachment_id;
                    finalize(resp.data.local_attachment_id, resp.data.url);
                } else fail();
            }).fail(fail);
        }
    };

    // --- 3. The View (Grid UI) ---
    const NetworkMediaView = wp.media.View.extend({
        className: 'tbfbkm-view',
        events: {
            'input .tbfbkm-search-input': 'onSearchInput',
            
            'click .tbfbkm-tool-btn.type-filter': 'toggleTypeFilter',
            'click .tbfbkm-tool-btn.refresh': 'resetAndRefresh',
            'click .tbfbkm-tool-btn.shuffle': 'shuffle',
            'click .tbfbkm-tool-btn.captions': 'toggleCaptions',
            'click .tbfbkm-tool-btn.sidebar': 'toggleSidebar',
            
            'click .tbfbkm-load-more': 'loadMore',
            'change .tbfbkm-origin': 'refresh',
            'click .tbfbkm-item': 'onItemClick',
            
            'click .tbfbkm-set-audio-thumb': 'onSetAudioThumb'
        },
        initialize(opts) {
            this.controller = opts.controller;
            this.page = 1;
            this.loading = false;
            this.done = false;
            this.query = '';
            this.activeTypes = []; 
            this.origin_blog_id = '';
            this.orderby = 'date';
            this.sidebarEnabled = true;
            
            try {
                const lib = this.controller.frame.state().get('library');
                if (lib && lib.get('type')) {
                    const types = lib.get('type');
                    const initialType = Array.isArray(types) ? types[0] : types;
                    if(initialType) this.activeTypes = [initialType];
                }
            } catch (e) { }
        },
        render() {
            this.$el.html(
                '<div style="display: flex; flex-direction: column; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #fff;">' +
                    '<div class="tbfbkm-compact-toolbar">' +
                        
                        '<div class="tbfbkm-site-select-wrap">' +
                             '<select class="tbfbkm-origin"><option value="">All Sites</option></select>' +
                        '</div>' +

                        '<div class="tbfbkm-tools-scroll">' +
                        
                            '<div class="tbfbkm-search-wrap">' +
                                '<input type="search" class="tbfbkm-search-input" placeholder="Search" />' +
                            '</div>' +
                            
                            '<div class="tbfbkm-divider"></div>' +

                            '<button type="button" class="tbfbkm-tool-btn type-filter" data-type="image" title="Images"><span class="dashicons dashicons-format-image"></span></button>' +
                            '<button type="button" class="tbfbkm-tool-btn type-filter" data-type="video" title="Videos"><span class="dashicons dashicons-video-alt3"></span></button>' +
                            '<button type="button" class="tbfbkm-tool-btn type-filter" data-type="audio" title="Audio"><span class="dashicons dashicons-format-audio"></span></button>' +
                            '<button type="button" class="tbfbkm-tool-btn type-filter" data-type="application" title="Documents"><span class="dashicons dashicons-media-document"></span></button>' +
                            
                            '<div class="tbfbkm-divider"></div>' +

                            '<button type="button" class="tbfbkm-tool-btn refresh" title="Refresh"><span class="dashicons dashicons-update"></span></button>' +
                            '<button type="button" class="tbfbkm-tool-btn shuffle" title="Shuffle"><span class="dashicons dashicons-randomize"></span></button>' +
                            
                            '<div class="tbfbkm-divider"></div>' +

                            '<button type="button" class="tbfbkm-tool-btn captions" title="Toggle Captions"><span class="dashicons dashicons-text"></span></button>' +
                            '<button type="button" class="tbfbkm-tool-btn sidebar active" title="Toggle Details"><span class="dashicons dashicons-columns"></span></button>' +
                        
                        '</div>' + 

                        '<span class="tbfbkm-status"></span>' +
                    '</div>' +
                    
                    '<div style="flex: 1; display: flex; overflow: hidden; position: relative;">' +
                        '<div style="flex: 1; overflow-y: auto; background: #f0f0f1;">' +
                            '<ul class="attachments tbfbkm-grid"></ul>' +
                            '<div class="tbfbkm-load-more-wrap">' +
                                '<button type="button" class="button tbfbkm-load-more" style="display:none;">Load more</button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="tbfbkm-sidebar" style="display: none; width: 300px; flex-shrink: 0; background: #f3f4f5; border-left: 1px solid #ccd0d4; padding: 16px; overflow-y: auto;"></div>' +
                    '</div>' +
                '</div>'
            );
            this.$grid = this.$('.tbfbkm-grid');
            this.$status = this.$('.tbfbkm-status');
            this.$origin = this.$('.tbfbkm-origin');
            this.$sidebar = this.$('.tbfbkm-sidebar');
            this.$searchInput = this.$('.tbfbkm-search-input');

            this.$sidebar.on('mousedown mouseup click play pause', '.tbfbkm-attachment-details audio, .tbfbkm-attachment-details video', function(e) { e.stopPropagation(); });
            
            this.activeTypes.forEach(t => {
                this.$(`.tbfbkm-tool-btn.type-filter[data-type="${t}"]`).addClass('active');
            });

            this.populateSites();
            this.refresh();
            return this;
        },
        
        // Custom Audio Thumbnail Handler
        onSetAudioThumb(e) {
            e.preventDefault();
            const btn = $(e.currentTarget);
            const audioId = btn.data('id');
            const audioBlogId = btn.data('blog');
            const key = audioBlogId + ':' + audioId;
            
            const frame = wp.media({
                title: 'Select Custom Audio Thumbnail',
                button: { text: 'Set Thumbnail' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                const thumbUrl = attachment.url;
                
                const originalText = btn.text();
                btn.text('Saving...').prop('disabled', true);
                
                $.post(tbfbkm_modal_data.ajax, {
                    action: 'tbfbkm_set_audio_thumb',
                    nonce: tbfbkm_modal_data.nonce,
                    audio_id: audioId,
                    audio_blog_id: audioBlogId,
                    thumb_url: thumbUrl
                }).done((res) => {
                    if (res.success) {
                        btn.text('Thumbnail Set!');
                        
                        $('#tbfbkm-audio-thumb-preview').attr('src', thumbUrl);
                        
                        if (this.itemsMap && this.itemsMap[key]) {
                            this.itemsMap[key].set('thumb', thumbUrl);
                        }
                        
                        const gridItemImg = this.$(`.tbfbkm-item[data-tbfbkm-key="${key}"] .thumbnail`);
                        gridItemImg.html('<div class="centered tbf-portrait-fix"><img src="' + _.escape(thumbUrl) + '" alt="" style="object-fit:cover; width:100%; height:100%;"></div>');
                        
                        setTimeout(() => { btn.text(originalText).prop('disabled', false); }, 2000);
                    } else {
                        btn.text('Error Saving').prop('disabled', false);
                        setTimeout(() => { btn.text(originalText).prop('disabled', false); }, 2000);
                    }
                }).fail(() => {
                    btn.text('Network Error').prop('disabled', false);
                    setTimeout(() => { btn.text(originalText).prop('disabled', false); }, 2000);
                });
            });
            
            frame.open();
        },

        onSearchInput() {
            this.query = this.$searchInput.val().trim();
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => this.refresh(), 300);
        },
        toggleTypeFilter(e) {
            e.preventDefault();
            const btn = $(e.currentTarget);
            const type = btn.data('type');
            
            if (btn.hasClass('active')) {
                btn.removeClass('active');
                this.activeTypes = this.activeTypes.filter(t => t !== type);
            } else {
                btn.addClass('active');
                this.activeTypes.push(type);
            }
            this.refresh();
        },
        onItemClick(e) {
            e.preventDefault();
            const el = e.currentTarget;
            const key = $(el).attr('data-tbfbkm-key');
            const model = this.itemsMap[key];
            if (model) this.controller.toggleSelected(model, el);
        },
        toggleCaptions(e) {
            e.preventDefault();
            this.$el.toggleClass('hide-captions');
            $(e.currentTarget).toggleClass('active');
        },
        toggleSidebar(e) {
            e.preventDefault();
            this.sidebarEnabled = !this.sidebarEnabled;
            $(e.currentTarget).toggleClass('active', this.sidebarEnabled);
            
            if (this.sidebarEnabled) {
                if (this.$sidebar.children().length > 0) this.$sidebar.show();
            } else {
                this.$sidebar.hide();
            }
        },
        renderSidebar(model) {
            const m = model.toJSON();
            let title = m.title || m.url.split('/').pop() || 'Media File';
            if (!m.title && m.url) title = title.replace(/\.[^/.]+$/, "");

            let mediaHtml = '';
            const includesUrl = (wp.media.view.settings && wp.media.view.settings.includesUrl) ? wp.media.view.settings.includesUrl : '/wp-includes/';

            if (m.media_type === 'audio') {
                let previewImgSrc = m.thumb || m.url;
                if (previewImgSrc.match(/\.(mp3|wav|ogg|flac|m4a|aac)$/i) || previewImgSrc.indexOf('images/media/audio.png') !== -1) {
                    previewImgSrc = includesUrl + 'images/media/audio.png';
                }

                mediaHtml = '<div class="tbfbkm-audio-preview-wrap" style="position:relative; margin-bottom:15px;">' +
                                '<img src="' + _.escape(previewImgSrc) + '" style="width:100%; height:auto; border-radius:4px; display:block; margin-bottom:10px; background:#f0f0f1; object-fit:contain; max-height:200px;" id="tbfbkm-audio-thumb-preview" />' +
                                '<audio controls src="' + _.escape(m.url) + '" style="width:100%; outline:none; display:block;"></audio>' +
                                '<button type="button" class="button button-secondary tbfbkm-set-audio-thumb" data-id="' + _.escape(m.attachment_id) + '" data-blog="' + _.escape(m.blog_id) + '" style="width:100%; margin-top:10px; font-weight:bold;">Select Thumbnail</button>' +
                            '</div>';
            } else if (m.media_type === 'video') {
                mediaHtml = '<video controls src="' + _.escape(m.url) + '" style="width:100%; background:#000; outline:none; margin-bottom:15px; display:block;"></video>';
            } else {
                mediaHtml = '<img src="' + _.escape(m.thumb || m.url) + '" style="max-width:100%; height:auto; border:1px solid #ddd; border-radius:4px; margin-bottom:15px; display:block;" />';
            }

            this.$sidebar.html(
                '<div class="tbfbkm-attachment-details attachment-details" style="padding-top:0;">' +
                    '<h2 style="font-size:12px; text-transform:uppercase; color:#646970; margin:0 0 15px 0; font-weight:600;">Attachment Details</h2>' +
                    mediaHtml +
                    '<div class="filename" style="font-weight:600; font-size:14px; word-break:break-all; margin-bottom:8px; line-height:1.4;">' + _.escape(title) + '</div>' +
                    '<div class="uploaded" style="color:#646970; font-size:12px; margin-bottom:4px;"><strong>Type:</strong> ' + _.escape(m.mime || m.media_type) + '</div>' +
                    '<div class="url" style="color:#646970; font-size:12px; word-break:break-all; margin-bottom:15px;"><strong>URL:</strong> <a href="' + _.escape(m.url) + '" target="_blank" style="color:#2271b1; text-decoration:underline;">View Original</a></div>' +
                '</div>'
            );
            
            if (this.sidebarEnabled) this.$sidebar.show();
        },
        hideSidebar() { this.$sidebar.hide().empty(); },
        setStatus(msg) {
            if (this.$status) { if(msg) this.$status.text(msg).show(); else this.$status.hide(); }
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
        resetAndRefresh() {
            this.orderby = 'date';
            this.refresh();
        },
        shuffle() {
            this.orderby = 'rand';
            this.refresh();
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

            let mimeFilter = '';
            if (this.activeTypes.length > 0) {
                if(this.activeTypes.length === 1) mimeFilter = this.activeTypes[0];
                else mimeFilter = ''; 
            }

            Ajax.list({
                page: this.page,
                per_page: (tbfbkm_modal_data && tbfbkm_modal_data.perPage) ? tbfbkm_modal_data.perPage : 60,
                s: this.query,
                mime: mimeFilter,
                origin_blog_id: this.$('.tbfbkm-origin').val() || '',
                orderby: this.orderby
            }).done((res) => {
                if (!res || !res.success || !res.data) { this.setStatus('Error loading.'); return; }
                const items = res.data.items || [];
                if (!items.length) {
                    this.setStatus(this.page === 1 ? 'No items found.' : 'No more items.');
                    this.$('.tbfbkm-load-more').hide();
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
                    let badgeHtml = ''; // Smart icon overlay system
                    
                    if (it.media_type === 'audio') {
                        // Blue music badge for audio
                        badgeHtml = '<div style="position:absolute; top:6px; right:6px; background:#2271b1; color:#fff; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; z-index:5; box-shadow:0 2px 4px rgba(0,0,0,0.3); pointer-events:none;"><span class="dashicons dashicons-format-audio" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span></div>';
                        
                        if (thumb.match(/\.(mp3|wav|ogg|flac|m4a|aac)$/i) || thumb.indexOf('images/media/audio.png') !== -1) {
                            thumbnailHtml = '<div class="thumbnail"><div class="centered tbf-audio-icon-wrap"><img src="' + includesUrl + 'images/media/audio.png" class="icon" alt=""></div></div>';
                        } else {
                            thumbnailHtml = '<div class="thumbnail"><div class="centered tbf-portrait-fix"><img src="' + _.escape(thumb) + '" alt="" style="object-fit:cover; width:100%; height:100%;"></div></div>';
                        }
                    } else if (it.media_type === 'video') {
                        // Dark video badge for video
                        badgeHtml = '<div style="position:absolute; top:6px; right:6px; background:rgba(0,0,0,0.7); color:#fff; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; z-index:5; box-shadow:0 2px 4px rgba(0,0,0,0.3); pointer-events:none;"><span class="dashicons dashicons-video-alt3" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span></div>';
                        
                        if (!it.thumb || it.thumb === it.url || thumb.match(/\.(mp4|webm|mov)$/i)) {
                            thumbnailHtml = '<div class="thumbnail"><div class="centered tbf-video-icon-wrap"><img src="' + includesUrl + 'images/media/video.png" class="icon" alt=""></div></div>';
                        } else {
                            thumbnailHtml = '<div class="thumbnail"><div class="centered tbf-portrait-fix"><img src="' + _.escape(thumb) + '" alt=""></div></div>';
                        }
                    } else {
                        thumbnailHtml = '<div class="thumbnail"><div class="centered tbf-portrait-fix"><img src="' + _.escape(thumb) + '" alt=""></div></div>';
                    }

                    const li = $('<li class="attachment tbfbkm-item" data-tbfbkm-key="' + key + '"></li>');
                    li.html(
                        '<div class="attachment-preview js--select-attachment type-' + _.escape(it.media_type) + '" style="position:relative;">' +
                            badgeHtml +
                            thumbnailHtml +
                            '<div class="filename"><div>' + _.escape(title) + '</div></div>' +
                        '</div>' +
                        '<button type="button" class="check" tabindex="-1"><span class="media-modal-icon"></span><span class="screen-reader-text">Deselect</span></button>'
                    );

                    this.$grid.append(li);
                    if (this.controller.selectedMap[key]) li.addClass('selected details');
                });
                
                if (this.page < parseInt(res.data.max_pages, 10)) this.$('.tbfbkm-load-more').show();
                else this.$('.tbfbkm-load-more').hide();

                this.setStatus('');
                this.page += 1;
            }).fail(() => { this.setStatus('AJAX failed'); }).always(() => { this.loading = false; });
        }
    });

    // --- 4. Inject Logic into WP Media Frame ---
    const oldBrowseRouter = wp.media.view.MediaFrame.Select.prototype.browseRouter;
    wp.media.view.MediaFrame.Select.prototype.browseRouter = function (routerView) {
        oldBrowseRouter.apply(this, arguments);
        routerView.set('tbf-network-media', {
            text: 'Big King Media', 
            priority: 80 
        });
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
