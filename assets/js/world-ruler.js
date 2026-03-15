/**
 * File: assets/js/world-ruler.js
 * Version: 7.0.1.17
 * Description: JavaScript Engine for Princess Keilah Studio
 */
document.addEventListener('DOMContentLoaded', function() {
    
    if (typeof tbfbkm_wr_data === 'undefined') return;

    const tracks = tbfbkm_wr_data.tracks || [];
    const slideDuration = parseInt(tbfbkm_wr_data.slideDuration) || 5000;
    const adminAutoStart = tbfbkm_wr_data.autoStart === true;

    const ui = {
        wrapper: document.getElementById('tbfbkm-world-ruler'),
        audio: document.getElementById('wr-native-audio'),
        
        playBtn: document.getElementById('wr-btn-play'),
        prevBtn: document.getElementById('wr-btn-prev'),
        nextBtn: document.getElementById('wr-btn-next'),
        playlistBtn: document.getElementById('wr-btn-playlist'),
        slideshowBtn: document.getElementById('wr-btn-slideshow'),
        minAllBtn: document.getElementById('wr-btn-minimize-all'),
        restoreBtn: document.getElementById('wr-btn-restore'),
        
        ringFill: document.querySelector('.wr-ring-fill'),
        
        slideshowPane: document.getElementById('wr-slideshow-pane'),
        playlistPane: document.getElementById('wr-playlist-pane'),
        trackList: document.getElementById('wr-playlist-tracks'),
        slideTitle: document.getElementById('wr-slide-title'),
        slideMinBtn: document.getElementById('wr-slide-min'),
        
        visPrev: document.getElementById('wr-slide-nav-prev'),
        visNext: document.getElementById('wr-slide-nav-next')
    };

    if (!ui.wrapper) return;

    let currentTrackIdx = 0;

    // ==========================================
    // PROGRESS RING MATH
    // ==========================================
    const radius = ui.ringFill.r.baseVal.value;
    const circumference = radius * 2 * Math.PI;
    ui.ringFill.style.strokeDasharray = `${circumference} ${circumference}`;
    ui.ringFill.style.strokeDashoffset = circumference;

    function updateRing(percent) {
        ui.ringFill.style.strokeDashoffset = circumference - (percent / 100) * circumference;
    }

    // ==========================================
    // STATE PERSISTENCE LOGIC (Session Storage)
    // ==========================================
    const adminMinAll = tbfbkm_wr_data.isMinimizedOnLoad === true;
    
    let savedMinAll = sessionStorage.getItem('tbfbkm_wr_min_all');
    let savedSlideOpen = sessionStorage.getItem('tbfbkm_wr_slide_open');
    
    const isMinAll = savedMinAll !== null ? savedMinAll === 'true' : adminMinAll;
    const isSlideOpen = savedSlideOpen !== null ? savedSlideOpen === 'true' : !adminMinAll;

    if (isMinAll) {
        ui.wrapper.classList.add('wr-global-minimized');
    }

    if (isSlideOpen) {
        ui.slideshowPane.classList.add('open');
        ui.slideshowBtn.classList.add('active');
    }

    // ==========================================
    // AUDIO PERSISTENCE LOGIC
    // ==========================================
    let savedTrack = sessionStorage.getItem('tbfbkm_wr_track');
    let savedTime = sessionStorage.getItem('tbfbkm_wr_time');
    let wasPlaying = sessionStorage.getItem('tbfbkm_wr_playing') === 'true';

    if (tracks.length > 0) {
        renderPlaylist();
        
        let initialIdx = savedTrack !== null ? parseInt(savedTrack) : 0;
        if(initialIdx >= tracks.length) initialIdx = 0;
        
        loadTrack(initialIdx, false);
        
        if (savedTime) {
            ui.audio.currentTime = parseFloat(savedTime);
        }
        
        if (wasPlaying || adminAutoStart) {
            ui.audio.play().catch(e => {
                console.log("Autoplay blocked by browser. User interaction required.");
                sessionStorage.setItem('tbfbkm_wr_playing', 'false');
                ui.playBtn.innerHTML = '<span class="dashicons dashicons-controls-play"></span>';
            });
        }
    } else {
        ui.slideTitle.innerText = "No Audio Available";
        ui.playBtn.style.opacity = "0.3";
    }

    function loadTrack(idx, playImmediately = false) {
        if (!tracks[idx]) return;
        
        currentTrackIdx = idx;
        sessionStorage.setItem('tbfbkm_wr_track', idx);
        
        ui.audio.src = tracks[idx].url;
        ui.slideTitle.innerText = tracks[idx].title;
        
        document.querySelectorAll('.wr-pl-item').forEach(el => el.classList.remove('playing'));
        const activeItem = document.querySelector(`.wr-pl-item[data-idx="${idx}"]`);
        if(activeItem) {
            activeItem.classList.add('playing');
            activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        if (playImmediately) {
            ui.audio.play();
        }
    }

    // ==========================================
    // AUDIO EVENT LISTENERS
    // ==========================================
    ui.playBtn.addEventListener('click', () => {
        if (tracks.length === 0) return;
        if (ui.audio.paused) {
            ui.audio.play();
        } else {
            ui.audio.pause();
        }
    });

    ui.audio.addEventListener('play', () => { 
        ui.playBtn.innerHTML = '<span class="dashicons dashicons-controls-pause"></span>'; 
        sessionStorage.setItem('tbfbkm_wr_playing', 'true');
    });
    
    ui.audio.addEventListener('pause', () => { 
        ui.playBtn.innerHTML = '<span class="dashicons dashicons-controls-play"></span>'; 
        sessionStorage.setItem('tbfbkm_wr_playing', 'false');
    });

    ui.audio.addEventListener('timeupdate', () => {
        const percent = (ui.audio.currentTime / ui.audio.duration) * 100;
        updateRing(percent || 0);
        sessionStorage.setItem('tbfbkm_wr_time', ui.audio.currentTime);
    });

    function playNext() {
        if (tracks.length === 0) return;
        let nextIdx = currentTrackIdx + 1;
        if (nextIdx >= tracks.length) nextIdx = 0;
        loadTrack(nextIdx, true);
    }

    function playPrev() {
        if (tracks.length === 0) return;
        let prevIdx = currentTrackIdx - 1;
        if (prevIdx < 0) prevIdx = tracks.length - 1;
        loadTrack(prevIdx, true);
    }

    ui.nextBtn.addEventListener('click', playNext);
    ui.prevBtn.addEventListener('click', playPrev);
    ui.audio.addEventListener('ended', playNext);

    // ==========================================
    // UI TOGGLES & WINDOW CONTROLS
    // ==========================================
    ui.playlistBtn.addEventListener('click', () => {
        const isOpen = ui.playlistPane.classList.toggle('open');
        ui.playlistBtn.classList.toggle('active', isOpen);
        
        if (isOpen) {
            ui.playlistBtn.innerHTML = '<span class="dashicons dashicons-no-alt"></span>'; 
        } else {
            ui.playlistBtn.innerHTML = '<span class="dashicons dashicons-playlist-audio"></span>';
        }
    });

    ui.slideshowBtn.addEventListener('click', () => {
        const isOpen = ui.slideshowPane.classList.toggle('open');
        ui.slideshowBtn.classList.toggle('active', isOpen);
        sessionStorage.setItem('tbfbkm_wr_slide_open', isOpen);
    });

    ui.slideMinBtn.addEventListener('click', () => {
        ui.slideshowPane.classList.remove('open');
        ui.slideshowBtn.classList.remove('active');
        sessionStorage.setItem('tbfbkm_wr_slide_open', 'false');
    });

    ui.minAllBtn.addEventListener('click', () => {
        ui.wrapper.classList.add('wr-global-minimized');
        sessionStorage.setItem('tbfbkm_wr_min_all', 'true');
    });
    
    ui.restoreBtn.addEventListener('click', () => {
        ui.wrapper.classList.remove('wr-global-minimized');
        sessionStorage.setItem('tbfbkm_wr_min_all', 'false');
    });

    function renderPlaylist() {
        ui.trackList.innerHTML = '';
        tracks.forEach((track, idx) => {
            const div = document.createElement('div');
            div.className = 'wr-pl-item';
            div.dataset.idx = idx;
            div.innerText = track.title;
            div.addEventListener('click', () => {
                loadTrack(idx, true);
                if(window.innerWidth < 600) {
                    ui.playlistBtn.click();
                }
            });
            ui.trackList.appendChild(div);
        });
    }

    // ==========================================
    // SLIDESHOW ENGINE
    // ==========================================
    const visuals = document.querySelectorAll('.wr-visual-link');
    if (visuals.length > 1) {
        let currentVis = 0;
        let slideInterval;

        function showImage(idx) {
            visuals[currentVis].classList.remove('active');
            currentVis = idx;
            
            if (currentVis >= visuals.length) currentVis = 0;
            if (currentVis < 0) currentVis = visuals.length - 1;
            
            visuals[currentVis].classList.add('active');
        }

        function startSlideshow() {
            slideInterval = setInterval(() => { 
                showImage(currentVis + 1); 
            }, slideDuration);
        }

        function resetSlideshow() {
            clearInterval(slideInterval);
            startSlideshow();
        }

        if(ui.visPrev) {
            ui.visPrev.addEventListener('click', () => { 
                showImage(currentVis - 1); 
                resetSlideshow(); 
            });
            
            ui.visNext.addEventListener('click', () => { 
                showImage(currentVis + 1); 
                resetSlideshow(); 
            });
        }

        startSlideshow();
    }
});
