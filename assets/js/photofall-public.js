/**
 * File: assets/js/photofall-public.js
 * Version: 6.2.3 (AJAX Format Fix & tbfnmi_ Prefixes)
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
    
    // WordPress Scanner Fix: TBF_Data is now tbfnmi_data
    const dataObj = typeof tbfnmi_data !== 'undefined' ? tbfnmi_data : null;

    if (loadMoreBtn && dataObj) {
        loadMoreBtn.addEventListener('click', function() {
            if (parseInt(dataObj.current_page) >= parseInt(dataObj.max_pages)) return;

            loadMoreBtn.disabled = true;
            loadMoreBtn.innerText = 'Loading...';
            if(loader) loader.style.display = 'inline-block';

            // FIX: Using URLSearchParams forces application/x-www-form-urlencoded (WP's native AJAX format)
            const params = new URLSearchParams();
            params.append('action', 'tbfnmi_load_more');
            params.append('nonce', dataObj.nonce);
            params.append('page', parseInt(dataObj.current_page) + 1);
            params.append('filter', dataObj.filter || 'all'); 
            params.append('source', dataObj.source || 'all'); 
            params.append('sort', dataObj.sort || '');
            params.append('search', dataObj.search || '');
            params.append('year', dataObj.year || '');
            params.append('site_filter', dataObj.site_filter || '');

            fetch(dataObj.ajax_url, { 
                method: 'POST', 
                body: params 
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = res.data.html;
                    
                    Array.from(tempDiv.children).forEach(node => gridContainer.appendChild(node));
                    
                    dataObj.current_page++;
                    if (dataObj.current_page >= res.data.max_pages) {
                        loadMoreBtn.style.display = 'none';
                    }
                } else {
                    console.error('TBF Photofall Error:', res);
                }
            })
            .catch(err => console.error('TBF Photofall Network Error:', err))
            .finally(() => {
                loadMoreBtn.disabled = false;
                loadMoreBtn.innerText = 'Load More';
                if(loader) loader.style.display = 'none';
            });
        });
    }

    // --- 3. Lightbox Logic (Now prefixed as tbfnmi_photofall) ---
    window.tbfnmi_photofall = {
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
            if (!lb) return;
            
            const lbImg = document.getElementById('tbf-lb-img');
            const lbVid = document.getElementById('tbf-lb-video');
            const lbAud = document.getElementById('tbf-lb-audio');
            const lbCap = document.getElementById('tbf-lb-caption');
            const lbLink = document.getElementById('tbf-lb-link');

            if(lbVid) { lbVid.pause(); lbVid.style.display = 'none'; }
            if(lbAud) { lbAud.pause(); lbAud.style.display = 'none'; }
            if(lbImg) { lbImg.style.display = 'none'; lbImg.style.width = ''; lbImg.style.marginBottom = ''; }

            if (type === 'video' && lbVid) {
                lbVid.style.display = 'block'; 
                lbVid.src = src; 
                try { lbVid.play(); } catch(e){}
            } 
            else if (type === 'audio' && lbAud && lbImg && dataObj) {
                lbImg.style.display = 'block'; 
                lbImg.src = dataObj.includes_url + 'images/media/audio.png';
                lbImg.style.width = '150px'; 
                lbImg.style.marginBottom = '20px';
                
                lbAud.style.display = 'block'; 
                lbAud.src = src; 
                try { lbAud.play(); } catch(e){}
            } 
            else if (lbImg) {
                lbImg.style.display = 'block'; 
                lbImg.src = src;
            }

            if(lbCap) lbCap.innerText = caption || '';
            if (lbLink) {
                if (permalink) { 
                    lbLink.style.display = 'inline-block'; 
                    lbLink.href = permalink; 
                } else { 
                    lbLink.style.display = 'none'; 
                }
            }

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
    if (closeBtn) closeBtn.addEventListener('click', () => tbfnmi_photofall.close());
    
    const nextBtn = document.querySelector('.tbf-next'); 
    if (nextBtn) nextBtn.addEventListener('click', (e) => { e.stopPropagation(); tbfnmi_photofall.nav(1); });
    
    const prevBtn = document.querySelector('.tbf-prev'); 
    if (prevBtn) prevBtn.addEventListener('click', (e) => { e.stopPropagation(); tbfnmi_photofall.nav(-1); });
    
    const lb = document.getElementById('tbf-lightbox');
    if (lb) lb.addEventListener('click', (e) => { 
        if (e.target.id === 'tbf-lightbox' || e.target.classList.contains('tbf-lightbox-content')) tbfnmi_photofall.close(); 
    });
    
    document.addEventListener('keydown', (e) => {
        if (lb && lb.style.display === 'block') {
            if (e.key === 'Escape') tbfnmi_photofall.close();
            if (e.key === 'ArrowRight') tbfnmi_photofall.nav(1);
            if (e.key === 'ArrowLeft') tbfnmi_photofall.nav(-1);
        }
    });
})();