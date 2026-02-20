/**
 * File: assets/js/photofall-public.js
 * Version: 6.0.4 (Unified Lightbox & Grid Logic)
 */
(function() {
    'use strict';
    
    // --- 1. Form Auto-Submit Logic ---
    const filterForm = document.getElementById('tbf-filter-form');
    if (filterForm) {
        document.querySelectorAll('.tbf-auto-submit').forEach(el => {
            el.addEventListener('change', () => { filterForm.submit(); });
        });
    }

    // --- 2. AJAX Load More Logic ---
    const loadMoreBtn = document.getElementById('tbf-load-more');
    const loader = document.getElementById('tbf-loader');
    const gridContainer = document.getElementById('tbf-grid-container');
    
    // Safety check: ensure TBF_Data was passed from PHP
    const tbfData = typeof TBF_Data !== 'undefined' ? TBF_Data : null;

    if (loadMoreBtn && tbfData) {
        loadMoreBtn.addEventListener('click', function() {
            if (tbfData.current_page >= tbfData.max_pages) return;

            loadMoreBtn.disabled = true;
            loadMoreBtn.innerText = 'Loading...';
            if(loader) loader.style.display = 'inline-block';

            const formData = new FormData();
            formData.append('action', 'tbfnmi_load_more');
            formData.append('nonce', tbfData.nonce);
            formData.append('page', parseInt(tbfData.current_page) + 1);
            
            // Pass active filters
            formData.append('filter', tbfData.filter || 'all'); 
            formData.append('sort', tbfData.sort || '');
            formData.append('search', tbfData.search || '');
            formData.append('year', tbfData.year || '');
            formData.append('site_filter', tbfData.site_filter || '');

            fetch(tbfData.ajax_url, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = res.data.html;
                    
                    Array.from(tempDiv.children).forEach(node => gridContainer.appendChild(node));
                    
                    tbfData.current_page++;
                    if (tbfData.current_page >= res.data.max_pages) {
                        loadMoreBtn.style.display = 'none';
                    }
                }
            })
            .catch(err => console.error('TBF Photofall Error:', err))
            .finally(() => {
                loadMoreBtn.disabled = false;
                loadMoreBtn.innerText = 'Load More';
                if(loader) loader.style.display = 'none';
            });
        });
    }

    // --- 3. Lightbox Logic ---
    window.TBF_Photofall = {
        currentTrigger: null,
        
        open: function(triggerImg) {
            this.currentTrigger = triggerImg;
            this.render(
                triggerImg.getAttribute('data-full'),
                triggerImg.getAttribute('data-type'),
                triggerImg.getAttribute('data-caption'),
                triggerImg.getAttribute('data-permalink')
            );
        },
        
        openRaw: function(src, type, caption) { 
            this.render(src, type, caption, null); 
        },
        
        render: function(src, type, caption, permalink) {
            const lb = document.getElementById('tbf-lightbox');
            if (!lb) return; // Failsafe if HTML is missing
            
            const lbImg = document.getElementById('tbf-lb-img');
            const lbVid = document.getElementById('tbf-lb-video');
            const lbAud = document.getElementById('tbf-lb-audio');
            const lbCap = document.getElementById('tbf-lb-caption');
            const lbLink = document.getElementById('tbf-lb-link');

            // 1. Reset everything
            if(lbVid) { lbVid.pause(); lbVid.style.display = 'none'; }
            if(lbAud) { lbAud.pause(); lbAud.style.display = 'none'; }
            if(lbImg) { lbImg.style.display = 'none'; lbImg.style.width = ''; lbImg.style.marginBottom = ''; }

            // 2. Setup correct media type
            if (type === 'video' && lbVid) {
                lbVid.style.display = 'block'; 
                lbVid.src = src; 
                try { lbVid.play(); } catch(e){}
            } 
            else if (type === 'audio' && lbAud && lbImg && tbfData) {
                // Audio gets a visual icon + the player
                lbImg.style.display = 'block'; 
                lbImg.src = tbfData.includes_url + 'images/media/audio.png';
                lbImg.style.width = '150px'; 
                lbImg.style.marginBottom = '20px';
                
                lbAud.style.display = 'block'; 
                lbAud.src = src; 
                try { lbAud.play(); } catch(e){}
            } 
            else if (lbImg) {
                // Standard Image
                lbImg.style.display = 'block'; 
                lbImg.src = src;
            }

            // 3. Meta Data
            if(lbCap) lbCap.innerText = caption || '';
            if (lbLink) {
                if (permalink) { 
                    lbLink.style.display = 'inline-block'; 
                    lbLink.href = permalink; 
                } else { 
                    lbLink.style.display = 'none'; 
                }
            }

            // 4. Show Lightbox
            lb.style.display = 'block'; 
            document.body.style.overflow = 'hidden';
        },
        
        close: function() {
            const lb = document.getElementById('tbf-lightbox'); 
            if(lb) lb.style.display = 'none';
            
            const vid = document.getElementById('tbf-lb-video'); 
            if(vid) vid.pause();
            
            const aud = document.getElementById('tbf-lb-audio'); 
            if(aud) aud.pause();
            
            document.body.style.overflow = '';
        },
        
        nav: function(direction) {
            if (!this.currentTrigger) return;
            const allVisible = Array.from(document.querySelectorAll('.tbf-grid-item:not(.hidden) .tbf-photofall-img'));
            let idx = allVisible.indexOf(this.currentTrigger);
            if (idx === -1) return;
            let nextIdx = idx + direction;
            if (nextIdx >= allVisible.length) nextIdx = 0;
            if (nextIdx < 0) nextIdx = allVisible.length - 1;
            this.open(allVisible[nextIdx]);
        }
    };

    // --- 4. Event Listeners ---
    const closeBtn = document.querySelector('.tbf-close'); 
    if (closeBtn) closeBtn.addEventListener('click', () => TBF_Photofall.close());
    
    const nextBtn = document.querySelector('.tbf-next'); 
    if (nextBtn) nextBtn.addEventListener('click', (e) => { e.stopPropagation(); TBF_Photofall.nav(1); });
    
    const prevBtn = document.querySelector('.tbf-prev'); 
    if (prevBtn) prevBtn.addEventListener('click', (e) => { e.stopPropagation(); TBF_Photofall.nav(-1); });
    
    const lb = document.getElementById('tbf-lightbox');
    if (lb) lb.addEventListener('click', (e) => { 
        if (e.target.id === 'tbf-lightbox' || e.target.classList.contains('tbf-lightbox-content')) TBF_Photofall.close(); 
    });
    
    document.addEventListener('keydown', (e) => {
        if (lb && lb.style.display === 'block') {
            if (e.key === 'Escape') TBF_Photofall.close();
            if (e.key === 'ArrowRight') TBF_Photofall.nav(1);
            if (e.key === 'ArrowLeft') TBF_Photofall.nav(-1);
        }
    });
})();
