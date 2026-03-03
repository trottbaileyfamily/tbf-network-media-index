/**
 * File: assets/js/world-ruler.js
 * Version: 6.9.5 (Manual Sort Enforcement & Complete Engine)
 */
(function($) {
    'use strict';

    if (typeof tbf_wr_data === 'undefined') return;

    // --- CONFIGURATION & STATE ---
    const STATE_KEY = 'tbf_wr_state';
    
    // --- DOM ELEMENTS ---
    const container = $('#tbf-world-ruler-container');
    const header = $('.wr-header');
    const audio = document.getElementById('wr-audio-element');
    const slideLayer = $('#wr-slideshow-layer');
    const trackInfo = $('.wr-track-info');
    
    // Overlay Elements
    const playlistOverlay = $('#wr-playlist-overlay');
    const overlayContent = $('#wr-overlay-content');
    const backBtn = $('#wr-back-playlists');
    
    // --- DATA STORE ---
    let allPlaylists = tbf_wr_data.playlists || [];
    let currentPlaylistIdx = 0; 
    let currentTrackIdx = 0;    
    
    let activeQueue = [];       
    let isShuffle = false;
    
    let mediaPool = [];
    let slideTimer = null;
    let slideHistory = []; 
    let historyIdx = -1;

    // ==========================================================================
    // 1. DRAGGABLE PHYSICS ENGINE
    // ==========================================================================
    function makeDraggable(el) {
        let isDragging = false;
        let startX, startY, initialLeft, initialTop;

        const onStart = (e) => {
            // Disable drag if maximized or if clicking controls
            if (container.hasClass('maximized')) return;
            if ($(e.target).closest('.wr-controls, .wr-stage, .wr-player').length) return;

            isDragging = true;
            
            // Normalize Touch vs Mouse
            const clientX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
            const clientY = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY;

            startX = clientX;
            startY = clientY;

            const rect = container[0].getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;

            // Switch from bottom/right positioning to top/left for dragging
            container.css({ 
                bottom: 'auto', 
                right: 'auto', 
                left: initialLeft + 'px', 
                top: initialTop + 'px' 
            });
        };

        const onMove = (e) => {
            if (!isDragging) return;
            e.preventDefault(); // Prevent scrolling while dragging

            const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
            const clientY = e.type === 'touchmove' ? e.touches[0].clientY : e.clientY;

            const dx = clientX - startX;
            const dy = clientY - startY;

            container.css({
                left: (initialLeft + dx) + 'px',
                top: (initialTop + dy) + 'px'
            });
        };

        const onEnd = () => {
            if (isDragging) {
                isDragging = false;
                saveState(); // Save new position
            }
        };

        header.on('mousedown touchstart', onStart);
        $(document).on('mousemove touchmove', onMove);
        $(document).on('mouseup touchend', onEnd);
    }

    // Initialize Drag
    makeDraggable(container);

    // ==========================================================================
    // 2. STATE PERSISTENCE
    // ==========================================================================
    function saveState() {
        const rect = container[0].getBoundingClientRect();
        const state = {
            isOpen: container.hasClass('expanded'),
            isMaximized: container.hasClass('maximized'),
            isPlaying: !audio.paused,
            currentTime: audio.currentTime,
            plIdx: currentPlaylistIdx,
            trIdx: currentTrackIdx,
            volume: audio.volume,
            isShuffle: isShuffle,
            // Only save position if NOT maximized to avoid saving 0,0
            pos: !container.hasClass('maximized') ? { top: rect.top, left: rect.left } : loadState()?.pos
        };
        localStorage.setItem(STATE_KEY, JSON.stringify(state));
    }

    function loadState() {
        const raw = localStorage.getItem(STATE_KEY);
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (e) { return null; }
    }

    window.addEventListener('beforeunload', saveState);

    // ==========================================================================
    // 3. QUEEN KEILAH AUDIO ENGINE
    // ==========================================================================
    
    function loadPlaylist(idx, startTrackIdx = 0, autoPlay = false) {
        currentPlaylistIdx = idx;
        const pl = allPlaylists[idx];
        
        if (!pl || !pl.tracks || pl.tracks.length === 0) {
            trackInfo.text("Empty Playlist");
            return;
        }

        // If playlist tracks are just IDs, we need to resolve them to URLs
        if (pl.resolved) {
            setupQueue(pl.resolved, startTrackIdx, autoPlay);
        } else {
            trackInfo.text("Loading Playlist...");
            
            // Handle array vs CSV string
            const idsStr = Array.isArray(pl.tracks) ? pl.tracks.join(',') : pl.tracks;
            
            $.post(tbf_wr_data.ajax_url, {
                action: 'tbfnmi_resolve_playlist',
                ids: idsStr
            }, function(res) {
                if (res.success && res.data.length > 0) {
                    pl.resolved = res.data; // Cache result
                    setupQueue(pl.resolved, startTrackIdx, autoPlay);
                } else {
                    trackInfo.text("Audio Unavailable");
                }
            }).fail(function() {
                trackInfo.text("Connection Error");
            });
        }
    }

    function setupQueue(tracks, startIdx, autoPlay) {
        if (isShuffle) {
            // Create a shuffled copy
            activeQueue = [...tracks].sort(() => Math.random() - 0.5);
            currentTrackIdx = 0; 
        } else {
            activeQueue = tracks;
            currentTrackIdx = startIdx;
        }
        playTrack(currentTrackIdx, 0, autoPlay);
    }

    function playTrack(index, startTime = 0, autoPlay = true) {
        if (activeQueue.length === 0) return;
        
        // Loop logic
        if (index < 0) index = activeQueue.length - 1;
        if (index >= activeQueue.length) index = 0;
        
        currentTrackIdx = index;
        const track = activeQueue[currentTrackIdx];
        
        if (!track || !track.url) {
            trackInfo.text("Error: Missing URL");
            return;
        }

        if (audio.src !== track.url) {
            audio.src = track.url;
        }
        
        audio.currentTime = startTime;
        trackInfo.text(track.title || "Track " + track.id);

        if (autoPlay) {
            const promise = audio.play();
            if (promise !== undefined) {
                promise.catch(e => { console.log("Autoplay blocked by browser policy"); });
            }
        }
        
        // Refresh Overlay UI if open to show active track
        if(playlistOverlay.hasClass('active') && backBtn.is(':visible')) {
            renderTrackListUI(activeQueue); 
        }
    }

    // Audio Event Listeners
    audio.onended = function() { 
        playTrack(currentTrackIdx + 1); 
    };

    $('#wr-prev-track').click(function() { 
        playTrack(currentTrackIdx - 1); 
    });

    $('#wr-next-track').click(function() { 
        playTrack(currentTrackIdx + 1); 
    });
    
    $('#wr-shuffle-toggle').click(function() {
        isShuffle = !isShuffle;
        $(this).toggleClass('active', isShuffle);
        // Reshuffle current playlist starting from 0
        loadPlaylist(currentPlaylistIdx, 0, !audio.paused);
    });

    // ==========================================================================
    // 4. VISUAL SLIDESHOW ENGINE
    // ==========================================================================

    function fetchMedia() {
        if (mediaPool.length > 0) return; // Already loaded

        const params = {
            action: 'tbfnmi_list',
            per_page: 100, // Fetch more to allow for reordering
            orderby: 'rand',
            mime: 'image',
            origin_blog_id: 0 // Force network query
        };

        // Custom Sort Logic for "Specific Images"
        let customOrderIds = [];
        if (tbf_wr_data.mode === 'specific' && tbf_wr_data.specific_ids) { 
            params.include = tbf_wr_data.specific_ids; 
            // DO NOT randomize if we want specific order.
            // But AJAX query doesn't support 'ordered' yet in SQL.
            // We request data, then sort in JS below.
            params.orderby = 'date'; 
            customOrderIds = tbf_wr_data.specific_ids.split(',').map(Number);
        }
        
        $.get(tbf_wr_data.ajax_url, params, function(res) {
            if(res.success && res.data.items && res.data.items.length > 0) {
                mediaPool = res.data.items;

                // --- APPLY MANUAL SORT IF SPECIFIC ---
                if (customOrderIds.length > 0) {
                    // Sort mediaPool to match customOrderIds
                    mediaPool.sort(function(a, b) {
                        return customOrderIds.indexOf(parseInt(a.attachment_id)) - customOrderIds.indexOf(parseInt(b.attachment_id));
                    });
                }
                // -------------------------------------

                $('#wr-loading').hide();
                nextSlide();
            } else {
                $('#wr-loading').text('No media found.');
            }
        });
    }
    
    function showImage(item) {
        if (!item) return;
        
        const img = $('<img class="wr-slide">').attr('src', item.url)
            .attr('data-id', item.attachment_id)
            .attr('data-blog', item.blog_id);
        
        slideLayer.append(img);
        
        // Force reflow for transition
        img[0].offsetWidth; 
        img.addClass('active');

        // Cleanup DOM to keep it light
        if(slideLayer.children().length > 2) {
            slideLayer.children().first().remove();
        }

        // Double Click to Visit Page
        img.on('dblclick', function() {
            const url = tbf_wr_data.home_url + 'image/' + $(this).data('blog') + '-' + $(this).data('id') + '/';
            window.location.href = url;
        });
    }

    function nextSlide() {
        clearTimeout(slideTimer);
        if (mediaPool.length === 0) return;

        // History Logic (Back button support)
        if (historyIdx < slideHistory.length - 1) {
            historyIdx++;
            showImage(slideHistory[historyIdx]);
        } else {
            // New Image Logic
            let item;
            
            if (tbf_wr_data.mode === 'specific') {
                // Sequential Loop for specific
                // Use history length as index module pool length
                const nextIdx = slideHistory.length % mediaPool.length;
                item = mediaPool[nextIdx];
            } else {
                // Random
                item = mediaPool[Math.floor(Math.random() * mediaPool.length)];
            }

            slideHistory.push(item);
            historyIdx = slideHistory.length - 1;
            
            // Cap history to prevent memory leak
            if (slideHistory.length > 50) {
                slideHistory.shift();
                historyIdx--;
            }
            
            showImage(item);
        }
        slideTimer = setTimeout(nextSlide, tbf_wr_data.duration);
    }

    function prevSlide() {
        clearTimeout(slideTimer);
        if (historyIdx > 0) {
            historyIdx--;
            showImage(slideHistory[historyIdx]);
        }
        // Restart timer after interaction
        slideTimer = setTimeout(nextSlide, tbf_wr_data.duration);
    }

    // Manual Slide Controls
    $('.wr-next-slide').click(function(e) { e.stopPropagation(); nextSlide(); });
    $('.wr-prev-slide').click(function(e) { e.stopPropagation(); prevSlide(); });

    // ==========================================================================
    // 5. PLAYLIST OVERLAY UI
    // ==========================================================================

    $('#wr-playlist-toggle').click(function() { 
        renderOverlayPlaylists(); 
        playlistOverlay.addClass('active'); 
    });

    $('#wr-close-overlay').click(function() { 
        playlistOverlay.removeClass('active'); 
    });

    $('#wr-back-playlists').click(function() { 
        renderOverlayPlaylists(); 
    });

    function renderOverlayPlaylists() {
        backBtn.hide();
        $('.wr-overlay-title').text('Playlists');
        
        let html = '';
        if(allPlaylists.length === 0) {
            html = '<div style="padding:20px; color:#888; text-align:center;">No playlists found.</div>';
        } else {
            allPlaylists.forEach((pl, i) => {
                const trackCount = pl.tracks ? pl.tracks.length : 0;
                html += `<div class="wr-pl-item" onclick="tbfOpenPl(${i})">
                            <span>${pl.name}</span>
                            <span style="opacity:0.5; font-size:11px;">${trackCount} tracks</span>
                         </div>`;
            });
        }
        overlayContent.html(html);
        
        window.tbfOpenPl = function(i) {
            currentPlaylistIdx = i;
            renderOverlayTracks();
        };
    }

    function renderOverlayTracks() {
        backBtn.show();
        const pl = allPlaylists[currentPlaylistIdx];
        $('.wr-overlay-title').text(pl.name);
        
        if (!pl.resolved) {
            overlayContent.html('<div style="color:#888; padding:20px; text-align:center;">Loading tracks...</div>');
            const idsStr = Array.isArray(pl.tracks) ? pl.tracks.join(',') : pl.tracks;
            
            $.post(tbf_wr_data.ajax_url, { 
                action: 'tbfnmi_resolve_playlist', 
                ids: idsStr 
            }, function(res) {
                if(res.success) { 
                    pl.resolved = res.data; 
                    renderTrackListUI(pl.resolved); 
                } else {
                    overlayContent.html('<div style="color:#888; padding:20px;">Failed to load.</div>');
                }
            });
        } else {
            renderTrackListUI(pl.resolved);
        }
    }

    function renderTrackListUI(tracks) {
        let html = '';
        if(tracks.length === 0) html = '<div style="padding:20px; color:#888;">Empty Playlist.</div>';
        else {
            tracks.forEach((t, i) => {
                let activeClass = '';
                const currentPlaying = activeQueue[currentTrackIdx];
                if (currentPlaying && currentPlaying.id === t.id && !audio.paused) {
                    activeClass = 'active';
                }

                html += `<div class="wr-pl-track ${activeClass}" onclick="tbfPlayItem(${i})">
                            <span>${t.title}</span>
                         </div>`;
            });
        }
        overlayContent.html(html);

        window.tbfPlayItem = function(i) {
            isShuffle = false; 
            $('#wr-shuffle-toggle').removeClass('active');
            activeQueue = tracks;
            playTrack(i, 0, true);
        };
    }

    // ==========================================================================
    // 6. INITIALIZATION & RESTORE STATE
    // ==========================================================================
    const saved = loadState();
    
    if (saved) {
        // Restore Position
        if(saved.pos && saved.pos.top) {
            container.css({ 
                top: saved.pos.top + 'px', 
                left: saved.pos.left + 'px', 
                bottom: 'auto', 
                right: 'auto' 
            });
        }

        // Restore Volume & Shuffle
        audio.volume = saved.volume !== undefined ? saved.volume : 0.5;
        isShuffle = saved.isShuffle || false;
        if(isShuffle) $('#wr-shuffle-toggle').addClass('active');
        
        // Restore Open/Play State
        if (saved.isOpen) {
            container.addClass('expanded');
            if(saved.isMaximized) container.addClass('maximized');
            fetchMedia();
            loadPlaylist(saved.plIdx || 0, saved.trIdx || 0, saved.isPlaying);
            if(saved.isPlaying) setTimeout(() => { audio.currentTime = saved.currentTime; }, 200);
        } else if ((tbf_wr_data.open_default || tbf_wr_data.auto_start) && !localStorage.getItem(STATE_KEY)) {
            // First visit / auto-start
            if(tbf_wr_data.open_default) container.addClass('expanded');
            fetchMedia();
            loadPlaylist(0, 0, !!tbf_wr_data.auto_start);
        } else {
            loadPlaylist(saved.plIdx || 0, 0, false);
        }
    } else {
        if (tbf_wr_data.open_default) container.addClass('expanded');
        fetchMedia();
        loadPlaylist(0, 0, !!tbf_wr_data.auto_start);
    }

    // ==========================================================================
    // 7. MAIN UI EVENTS
    // ==========================================================================

    $('#tbf-wr-tab').click(function() {
        container.addClass('expanded');
        fetchMedia();
        if (audio.src) audio.play();
    });

    $('#wr-minimize').click(function() {
        container.removeClass('expanded maximized');
    });

    $('#wr-close').click(function() {
        container.removeClass('expanded maximized');
        audio.pause();
        audio.currentTime = 0;
        localStorage.removeItem(STATE_KEY);
        container.css({ top: '', left: '', bottom: '20px', right: '20px' });
    });

    $('#wr-maximize').click(function() {
        container.toggleClass('maximized');
        if(container.hasClass('maximized')) {
            container.attr('style', ''); // Clear inline drag styles
        } else if(saved && saved.pos) {
            container.css({ 
                top: saved.pos.top + 'px', 
                left: saved.pos.left + 'px', 
                bottom: 'auto', 
                right: 'auto' 
            });
        }
    });

})(jQuery);