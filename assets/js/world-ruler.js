/**
 * File: assets/js/world-ruler.js
 * Version: 6.9.5.9 (Gravity Tether - Orientation Fix)
 * Description: Handles the floating gadget, audio player, visual slideshow, and network persistence.
 */
(function($) {
    'use strict';

    // 1. DATA VALIDATION
    if (typeof tbf_wr_data === 'undefined') {
        console.error('Princess Keilah: Configuration data missing.');
        return;
    }

    // 2. CONFIGURATION & STATE
    const STATE_KEY = 'tbf_wr_state_v6959_pk'; // Bumped key for clean slate
    
    // 3. DOM ELEMENTS
    const container = $('#tbf-world-ruler-container');
    const header = $('.wr-header');
    const audio = document.getElementById('wr-audio-element');
    const slideLayer = $('#wr-slideshow-layer');
    const trackInfo = $('.wr-track-info');
    
    // Overlay Elements (Playlist UI)
    const playlistOverlay = $('#wr-playlist-overlay');
    const overlayContent = $('#wr-overlay-content');
    const backBtn = $('#wr-back-playlists');
    
    // 4. RUNTIME VARIABLES
    let allPlaylists = tbf_wr_data.playlists || [];
    let currentPlaylistIdx = 0; 
    let currentTrackIdx = 0;    
    
    let activeQueue = [];       
    let isShuffle = false;
    
    let mediaPool = [];
    let slideTimer = null;
    let slideHistory = []; 
    let historyIdx = -1;
    
    let autoStartBlocked = false; 

    // ==========================================================================
    // MODULE A: DRAGGABLE PHYSICS ENGINE
    // Handles mouse/touch drag + Window Resize Safety
    // ==========================================================================
    function makeDraggable(el) {
        let isDragging = false;
        let startX, startY, initialLeft, initialTop;

        const onStart = (e) => {
            if (container.hasClass('maximized')) return;
            if ($(e.target).closest('.wr-controls, .wr-stage, .wr-player').length) return;

            isDragging = true;
            
            const clientX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
            const clientY = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY;

            startX = clientX;
            startY = clientY;

            const rect = container[0].getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;

            // Switch to absolute positioning for smooth drag
            container.css({ 
                bottom: 'auto', 
                right: 'auto', 
                left: initialLeft + 'px', 
                top: initialTop + 'px' 
            });
        };

        const onMove = (e) => {
            if (!isDragging) return;
            e.preventDefault(); 

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
                ensureOnScreen(); // Snap back if dragged too far
                saveState(); 
            }
        };

        header.on('mousedown touchstart', onStart);
        $(document).on('mousemove touchmove', onMove);
        $(document).on('mouseup touchend', onEnd);
    }
    
    // THE GRAVITY TETHER: Ensures player never flies off screen
    function ensureOnScreen() {
        if (container.hasClass('maximized')) return;

        const rect = container[0].getBoundingClientRect();
        const winW = window.innerWidth;
        const winH = window.innerHeight;
        let newLeft = rect.left;
        let newTop = rect.top;
        let needsFix = false;

        // Check Right Edge
        if (rect.right > winW) { newLeft = winW - rect.width - 20; needsFix = true; }
        // Check Left Edge
        if (rect.left < 0) { newLeft = 20; needsFix = true; }
        // Check Bottom Edge
        if (rect.bottom > winH) { newTop = winH - rect.height - 20; needsFix = true; }
        // Check Top Edge
        if (rect.top < 0) { newTop = 20; needsFix = true; }

        if (needsFix) {
            container.css({ top: newTop + 'px', left: newLeft + 'px' });
            saveState(); // Update storage with safe coordinates
        }
    }

    // Listen for Rotation or Resize
    $(window).on('resize orientationchange', function() {
        // Small timeout to allow browser layout to settle after rotation
        setTimeout(ensureOnScreen, 100);
        setTimeout(ensureOnScreen, 500); // Double tap for safety
    });
    
    makeDraggable(container);

    // ==========================================================================
    // MODULE B: STATE PERSISTENCE (SMART RESUME)
    // ==========================================================================
    function saveState() {
        if (!audio) return;
        
        const rect = container[0].getBoundingClientRect();
        const isActuallyPlaying = !audio.paused || autoStartBlocked;

        const state = {
            isOpen: container.hasClass('expanded'),
            isMaximized: container.hasClass('maximized'),
            isPlaying: isActuallyPlaying, 
            currentTime: audio.currentTime,
            plIdx: currentPlaylistIdx,
            trIdx: currentTrackIdx,
            volume: audio.volume,
            isShuffle: isShuffle,
            timestamp: Date.now(), 
            // Only save position if NOT maximized
            pos: !container.hasClass('maximized') ? { top: rect.top, left: rect.left } : loadState()?.pos
        };
        
        localStorage.setItem(STATE_KEY, JSON.stringify(state));
    }

    function loadState() {
        const raw = localStorage.getItem(STATE_KEY);
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (e) { return null; }
    }

    $(window).on('beforeunload pagehide visibilitychange', saveState);
    setInterval(saveState, 4000); 

    // ==========================================================================
    // MODULE C: PRINCESS KEILAH AUDIO ENGINE
    // ==========================================================================
    
    function loadPlaylist(idx, startTrackIdx = 0, autoPlay = false, resumeTime = 0) {
        currentPlaylistIdx = idx;
        const pl = allPlaylists[idx];
        
        if (!pl || !pl.tracks || pl.tracks.length === 0) {
            trackInfo.text("Empty Playlist");
            return;
        }

        if (pl.resolved) {
            setupQueue(pl.resolved, startTrackIdx, autoPlay, resumeTime);
        } else {
            trackInfo.text("Loading Playlist...");
            const idsStr = Array.isArray(pl.tracks) ? pl.tracks.join(',') : pl.tracks;
            
            $.post(tbf_wr_data.ajax_url, {
                action: 'tbfnmi_resolve_playlist',
                ids: idsStr
            }, function(res) {
                if (res.success && res.data.length > 0) {
                    pl.resolved = res.data; 
                    setupQueue(pl.resolved, startTrackIdx, autoPlay, resumeTime);
                } else {
                    trackInfo.text("Audio Unavailable");
                }
            }).fail(function() {
                trackInfo.text("Connection Error");
            });
        }
    }

    function setupQueue(tracks, startIdx, autoPlay, resumeTime = 0) {
        if (isShuffle) {
            activeQueue = [...tracks].sort(() => Math.random() - 0.5);
            currentTrackIdx = 0; 
        } else {
            activeQueue = tracks;
            currentTrackIdx = startIdx;
        }
        playTrack(currentTrackIdx, resumeTime, autoPlay);
    }

    function playTrack(index, startTime = 0, autoPlay = true) {
        if (activeQueue.length === 0) return;
        
        if (index < 0) index = activeQueue.length - 1;
        if (index >= activeQueue.length) index = 0;
        
        currentTrackIdx = index;
        const track = activeQueue[currentTrackIdx];
        
        if (!track || !track.url) {
            trackInfo.text("Error: Missing URL");
            return;
        }

        if (audio.src !== track.url || startTime > 0) {
            audio.src = track.url;
            audio.currentTime = startTime; 
        }
        
        trackInfo.text(track.title || "Track " + track.id);

        if (autoPlay) {
            const promise = audio.play();
            if (promise !== undefined) {
                promise.then(() => {
                    autoStartBlocked = false;
                }).catch(e => { 
                    console.log("Princess Keilah: Autoplay blocked. Waiting for interaction.");
                    autoStartBlocked = true;
                    trackInfo.text("Click anywhere to play music");
                });
            }
        }
    }

    // Auto-Start Recovery
    $(document).one('click keydown', function() {
        if (autoStartBlocked && audio.paused) {
            audio.play().then(() => {
                autoStartBlocked = false;
                trackInfo.text(activeQueue[currentTrackIdx].title); 
            });
        }
    });

    audio.onended = function() { playTrack(currentTrackIdx + 1); };
    $('#wr-prev-track').click(function() { playTrack(currentTrackIdx - 1); });
    $('#wr-next-track').click(function() { playTrack(currentTrackIdx + 1); });
    
    $('#wr-shuffle-toggle').click(function() {
        isShuffle = !isShuffle;
        $(this).toggleClass('active', isShuffle);
        loadPlaylist(currentPlaylistIdx, 0, !audio.paused);
    });

    // ==========================================================================
    // MODULE D: VISUAL SLIDESHOW ENGINE
    // ==========================================================================

    function fetchMedia() {
        if (mediaPool.length > 0) return; 

        const params = {
            action: 'tbfnmi_list',
            per_page: 100, 
            orderby: 'rand',
            mime: 'image',
            origin_blog_id: 0 
        };
        
        let customOrderIds = [];
        if (tbf_wr_data.mode === 'specific' && tbf_wr_data.specific_ids) { 
            params.include = tbf_wr_data.specific_ids; 
            params.orderby = 'date'; 
            customOrderIds = tbf_wr_data.specific_ids.split(',').map(Number);
        }
        
        $.get(tbf_wr_data.ajax_url, params, function(res) {
            if(res.success && res.data.items && res.data.items.length > 0) {
                mediaPool = res.data.items;
                
                if (customOrderIds.length > 0) {
                    mediaPool.sort(function(a, b) {
                        return customOrderIds.indexOf(parseInt(a.attachment_id)) - customOrderIds.indexOf(parseInt(b.attachment_id));
                    });
                }
                
                $('#wr-loading').hide();
                nextSlide();
            } else {
                $('#wr-loading').text('No media found.');
            }
        });
    }
    
    function showImage(item) {
        if (!item) return;
        
        const img = $('<img class="wr-slide">')
            .attr('src', item.url)
            .attr('data-id', item.attachment_id)
            .attr('data-blog', item.blog_id);
        
        slideLayer.append(img);
        img[0].offsetWidth; 
        img.addClass('active');

        if(slideLayer.children().length > 2) {
            slideLayer.children().first().remove();
        }

        img.on('dblclick', function() {
            window.location.href = tbf_wr_data.home_url + 'image/' + $(this).data('blog') + '-' + $(this).data('id') + '/';
        });
    }

    function nextSlide() {
        clearTimeout(slideTimer);
        if (mediaPool.length === 0) return;

        if (historyIdx < slideHistory.length - 1) {
            historyIdx++;
            showImage(slideHistory[historyIdx]);
        } else {
            let item;
            if (tbf_wr_data.mode === 'specific') {
                const nextIdx = slideHistory.length % mediaPool.length;
                item = mediaPool[nextIdx];
            } else {
                item = mediaPool[Math.floor(Math.random() * mediaPool.length)];
            }

            slideHistory.push(item);
            historyIdx = slideHistory.length - 1;
            
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
        slideTimer = setTimeout(nextSlide, tbf_wr_data.duration);
    }

    $('.wr-next-slide').click(function(e) { e.stopPropagation(); nextSlide(); });
    $('.wr-prev-slide').click(function(e) { e.stopPropagation(); prevSlide(); });

    // ==========================================================================
    // MODULE E: OVERLAY UI (PLAYLIST BROWSER)
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
            html = '<div style="padding:20px; color:#888;">No playlists found.</div>';
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
            overlayContent.html('<div style="color:#888; padding:20px;">Loading tracks...</div>');
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
        tracks.forEach((t, i) => {
            let activeClass = (activeQueue[currentTrackIdx] && activeQueue[currentTrackIdx].id === t.id) ? 'active' : '';
            
            html += `<div class="wr-pl-track ${activeClass}" onclick="tbfPlayItem(${i})">
                        <span>${t.title}</span>
                     </div>`;
        });
        overlayContent.html(html);

        window.tbfPlayItem = function(i) {
            isShuffle = false; 
            $('#wr-shuffle-toggle').removeClass('active');
            activeQueue = tracks;
            playTrack(i, 0, true);
        };
    }

    // ==========================================================================
    // MODULE F: INITIALIZATION BOOTSTRAP
    // ==========================================================================
    
    const saved = loadState();
    
    // Check Freshness (24h)
    const isFresh = saved && saved.timestamp && (Date.now() - saved.timestamp < 86400000);

    if (saved && isFresh) {
        // Restore Position with Validity Check
        if(saved.pos && saved.pos.top) {
            const winW = window.innerWidth;
            const winH = window.innerHeight;
            // Sanity check: Ensure it's somewhat on screen
            if (saved.pos.left < winW - 50 && saved.pos.top < winH - 50) {
                container.css({ 
                    top: saved.pos.top + 'px', 
                    left: saved.pos.left + 'px', 
                    bottom: 'auto', 
                    right: 'auto' 
                });
            } else {
                // If off-screen (e.g. orientation change while closed), reset
                container.css({ top: '', left: '', bottom: '20px', right: '20px' });
            }
        }

        audio.volume = saved.volume !== undefined ? saved.volume : 0.5;
        isShuffle = saved.isShuffle || false;
        if(isShuffle) $('#wr-shuffle-toggle').addClass('active');
        
        if (saved.isOpen) {
            container.addClass('expanded');
            if(saved.isMaximized) container.addClass('maximized');
            fetchMedia(); 
            
            const resumeTime = saved.currentTime || 0;
            loadPlaylist(saved.plIdx || 0, saved.trIdx || 0, saved.isPlaying, resumeTime);
            
        } else if ((tbf_wr_data.open_default || tbf_wr_data.auto_start) && !localStorage.getItem(STATE_KEY)) {
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

    // Force "On Screen" check once at startup
    ensureOnScreen();

    // ==========================================================================
    // MODULE G: MAIN UI EVENTS
    // ==========================================================================

    $('#tbf-wr-tab').click(function() {
        container.addClass('expanded');
        fetchMedia();
        if (audio.paused && audio.src) audio.play();
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
            container.attr('style', ''); 
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